<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => trim((string) $request->input('status', '')),
            'discount_type' => trim((string) $request->input('discount_type', '')),
        ];

        $query = Coupon::query();

        if ($filters['q'] !== '') {
            $query->where(function ($builder) use ($filters) {
                $builder->where('code', 'like', '%' . $filters['q'] . '%')
                    ->orWhere('name', 'like', '%' . $filters['q'] . '%');
            });
        }

        if ($filters['status'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['status'] === 'inactive') {
            $query->where('is_active', false);
        }

        if ($filters['discount_type'] !== '') {
            $query->where('discount_type', $filters['discount_type']);
        }

        $coupons = $query
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.coupons.index', [
            'coupons' => $coupons,
            'filters' => $filters,
            'discountTypeOptions' => $this->discountTypeOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.coupons.create', [
            'coupon' => new Coupon([
                'discount_type' => Coupon::TYPE_PERCENTAGE,
                'discount_value' => 0,
                'currency_code' => null,
                'is_active' => true,
                'applies_to_all_plans' => true,
                'first_billing_cycle_only' => false,
                'max_redemptions' => null,
                'max_redemptions_per_tenant' => null,
                'starts_at' => null,
                'ends_at' => null,
                'notes' => null,
            ]),
            'discountTypeOptions' => $this->discountTypeOptions(),
            'plans' => $this->availablePlans(),
            'selectedPlanIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCoupon($request);

        $coupon = Coupon::query()->create([
            'code' => strtoupper((string) $validated['code']),
            'name' => $validated['name'],
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'currency_code' => $validated['currency_code'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'applies_to_all_plans' => (bool) ($validated['applies_to_all_plans'] ?? false),
            'first_billing_cycle_only' => (bool) ($validated['first_billing_cycle_only'] ?? false),
            'max_redemptions' => $validated['max_redemptions'] ?? null,
            'max_redemptions_per_tenant' => $validated['max_redemptions_per_tenant'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->syncPlans($coupon, $validated);

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', 'Coupon created successfully.');
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.edit', [
            'coupon' => $coupon,
            'discountTypeOptions' => $this->discountTypeOptions(),
            'plans' => $this->availablePlans(),
            'selectedPlanIds' => $coupon->plans()->pluck('plans.id')->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $validated = $this->validateCoupon($request, $coupon);

        $coupon->update([
            'code' => strtoupper((string) $validated['code']),
            'name' => $validated['name'],
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'currency_code' => $validated['currency_code'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'applies_to_all_plans' => (bool) ($validated['applies_to_all_plans'] ?? false),
            'first_billing_cycle_only' => (bool) ($validated['first_billing_cycle_only'] ?? false),
            'max_redemptions' => $validated['max_redemptions'] ?? null,
            'max_redemptions_per_tenant' => $validated['max_redemptions_per_tenant'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->syncPlans($coupon, $validated);

        return redirect()
            ->route('admin.coupons.edit', $coupon)
            ->with('success', 'Coupon updated successfully.');
    }

    public function toggleActive(Coupon $coupon): RedirectResponse
    {
        $coupon->update([
            'is_active' => ! $coupon->is_active,
        ]);

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', 'Coupon status updated successfully.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->plans()->detach();
        $coupon->delete();

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', 'Coupon deleted successfully.');
    }

    protected function validateCoupon(Request $request, ?Coupon $coupon = null): array
    {
        $couponId = $coupon?->id;

        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('coupons', 'code')
                    ->ignore($couponId)
                    ->where(fn ($query) => $query),
            ],
            'name' => ['required', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in(array_keys($this->discountTypeOptions()))],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
            'applies_to_all_plans' => ['nullable', 'boolean'],
            'first_billing_cycle_only' => ['nullable', 'boolean'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'max_redemptions_per_tenant' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string'],
            'plan_ids' => ['nullable', 'array'],
            'plan_ids.*' => ['integer'],
        ]);
    }

    protected function syncPlans(Coupon $coupon, array $validated): void
    {
        $appliesToAllPlans = (bool) ($validated['applies_to_all_plans'] ?? false);

        if ($appliesToAllPlans) {
            $coupon->plans()->detach();

            return;
        }

        $selectedPlanIds = collect($validated['plan_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $coupon->plans()->sync($selectedPlanIds);
    }

    protected function discountTypeOptions(): array
    {
        return [
            Coupon::TYPE_PERCENTAGE => 'Percentage',
            Coupon::TYPE_FIXED => 'Fixed Amount',
        ];
    }

    protected function availablePlans()
    {
        $connection = (string) (Config::get('tenancy.database.central_connection') ?: Config::get('database.default'));

        if (! Schema::connection($connection)->hasTable('plans')) {
            return collect();
        }

        $columns = Schema::connection($connection)->getColumnListing('plans');

        $selectColumns = array_values(array_filter([
            'id',
            'name',
            'slug',
            in_array('billing_period', $columns, true) ? 'billing_period' : null,
            in_array('price', $columns, true) ? 'price' : null,
            in_array('is_active', $columns, true) ? 'is_active' : null,
        ]));

        $query = DB::connection($connection)
            ->table('plans')
            ->select($selectColumns);

        if (in_array('is_active', $columns, true)) {
            $query->orderByDesc('is_active');
        }

        if (in_array('sort_order', $columns, true)) {
            $query->orderBy('sort_order');
        }

        $query->orderBy('id');

        return $query->get();
    }
}
