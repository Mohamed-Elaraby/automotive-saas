<?php
$page = $page ?? '';

$systemErrorUnreadCount = 0;

try {
    $systemErrorConnection = (string) (config('tenancy.database.central_connection') ?? config('database.default'));

    if (\Illuminate\Support\Facades\Schema::connection($systemErrorConnection)->hasTable('system_error_logs')) {
        $systemErrorUnreadCount = \App\Models\SystemErrorLog::query()
            ->where('is_read', false)
            ->count();
    }
} catch (\Throwable $e) {
    $systemErrorUnreadCount = 0;
}
?>

<div class="two-col-sidebar" id="two-col-sidebar">
    <div class="twocol-mini">
        <ul class="menu-list">
            <li>
                <a href="{{ route('admin.dashboard') }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ __('admin.dashboard') }}">
                    <i class="isax isax-element-45"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.products.index') }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ __('admin.products') }}">
                    <i class="isax isax-box"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.plans.index') }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ __('admin.plans') }}">
                    <i class="isax isax-crown5"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.tenants.index') }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ __('admin.tenants') }}">
                    <i class="isax isax-buildings-2"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.subscriptions.index') }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ __('admin.subscriptions') }}">
                    <i class="isax isax-receipt-2"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.coupons.index') }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ __('admin.coupons') }}">
                    <i class="isax isax-ticket-discount"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.reports.billing') }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ __('admin.billing_reports') }}">
                    <i class="isax isax-chart-21"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.settings.general.edit') }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ __('admin.general_settings') }}">
                    <i class="isax isax-setting-2"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.reference-data.currencies.index') }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ __('admin.reference_data') }}">
                    <i class="isax isax-location"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.notifications.index') }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ __('admin.notifications') }}">
                    <i class="isax isax-notification-bing"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.product-enablement-requests.index') }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ __('admin.product_enablement_requests') }}">
                    <i class="isax isax-box-add"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.activity-logs.index') }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ __('admin.activity_logs') }}">
                    <i class="isax isax-document-text"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.system-errors.index') }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="{{ __('admin.system_errors') }}">
                    <i class="isax isax-warning-2"></i>
                </a>
                </li>
        </ul>
    </div>

    <div class="sidebar" id="sidebar-two">
        <div class="sidebar-logo">
            <a href="{{ route('admin.dashboard') }}" class="logo logo-normal">
                <img src="{{ url('theme/img/logo.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('admin.dashboard') }}" class="logo-small">
                <img src="{{ url('theme/img/logo-small.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('admin.dashboard') }}" class="dark-logo">
                <img src="{{ url('theme/img/logo-white.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('admin.dashboard') }}" class="dark-small">
                <img src="{{ url('theme/img/logo-small-white.svg') }}" alt="Logo">
            </a>

            <a id="toggle_btn" href="javascript:void(0);">
                <i class="isax isax-menu-1"></i>
            </a>
        </div>

        <div class="sidebar-search">
            <div class="input-icon-end position-relative">
                <input type="text" class="form-control" placeholder="{{ __('admin.search') }}">
                <span class="input-icon-addon">
                    <i class="isax isax-search-normal"></i>
                </span>
            </div>
        </div>

        <div class="sidebar-inner" data-simplebar>
            <div id="sidebar-menu" class="sidebar-menu">
                <ul>
                    <li class="menu-title"><span>{{ __('admin.main') }}</span></li>
                    <li>
                        <ul>
                            <li class="{{ $page === 'admin-dashboard' ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.dashboard') }}">
                                    <i class="isax isax-element-45"></i>
                                    <span>{{ __('admin.dashboard') }}</span>
                                </a>
                            </li>

                            <li class="{{ in_array($page, ['membership-plans', 'plan-create', 'plan-edit'], true) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.plans.index') }}">
                                    <i class="isax isax-crown5"></i>
                                    <span>{{ __('admin.plans') }}</span>
                                </a>
                            </li>

                            <li class="{{ in_array($page, ['products-index', 'products-create', 'products-edit'], true) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.products.index') }}">
                                    <i class="isax isax-box"></i>
                                    <span>{{ __('admin.products') }}</span>
                                </a>
                            </li>

                            <li class="{{ in_array($page, ['tenants-index', 'tenants-show'], true) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.tenants.index') }}">
                                    <i class="isax isax-buildings-2"></i>
                                    <span>{{ __('admin.tenants') }}</span>
                                </a>
                            </li>

                            <li class="{{ in_array($page, ['subscriptions-index', 'subscription-show'], true) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.subscriptions.index') }}">
                                    <i class="isax isax-receipt-2"></i>
                                    <span>{{ __('admin.subscriptions') }}</span>
                                </a>
                            </li>

                            <li class="{{ in_array($page, ['coupons-index', 'coupons-create', 'coupons-show', 'coupons-edit'], true) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.coupons.index') }}">
                                    <i class="isax isax-ticket-discount"></i>
                                    <span>{{ __('admin.coupons') }}</span>
                                </a>
                            </li>

                            <li class="{{ $page === 'billing-reports' ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.reports.billing') }}">
                                    <i class="isax isax-chart-21"></i>
                                    <span>{{ __('admin.billing_reports') }}</span>
                                </a>
                            </li>

                            <li class="{{ in_array($page, ['saas-settings-general'], true) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.settings.general.edit') }}">
                                    <i class="isax isax-setting-2"></i>
                                    <span>{{ __('admin.general_settings') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-title"><span>{{ __('admin.reference_data') }}</span></li>
                    <li>
                        <ul>
                            <li class="{{ in_array($page, ['reference-currencies-index', 'reference-currencies-create', 'reference-currencies-edit'], true) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.reference-data.currencies.index') }}">
                                    <i class="isax isax-money-4"></i>
                                    <span>{{ __('admin.currencies') }}</span>
                                </a>
                            </li>

                            <li class="{{ in_array($page, ['reference-countries-index', 'reference-countries-create', 'reference-countries-edit'], true) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.reference-data.countries.index') }}">
                                    <i class="isax isax-global"></i>
                                    <span>{{ __('admin.countries') }}</span>
                                </a>
                            </li>

                            <li class="{{ in_array($page, ['reference-states-index', 'reference-states-create', 'reference-states-edit'], true) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.reference-data.states.index') }}">
                                    <i class="isax isax-map"></i>
                                    <span>{{ __('admin.states') }}</span>
                                </a>
                            </li>

                            <li class="{{ in_array($page, ['reference-cities-index', 'reference-cities-create', 'reference-cities-edit'], true) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.reference-data.cities.index') }}">
                                    <i class="isax isax-building-3"></i>
                                    <span>{{ __('admin.cities') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-title"><span>{{ __('admin.monitoring') }}</span></li>
                    <li>
                        <ul>
                            <li class="{{ in_array($page, ['admin-notifications-index', 'admin-notifications-show'], true) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.notifications.index') }}">
                                    <i class="isax isax-notification-bing"></i>
                                    <span>{{ __('admin.notifications') }}</span>
                                </a>
                            </li>

                            <li class="{{ $page === 'product-enablement-requests-index' ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.product-enablement-requests.index') }}">
                                    <i class="isax isax-box-add"></i>
                                    <span>{{ __('admin.product_enablement') }}</span>
                                </a>
                            </li>

                            <li class="{{ in_array($page, ['activity-logs-index', 'activity-logs-show'], true) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.activity-logs.index') }}">
                                    <i class="isax isax-document-text"></i>
                                    <span>{{ __('admin.activity_logs') }}</span>
                                </a>
                            </li>

                            <li class="{{ in_array($page, ['system-errors-index', 'system-errors-show'], true) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.system-errors.index') }}">
                                    <i class="isax isax-warning-2"></i>
                                    <span>{{ __('admin.system_errors') }}</span>
                                    @if($systemErrorUnreadCount > 0)
                                        <span class="badge bg-danger ms-2">{{ $systemErrorUnreadCount }}</span>
                                    @endif
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>

                <div class="sidebar-footer">
                    <ul class="menu-list">
                        <li>
                            <a href="{{ route('admin.dashboard') }}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ __('admin.dashboard') }}">
                                <i class="isax isax-element-45"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.plans.index') }}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ __('admin.plans') }}">
                                <i class="isax isax-crown5"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.tenants.index') }}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ __('admin.tenants') }}">
                                <i class="isax isax-buildings-2"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.subscriptions.index') }}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ __('admin.subscriptions') }}">
                                <i class="isax isax-receipt-2"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.coupons.index') }}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ __('admin.coupons') }}">
                                <i class="isax isax-ticket-discount"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.reports.billing') }}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ __('admin.billing_reports') }}">
                                <i class="isax isax-chart-21"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.settings.general.edit') }}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ __('admin.general_settings') }}">
                                <i class="isax isax-setting-2"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.reference-data.currencies.index') }}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ __('admin.reference_data') }}">
                                <i class="isax isax-location"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.notifications.index') }}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ __('admin.notifications') }}">
                                <i class="isax isax-notification-bing"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.activity-logs.index') }}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ __('admin.activity_logs') }}">
                                <i class="isax isax-document-text"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.system-errors.index') }}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ __('admin.system_errors') }}">
                                <i class="isax isax-warning-2"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
