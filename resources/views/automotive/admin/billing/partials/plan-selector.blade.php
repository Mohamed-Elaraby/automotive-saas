@php
    $plans = $availablePlans ?? collect();
    $selectedPlanId = (string) ($selectedPlanId ?? '');
@endphp

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h5 class="mb-1">Choose Paid Plan</h5>
                <p class="text-muted mb-0">
                    Select a subscription plan. The page will refresh automatically to load pricing verification and Stripe preview for the selected plan.
                </p>
            </div>
        </div>

        @if($plans->isEmpty())
            <div class="alert alert-warning mb-0">
                No paid plans are currently available.
            </div>
        @else
            <div class="row">
                @foreach($plans as $billingPlan)
                    @php
                        $isSelected = $selectedPlanId === (string) $billingPlan->id;
                    @endphp

                    <div class="col-xl-4 col-lg-6 d-flex">
                        <label class="card border w-100 cursor-pointer {{ $isSelected ? 'border-primary' : '' }}">
                            <div class="card-body">
                                <div class="form-check mb-3">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        name="target_plan_id"
                                        value="{{ $billingPlan->id }}"
                                        id="target_plan_{{ $billingPlan->id }}"
                                        {{ $isSelected ? 'checked' : '' }}
                                    >
                                    <span class="form-check-label fw-semibold">
                                        {{ $billingPlan->name }}
                                    </span>
                                </div>

                                <h4 class="mb-2">
                                    {{ number_format((float) $billingPlan->price, 2) }}
                                    <small class="text-muted">{{ strtoupper($billingPlan->currency ?? 'USD') }}</small>
                                </h4>

                                <p class="text-muted mb-3">
                                    {{ ucfirst($billingPlan->billing_period ?? 'monthly') }}
                                </p>

                                @if(!empty($billingPlan->description))
                                    <p class="mb-3">{{ $billingPlan->description }}</p>
                                @endif

                                <ul class="mb-0 ps-3">
                                    <li class="mb-1">Users: {{ $billingPlan->max_users ?? '-' }}</li>
                                    <li class="mb-1">Branches: {{ $billingPlan->max_branches ?? '-' }}</li>
                                    <li class="mb-1">Products: {{ $billingPlan->max_products ?? '-' }}</li>
                                    <li class="mb-1">Storage: {{ $billingPlan->max_storage_mb ?? '-' }} MB</li>

                                    @foreach(($billingPlan->features_array ?? []) as $featureKey => $enabled)
                                        @if($enabled)
                                            <li class="mb-1">{{ ucwords(str_replace('_', ' ', $featureKey)) }}</li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </label>
                    </div>
                @endforeach
            </div>

            @error('target_plan_id')
            <div class="text-danger small mt-2">{{ $message }}</div>
            @enderror
        @endif
    </div>
</div>
