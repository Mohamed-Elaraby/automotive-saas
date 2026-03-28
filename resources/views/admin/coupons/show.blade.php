<?php $page = 'admin-coupons-show'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Coupon Details</h5>
                    <p class="text-muted mb-0">Review coupon rules, linked plans, usage activity, and eligibility preview.</p>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('admin.coupons.index') }}" class="btn btn-light">Back</a>
                    <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn btn-primary">Edit Coupon</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Coupon Code</div>
                            <h6 class="mb-0">{{ $coupon->code }}</h6>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Status</div>
                            <h6 class="mb-0">
                                <span class="badge {{ $coupon->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $coupon->is_active ? 'ACTIVE' : 'INACTIVE' }}
                                </span>
                            </h6>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Discount</div>
                            <h6 class="mb-0">
                                @if($coupon->discount_type === \App\Models\Coupon::TYPE_PERCENTAGE)
                                    {{ rtrim(rtrim(number_format((float) $coupon->discount_value, 2, '.', ''), '0'), '.') }}%
                                @else
                                    {{ rtrim(rtrim(number_format((float) $coupon->discount_value, 2, '.', ''), '0'), '.') }}
                                    {{ $coupon->currency_code ?: '' }}
                                @endif
                            </h6>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Total Redemptions</div>
                            <h6 class="mb-0">{{ $coupon->redemptions()->count() }}</h6>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Coupon Rules</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <tbody>
                                <tr>
                                    <th style="width: 260px;">Coupon Code</th>
                                    <td>{{ $coupon->code }}</td>
                                </tr>
                                <tr>
                                    <th>Name</th>
                                    <td>{{ $coupon->name }}</td>
                                </tr>
                                <tr>
                                    <th>Discount Type</th>
                                    <td>{{ strtoupper($coupon->discount_type) }}</td>
                                </tr>
                                <tr>
                                    <th>Discount Value</th>
                                    <td>{{ $coupon->discount_value }}</td>
                                </tr>
                                <tr>
                                    <th>Currency Code</th>
                                    <td>{{ $coupon->currency_code ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Applies to All Plans</th>
                                    <td>{{ $coupon->applies_to_all_plans ? 'Yes' : 'No' }}</td>
                                </tr>
                                <tr>
                                    <th>First Billing Cycle Only</th>
                                    <td>{{ $coupon->first_billing_cycle_only ? 'Yes' : 'No' }}</td>
                                </tr>
                                <tr>
                                    <th>Max Redemptions</th>
                                    <td>{{ $coupon->max_redemptions ?: 'Unlimited' }}</td>
                                </tr>
                                <tr>
                                    <th>Max Redemptions Per Tenant</th>
                                    <td>{{ $coupon->max_redemptions_per_tenant ?: 'Unlimited' }}</td>
                                </tr>
                                <tr>
                                    <th>Starts At</th>
                                    <td>{{ optional($coupon->starts_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Ends At</th>
                                    <td>{{ optional($coupon->ends_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Notes</th>
                                    <td>{{ $coupon->notes ?: '-' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Eligibility Preview</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.coupons.preview-eligibility', $coupon) }}">
                                @csrf

                                <div class="row g-3">
                                    <div class="col-xl-4">
                                        <label class="form-label">Tenant ID</label>
                                        <input
                                            type="text"
                                            name="tenant_id"
                                            value="{{ $previewInput['tenant_id'] ?? '' }}"
                                            class="form-control"
                                            placeholder="Optional tenant ID"
                                        >
                                    </div>

                                    <div class="col-xl-4">
                                        <label class="form-label">Plan</label>
                                        <select name="plan_id" class="form-select">
                                            <option value="">No plan selected</option>
                                            @foreach($plans as $plan)
                                                @php
                                                    $planLabel = $plan->name ?: $plan->slug ?: ('Plan #' . $plan->id);
                                                @endphp
                                                <option value="{{ $plan->id }}" @selected((string) ($previewInput['plan_id'] ?? '') === (string) $plan->id)>
                                                {{ $planLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-xl-4">
                                        <label class="form-label">Billing Cycle Context</label>
                                        <select name="is_first_billing_cycle" class="form-select">
                                            <option value="1" @selected(($previewInput['is_first_billing_cycle'] ?? '1') === '1')>First billing cycle</option>
                                            <option value="0" @selected(($previewInput['is_first_billing_cycle'] ?? '1') === '0')>Not first billing cycle</option>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">Run Eligibility Preview</button>
                                    </div>
                                </div>
                            </form>

                            @if($previewResult)
                                <div class="mt-4">
                                    <div class="alert {{ !empty($previewResult['eligible']) ? 'alert-success' : 'alert-danger' }}">
                                        {{ $previewResult['summary'] ?? 'Preview completed.' }}
                                    </div>

                                    @if(!empty($previewResult['reasons']))
                                        <div class="mb-3">
                                            <h6 class="mb-2">Reasons</h6>
                                            <ul class="mb-0">
                                                @foreach($previewResult['reasons'] as $reason)
                                                    <li>{{ $reason }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    <div>
                                        <h6 class="mb-2">Evaluation Meta</h6>
                                        <pre class="bg-light p-3 rounded mb-0">{{ json_encode($previewResult['meta'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Latest Redemptions</h6>
                        </div>
                        <div class="card-body">
                            @if($latestRedemptions->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle mb-0">
                                        <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Tenant</th>
                                            <th>Subscription</th>
                                            <th>Plan</th>
                                            <th>Status</th>
                                            <th>Discount Amount</th>
                                            <th>When</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($latestRedemptions as $redemption)
                                            <tr>
                                                <td>{{ $redemption->id }}</td>
                                                <td>{{ $redemption->tenant_id ?: '-' }}</td>
                                                <td>{{ $redemption->subscription_id ?: '-' }}</td>
                                                <td>{{ $redemption->plan_id ?: '-' }}</td>
                                                <td>{{ strtoupper($redemption->status) }}</td>
                                                <td>
                                                    @if($redemption->discount_amount !== null)
                                                        {{ $redemption->discount_amount }} {{ $redemption->currency_code ?: '' }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>{{ optional($redemption->created_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-warning mb-0">No redemption activity has been recorded for this coupon yet.</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Allowed Plans</h6>
                        </div>
                        <div class="card-body">
                            @if($coupon->applies_to_all_plans)
                                <div class="alert alert-info mb-0">This coupon applies to all plans.</div>
                            @elseif($coupon->plans->count() > 0)
                                <div class="list-group list-group-flush">
                                    @foreach($coupon->plans as $plan)
                                        <div class="list-group-item px-0">
                                            <div class="fw-semibold">{{ $plan->name ?: $plan->slug ?: ('Plan #' . $plan->id) }}</div>
                                            <small class="text-muted">Plan ID: {{ $plan->id }}</small>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="alert alert-warning mb-0">This coupon is restricted to selected plans, but no plans are currently linked.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
