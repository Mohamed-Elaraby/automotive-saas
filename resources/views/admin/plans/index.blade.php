<?php $page = 'membership-plans'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two pb-0">

            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h6>{{ __('admin.plans') }}</h6>
                    <p class="mb-0">{{ __('admin.plans_intro') }}</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.billing-features.index') }}" class="btn btn-outline-white d-flex align-items-center">
                        <i class="isax isax-element-3 me-1"></i>{{ __('admin.manage_features') }}
                    </a>
                    <a href="{{ route('admin.plans.create') }}" class="btn btn-primary d-flex align-items-center">
                        <i class="isax isax-add-circle5 me-1"></i>{{ __('admin.new_plan') }}
                    </a>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->has('delete'))
                <div class="alert alert-danger">{{ $errors->first('delete') }}</div>
            @endif

            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.plans.index') }}" class="row g-3 align-items-end">
                        <div class="col-12 col-lg-4">
                            <label for="plan-search" class="form-label">{{ __('admin.search') }}</label>
                            <input
                                id="plan-search"
                                type="text"
                                name="q"
                                value="{{ $filters['q'] ?? '' }}"
                                class="form-control"
                                placeholder="{{ __('admin.search_by_name_or_slug') }}"
                            >
                        </div>
                        <div class="col-6 col-lg-2">
                            <label for="plan-product" class="form-label">{{ __('admin.product') }}</label>
                            <select id="plan-product" name="product_id" class="form-select">
                                <option value="">{{ __('admin.all_products') }}</option>
                                @foreach($availableProducts as $product)
                                    <option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') === (string) $product->id)>
                                        {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label for="plan-period" class="form-label">{{ __('admin.billing') }}</label>
                            <select id="plan-period" name="billing_period" class="form-select">
                                <option value="">{{ __('admin.all_periods') }}</option>
                                <option value="trial" @selected(($filters['billing_period'] ?? '') === 'trial')>{{ __('admin.trial') }}</option>
                                <option value="monthly" @selected(($filters['billing_period'] ?? '') === 'monthly')>{{ __('admin.monthly') }}</option>
                                <option value="yearly" @selected(($filters['billing_period'] ?? '') === 'yearly')>{{ __('admin.yearly') }}</option>
                                <option value="one_time" @selected(($filters['billing_period'] ?? '') === 'one_time')>{{ __('admin.one_time') }}</option>
                            </select>
                        </div>
                        <div class="col-6 col-lg-1">
                            <label for="plan-status" class="form-label">{{ __('admin.status') }}</label>
                            <select id="plan-status" name="status" class="form-select">
                                <option value="">{{ __('admin.all_statuses') }}</option>
                                <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('admin.active') }}</option>
                                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('admin.inactive') }}</option>
                            </select>
                        </div>
                        <div class="col-6 col-lg-1">
                            <label for="plan-stripe" class="form-label">{{ __('admin.stripe') }}</label>
                            <select id="plan-stripe" name="stripe" class="form-select">
                                <option value="">{{ __('admin.any_linkage') }}</option>
                                <option value="linked" @selected(($filters['stripe'] ?? '') === 'linked')>{{ __('admin.linked') }}</option>
                                <option value="unlinked" @selected(($filters['stripe'] ?? '') === 'unlinked')>{{ __('admin.unlinked') }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">{{ __('admin.apply') }}</button>
                            <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-white flex-fill">{{ __('admin.reset') }}</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-nowrap @if($plans->isNotEmpty()) datatable @endif">
                    <thead>
                    <tr>
                        <th>{{ __('admin.product') }}</th>
                        <th>{{ __('admin.name') }}</th>
                        <th>{{ __('admin.slug') }}</th>
                        <th>{{ __('admin.billing') }}</th>
                        <th>{{ __('admin.price') }}</th>
                        <th>{{ __('admin.stripe_price') }}</th>
                        <th>{{ __('admin.limits') }}</th>
                        <th>{{ __('admin.features') }}</th>
                        <th>{{ __('admin.status') }}</th>
                        <th>{{ __('admin.subscriptions') }}</th>
                        <th>{{ __('admin.order') }}</th>
                        <th class="no-sort"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($plans as $plan)
                        <tr>
                            <td>
                                <div>
                                    <p class="text-dark fw-medium mb-1">{{ $plan->product?->name ?: __('admin.no_product') }}</p>
                                    <small class="text-muted">{{ $plan->product?->code ?: '-' }}</small>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <p class="text-dark fw-medium mb-1">{{ $plan->name }}</p>
                                    @if($plan->description)
                                        <small class="text-muted">{{ $plan->description }}</small>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $plan->slug }}</td>
                            <td>
                                <span class="badge badge-soft-info d-inline-flex align-items-center">
                                    {{ $plan->billing_period_label }}
                                </span>
                                @if($plan->billing_period === 'trial')
                                    <small class="d-block text-muted mt-1">{{ (int) ($plan->trial_days ?? 14) }} {{ __('admin.days') }}</small>
                                @endif
                            </td>
                            <td>
                                <p class="text-dark mb-0">{{ $plan->display_price }}</p>
                            </td>
                            <td>
                                @if($plan->stripe_price_id)
                                    <small class="text-dark">{{ $plan->stripe_price_id }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $limitLines = collect([
                                        $plan->max_users ? $plan->max_users . ' ' . __('admin.users') : null,
                                        $plan->max_branches ? $plan->max_branches . ' ' . __('admin.branches') : null,
                                        $plan->max_products ? $plan->max_products . ' ' . __('admin.products_lower') : null,
                                        $plan->max_storage_mb ? $plan->max_storage_mb . ' MB ' . __('admin.storage') : null,
                                    ])->filter()->values();
                                @endphp

                                @if($limitLines->isEmpty())
                                    <span class="text-muted">{{ __('admin.no_advertised_limits') }}</span>
                                @else
                                    @foreach($limitLines as $line)
                                        <small class="d-block">{{ $line }}</small>
                                    @endforeach
                                @endif
                            </td>
                            <td>
                                @forelse($plan->billingFeatures->take(3) as $feature)
                                    <span class="badge badge-soft-info mb-1">{{ $feature->name }}</span>
                                @empty
                                    <span class="text-muted">-</span>
                                @endforelse
                                @if($plan->billingFeatures->count() > 3)
                                    <small class="d-block text-muted">+{{ $plan->billingFeatures->count() - 3 }} {{ __('admin.more') }}</small>
                                @endif
                            </td>
                            <td>
                                @if ($plan->is_active)
                                    <span class="badge badge-soft-success d-inline-flex align-items-center">
                                        {{ __('admin.active') }} <i class="isax isax-tick-circle ms-1"></i>
                                    </span>
                                @else
                                    <span class="badge badge-soft-danger d-inline-flex align-items-center">
                                        {{ __('admin.inactive') }} <i class="isax isax-close-circle ms-1"></i>
                                    </span>
                                @endif
                            </td>
                            <td>{{ $plan->subscriptions_count }}</td>
                            <td>{{ $plan->sort_order }}</td>
                            <td class="action-item">
                                <a href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <i class="isax isax-more"></i>
                                </a>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a href="{{ route('admin.plans.show', $plan) }}" class="dropdown-item d-flex align-items-center">
                                            <i class="isax isax-eye me-2"></i>{{ __('admin.view_usage') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('admin.plans.edit', $plan) }}" class="dropdown-item d-flex align-items-center">
                                            <i class="isax isax-edit me-2"></i>{{ __('admin.edit') }}
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.plans.toggle-active', $plan) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="dropdown-item d-flex align-items-center">
                                                <i class="isax isax-refresh me-2"></i>{{ $plan->is_active ? __('admin.deactivate') : __('admin.activate') }}
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" onsubmit="return confirm('{{ __('admin.delete_plan_confirm') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item d-flex align-items-center text-danger">
                                                <i class="isax isax-trash me-2"></i>{{ __('admin.delete') }}
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12">
                                <div class="text-center py-4">
                                    <p class="mb-0">{{ __('admin.no_plans_match_filters') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

        </div>

        <div class="footer d-sm-flex align-items-center justify-content-between bg-white py-2 px-4 border-top">
            <p class="text-dark mb-0">&copy; 2025 <a href="javascript:void(0);" class="link-primary">Kanakku</a>, {{ __('admin.all_rights_reserved') }}</p>
            <p class="text-dark">Version : 1.3.8</p>
        </div>
    </div>
@endsection
