<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingFeature;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\StripePlanCatalogSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->string('q')),
            'billing_period' => (string) $request->string('billing_period'),
            'status' => (string) $request->string('status'),
            'stripe' => (string) $request->string('stripe'),
        ];

        $plans = Plan::query()
            ->with('billingFeatures')
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $search = $filters['q'];

                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when(in_array($filters['billing_period'], ['trial', 'monthly', 'yearly', 'one_time'], true), function ($query) use ($filters) {
                $query->where('billing_period', $filters['billing_period']);
            })
            ->when(in_array($filters['status'], ['active', 'inactive'], true), function ($query) use ($filters) {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->when(in_array($filters['stripe'], ['linked', 'unlinked'], true), function ($query) use ($filters) {
                if ($filters['stripe'] === 'linked') {
                    $query->whereNotNull('stripe_price_id')
                        ->where('stripe_price_id', '!=', '');

                    return;
                }

                $query->where(function ($nested) {
                    $nested->whereNull('stripe_price_id')
                        ->orWhere('stripe_price_id', '');
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Plan $plan) {
                $plan->subscriptions_count = DB::table('subscriptions')
                    ->where('plan_id', $plan->id)
                    ->count();

                $plan->billing_period_label = $this->billingPeriodLabel($plan->billing_period);
                $plan->display_price = number_format((float) $plan->price, 2) . ' ' . strtoupper((string) $plan->currency);

                return $plan;
            });

        return view('admin.plans.index', compact('plans', 'filters'));
    }

    public function create()
    {
        return view('admin.plans.create', [
            'plan' => new Plan([
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'is_active' => true,
                'sort_order' => 0,
            ]),
            'availableFeatures' => $this->availableFeatures(),
        ]);
    }

    public function show(Plan $plan)
    {
        $plan->load('billingFeatures');

        $subscriptions = Subscription::query()
            ->where('plan_id', $plan->id)
            ->latest('id')
            ->get();

        $usageByStatus = $subscriptions
            ->groupBy(fn (Subscription $subscription) => (string) $subscription->status)
            ->map(fn ($group, $status) => [
                'status' => $status,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values();

        return view('admin.plans.show', [
            'plan' => $plan,
            'subscriptions' => $subscriptions,
            'usageByStatus' => $usageByStatus,
        ]);
    }

    public function store(Request $request, StripePlanCatalogSyncService $stripePlanCatalogSyncService)
    {
        $data = $this->validatedData($request);
        $featureIds = $this->normalizeFeatureIds($request);
        $data = $this->preparePlanData($data, $request);

        $plan = Plan::create($data);
        $this->syncPlanFeatures($plan, $featureIds);

        $sync = $stripePlanCatalogSyncService->syncPlan($plan);

        if (! $sync['ok']) {
            return redirect()
                ->route('admin.plans.index')
                ->with('success', 'Plan created locally, but Stripe sync failed: ' . $sync['message']);
        }

        $message = !empty($sync['skipped'])
            ? 'Plan created successfully. Stripe sync was skipped because Stripe is not configured.'
            : 'Plan created successfully and synced with Stripe.';

        return redirect()
            ->route('admin.plans.index')
            ->with('success', $message);
    }

    public function edit(Plan $plan)
    {
        $plan->load('billingFeatures');

        return view('admin.plans.edit', [
            'plan' => $plan,
            'availableFeatures' => $this->availableFeatures(),
        ]);
    }

    public function update(Request $request, Plan $plan, StripePlanCatalogSyncService $stripePlanCatalogSyncService)
    {
        $data = $this->validatedData($request, $plan->id);
        $featureIds = $this->normalizeFeatureIds($request);
        $data = $this->preparePlanData($data, $request);

        $plan->update($data);
        $this->syncPlanFeatures($plan, $featureIds);

        $sync = $stripePlanCatalogSyncService->syncPlan($plan->fresh());

        if (! $sync['ok']) {
            return redirect()
                ->route('admin.plans.index')
                ->with('success', 'Plan updated locally, but Stripe sync failed: ' . $sync['message']);
        }

        $message = !empty($sync['skipped'])
            ? 'Plan updated successfully. Stripe sync was skipped because Stripe is not configured.'
            : 'Plan updated successfully and synced with Stripe.';

        return redirect()
            ->route('admin.plans.index')
            ->with('success', $message);
    }

    public function toggleActive(Plan $plan, StripePlanCatalogSyncService $stripePlanCatalogSyncService)
    {
        $plan->update([
            'is_active' => ! $plan->is_active,
        ]);

        $sync = $stripePlanCatalogSyncService->syncPlan($plan->fresh());

        if (! $sync['ok']) {
            return redirect()
                ->route('admin.plans.index')
                ->with('success', 'Plan status updated locally, but Stripe sync failed: ' . $sync['message']);
        }

        $message = !empty($sync['skipped'])
            ? 'Plan status updated successfully. Stripe sync was skipped because Stripe is not configured.'
            : 'Plan status updated successfully and synced with Stripe.';

        return redirect()
            ->route('admin.plans.index')
            ->with('success', $message);
    }

    public function destroy(Plan $plan, StripePlanCatalogSyncService $stripePlanCatalogSyncService)
    {
        $subscriptionsCount = DB::table('subscriptions')
            ->where('plan_id', $plan->id)
            ->count();

        if ($subscriptionsCount > 0) {
            return redirect()
                ->route('admin.plans.index')
                ->withErrors([
                    'delete' => 'Cannot delete a plan that is already used by subscriptions. Deactivate it instead.',
                ]);
        }

        $archive = $stripePlanCatalogSyncService->archivePlanResources($plan);

        $plan->delete();

        $message = !empty($archive['ok'])
            ? (!empty($archive['skipped'])
                ? 'Plan deleted locally. Stripe archive was skipped because Stripe is not configured.'
                : 'Plan deleted successfully and archived on Stripe.')
            : 'Plan deleted locally, but Stripe archive failed: ' . $archive['message'];

        return redirect()
            ->route('admin.plans.index')
            ->with('success', $message);
    }

    protected function validatedData(Request $request, ?int $planId = null): array
    {
        return $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'slug' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('plans', 'slug')->ignore($planId),
                ],
                'price' => ['required', 'numeric', 'min:0'],
                'currency' => ['required', 'string', 'size:3'],
                'billing_period' => ['required', 'string', Rule::in(['trial', 'monthly', 'yearly', 'one_time'])],
                'stripe_price_id' => ['nullable', 'string', 'max:255'],
                'is_active' => ['nullable', 'boolean'],
                'sort_order' => ['required', 'integer', 'min:0'],
                'max_users' => ['nullable', 'integer', 'min:1'],
                'max_branches' => ['nullable', 'integer', 'min:1'],
                'max_products' => ['nullable', 'integer', 'min:1'],
                'max_storage_mb' => ['nullable', 'integer', 'min:1'],
                'description' => ['nullable', 'string'],
                'feature_ids' => ['nullable', 'array'],
                'feature_ids.*' => ['integer', Rule::exists('billing_features', 'id')],
            ]) + [
                'is_active' => $request->boolean('is_active'),
            ];
    }

    protected function preparePlanData(array $data, Request $request): array
    {
        $data['slug'] = Str::slug((string) $data['slug']);
        $data['currency'] = strtoupper((string) $data['currency']);
        $data['stripe_price_id'] = trim((string) ($data['stripe_price_id'] ?? '')) ?: null;
        unset($data['feature_ids']);

        if ($data['billing_period'] === 'trial') {
            $data['price'] = 0;
            $data['stripe_price_id'] = null;
        }

        return $data;
    }

    protected function syncPlanFeatures(Plan $plan, array $featureIds): void
    {
        $syncPayload = collect($featureIds)
            ->values()
            ->mapWithKeys(fn (int $featureId, int $index) => [
                $featureId => [
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ])
            ->all();

        $plan->billingFeatures()->sync($syncPayload);
    }

    protected function normalizeFeatureIds(Request $request): array
    {
        return collect($request->input('feature_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function availableFeatures()
    {
        return BillingFeature::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function billingPeriodLabel(?string $period): string
    {
        return match ($period) {
        'trial' => 'Trial',
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'one_time' => 'One Time',
            default => ucfirst((string) $period),
        };
    }
}
