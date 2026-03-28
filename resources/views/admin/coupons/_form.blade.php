@php
    $selectedPlanIds = collect($selectedPlanIds ?? [])->map(fn ($id) => (int) $id)->all();
@endphp

<div class="row g-3">
    <div class="col-xl-4">
        <label class="form-label">Coupon Code</label>
        <input
            type="text"
            name="code"
            value="{{ old('code', $coupon->code) }}"
            class="form-control"
            placeholder="WELCOME20"
            required
        >
    </div>

    <div class="col-xl-8">
        <label class="form-label">Coupon Name</label>
        <input
            type="text"
            name="name"
            value="{{ old('name', $coupon->name) }}"
            class="form-control"
            placeholder="Welcome 20% Off"
            required
        >
    </div>

    <div class="col-xl-3">
        <label class="form-label">Discount Type</label>
        <select name="discount_type" class="form-select" required>
            @foreach($discountTypeOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('discount_type', $coupon->discount_type) === $value)>
                {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-xl-3">
        <label class="form-label">Discount Value</label>
        <input
            type="number"
            step="0.01"
            min="0.01"
            name="discount_value"
            value="{{ old('discount_value', $coupon->discount_value) }}"
            class="form-control"
            required
        >
    </div>

    <div class="col-xl-3">
        <label class="form-label">Currency Code</label>
        <input
            type="text"
            name="currency_code"
            value="{{ old('currency_code', $coupon->currency_code) }}"
            class="form-control"
            placeholder="USD"
        >
        <small class="text-muted">Useful for fixed discounts.</small>
    </div>

    <div class="col-xl-3">
        <label class="form-label">Status</label>
        <select name="is_active" class="form-select">
            <option value="1" @selected((string) old('is_active', (int) $coupon->is_active) === '1')>Active</option>
            <option value="0" @selected((string) old('is_active', (int) $coupon->is_active) === '0')>Inactive</option>
        </select>
    </div>

    <div class="col-xl-3">
        <label class="form-label">Max Redemptions</label>
        <input
            type="number"
            min="1"
            step="1"
            name="max_redemptions"
            value="{{ old('max_redemptions', $coupon->max_redemptions) }}"
            class="form-control"
            placeholder="Unlimited"
        >
    </div>

    <div class="col-xl-3">
        <label class="form-label">Max Redemptions Per Tenant</label>
        <input
            type="number"
            min="1"
            step="1"
            name="max_redemptions_per_tenant"
            value="{{ old('max_redemptions_per_tenant', $coupon->max_redemptions_per_tenant) }}"
            class="form-control"
            placeholder="Unlimited"
        >
    </div>

    <div class="col-xl-3">
        <label class="form-label">Starts At</label>
        <input
            type="datetime-local"
            name="starts_at"
            value="{{ old('starts_at', optional($coupon->starts_at)->format('Y-m-d\TH:i')) }}"
            class="form-control"
        >
    </div>

    <div class="col-xl-3">
        <label class="form-label">Ends At</label>
        <input
            type="datetime-local"
            name="ends_at"
            value="{{ old('ends_at', optional($coupon->ends_at)->format('Y-m-d\TH:i')) }}"
            class="form-control"
        >
    </div>

    <div class="col-xl-6">
        <div class="form-check mt-4">
            <input
                class="form-check-input"
                type="checkbox"
                value="1"
                id="applies_to_all_plans"
                name="applies_to_all_plans"
                @checked((bool) old('applies_to_all_plans', $coupon->applies_to_all_plans))
            >
            <label class="form-check-label" for="applies_to_all_plans">
                Applies to all plans
            </label>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="form-check mt-4">
            <input
                class="form-check-input"
                type="checkbox"
                value="1"
                id="first_billing_cycle_only"
                name="first_billing_cycle_only"
                @checked((bool) old('first_billing_cycle_only', $coupon->first_billing_cycle_only))
            >
            <label class="form-check-label" for="first_billing_cycle_only">
                First billing cycle only
            </label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Allowed Plans</label>
        @if($plans->count() > 0)
            <div class="row g-2">
                @foreach($plans as $plan)
                    @php
                        $planLabel = $plan->name ?: $plan->slug ?: ('Plan #' . $plan->id);
                        $periodLabel = $plan->billing_period ?? null;
                        $priceLabel = $plan->price ?? null;
                        $isSelected = in_array((int) $plan->id, old('plan_ids', $selectedPlanIds), true);
                    @endphp
                    <div class="col-xl-4 col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="plan_ids[]"
                                    value="{{ $plan->id }}"
                                    id="plan_{{ $plan->id }}"
                                    @checked($isSelected)
                                >
                                <label class="form-check-label" for="plan_{{ $plan->id }}">
                                    <strong>{{ $planLabel }}</strong>
                                </label>
                            </div>

                            <div class="small text-muted mt-2">
                                @if($periodLabel)
                                    <div>Period: {{ strtoupper($periodLabel) }}</div>
                                @endif

                                @if($priceLabel !== null)
                                    <div>Price: {{ $priceLabel }}</div>
                                @endif

                                @if(isset($plan->is_active))
                                    <div>Status: {{ (int) $plan->is_active === 1 ? 'Active' : 'Inactive' }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <small class="text-muted d-block mt-2">
                These selected plans are used only when “Applies to all plans” is unchecked.
            </small>
        @else
            <div class="alert alert-warning mb-0">No plans are available yet.</div>
        @endif
    </div>

    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea
            name="notes"
            rows="4"
            class="form-control"
            placeholder="Internal admin notes about this coupon..."
        >{{ old('notes', $coupon->notes) }}</textarea>
    </div>
</div>
