<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::query()
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

        return view('admin.plans.index', compact('plans'));
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
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data = $this->preparePlanData($data, $request);

        Plan::create($data);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan created successfully.');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $this->validatedData($request, $plan->id);
        $data = $this->preparePlanData($data, $request);

        $plan->update($data);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan updated successfully.');
    }

    public function toggleActive(Plan $plan)
    {
        $plan->update([
            'is_active' => ! $plan->is_active,
        ]);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan status updated successfully.');
    }

    public function destroy(Plan $plan)
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

        $plan->delete();

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan deleted successfully.');
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
            ]) + [
                'is_active' => $request->boolean('is_active'),
            ];
    }

    protected function preparePlanData(array $data, Request $request): array
    {
        $data['slug'] = Str::slug((string) $data['slug']);
        $data['currency'] = strtoupper((string) $data['currency']);
        $data['stripe_price_id'] = trim((string) ($data['stripe_price_id'] ?? '')) ?: null;
        $data['features'] = $this->normalizeFeatures($request->input('features_json'));

        if ($data['billing_period'] === 'trial') {
            $data['price'] = 0;
            $data['stripe_price_id'] = null;
        }

        return $data;
    }

    protected function normalizeFeatures(?string $featuresJson): ?array
    {
        if (blank($featuresJson)) {
            return null;
        }

        $decoded = json_decode($featuresJson, true);

        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
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
