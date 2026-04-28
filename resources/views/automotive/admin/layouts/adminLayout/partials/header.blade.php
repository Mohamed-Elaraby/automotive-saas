@php
    $tenantAdminUser = auth('automotive_admin')->user();
    $tenantAdminImpersonation = session('tenant_admin_impersonation', []);
    $isTenantAdminImpersonating = is_array($tenantAdminImpersonation) && ($tenantAdminImpersonation['active'] ?? false);
    $focusedWorkspaceProductCode = (string) request()->attributes->get('workspace_product_code', request()->query('workspace_product', 'automotive_service'));
    $focusedWorkspaceProductFamily = $focusedWorkspaceProductFamily ?? 'automotive_service';
    $workspaceQuery = $focusedWorkspaceProductCode !== '' ? ['workspace_product' => $focusedWorkspaceProductCode] : [];
    $customerPortalUrl = route('automotive.portal');

    $tenantAdminRouteLabels = [
        'automotive.admin.dashboard' => __('shared.dashboard'),
        'automotive.admin.users.*' => __('shared.users'),
        'automotive.admin.branches.*' => __('shared.branches'),
        'automotive.admin.products.*' => __('shared.stock_items'),
        'automotive.admin.inventory-adjustments.*' => __('shared.inventory_adjustments'),
        'automotive.admin.stock-transfers.*' => __('shared.stock_transfers'),
        'automotive.admin.inventory-report.*' => __('shared.inventory_report'),
        'automotive.admin.stock-movements.*' => __('shared.stock_movement_report'),
        'automotive.admin.billing.*' => __('shared.subscription_access'),
        'automotive.admin.modules.workshop-operations' => __('shared.workshop_operations'),
        'automotive.admin.modules.supplier-catalog' => __('shared.supplier_catalog'),
        'automotive.admin.modules.general-ledger' => __('shared.general_ledger'),
    ];

    $headerShortcut = match ($focusedWorkspaceProductFamily) {
        'parts_inventory' => [
            'route' => 'automotive.admin.inventory-report.index',
            'label' => __('shared.inventory_report'),
            'icon' => 'isax-chart-35',
        ],
        'accounting' => [
            'route' => 'automotive.admin.modules.general-ledger',
            'label' => __('shared.general_ledger'),
            'icon' => 'isax-wallet-3',
        ],
        default => [
            'route' => 'automotive.admin.modules.workshop-operations',
            'label' => __('shared.workshop_operations'),
            'icon' => 'isax-car',
        ],
    };

    $currentTenantAdminSection = __('shared.workspace');

    foreach ($tenantAdminRouteLabels as $pattern => $label) {
        if (request()->routeIs($pattern)) {
            $currentTenantAdminSection = $label;
            break;
        }
    }
@endphp

<!-- Topbar Start -->
<div class="header">
    <div class="main-header">
        <div class="header-left">
            <a href="{{ route('automotive.admin.dashboard', $workspaceQuery) }}" class="logo">
                <img src="{{ url('theme/img/logo.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('automotive.admin.dashboard', $workspaceQuery) }}" class="dark-logo">
                <img src="{{ url('theme/img/logo-white.svg') }}" alt="Logo">
            </a>
        </div>

        <a id="mobile_btn" class="mobile_btn" href="#sidebar-two">
            <span class="bar-icon">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </a>

        <div class="header-user">
            <div class="nav user-menu nav-list flex-wrap">
                <div class="me-auto d-flex align-items-center" id="header-search">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-divide mb-0">
                            <li class="breadcrumb-item d-flex align-items-center">
                                <a href="{{ route('automotive.admin.dashboard', $workspaceQuery) }}">
                                    <i class="isax isax-home-2 me-1"></i>{{ __('shared.tenant_admin') }}
                                </a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $currentTenantAdminSection }}</li>
                        </ol>
                    </nav>
                </div>

                @if($isTenantAdminImpersonating)
                    <div class="alert alert-warning border-0 py-2 px-3 mb-0 me-3 d-flex align-items-center flex-wrap gap-2">
                        <div class="fw-medium">
                            {{ __('shared.impersonation_mode') }}:
                            <span class="fw-normal">
                                {{ $tenantAdminImpersonation['central_admin_email'] ?? 'unknown' }}
                                {{ __('shared.as') }}
                                {{ $tenantAdminImpersonation['tenant_user_email'] ?? ($tenantAdminUser?->email ?? 'tenant user') }}
                            </span>
                        </div>

                        <form method="POST" action="{{ route('automotive.admin.stop-impersonation') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-dark">
                                {{ __('shared.stop') }}
                            </button>
                        </form>
                    </div>
                @endif

                <div class="d-flex align-items-center">
                    @include('shared.partials.language-switcher')

                    <a href="{{ $customerPortalUrl }}" class="btn btn-primary me-2 d-none d-lg-inline-flex align-items-center">
                        <i class="isax isax-profile-circle me-1"></i>{{ __('shared.customer_portal') }}
                    </a>

                    <div class="me-2 theme-item">
                        <a href="javascript:void(0);" id="dark-mode-toggle" class="theme-toggle btn btn-menubar">
                            <i class="isax isax-moon"></i>
                        </a>
                        <a href="javascript:void(0);" id="light-mode-toggle" class="theme-toggle btn btn-menubar">
                            <i class="isax isax-sun-1"></i>
                        </a>
                    </div>

                    <div class="dropdown profile-dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <span class="avatar online">
                                <span class="avatar avatar-md bg-primary text-white rounded-circle">
                                    {{ strtoupper(substr((string) ($tenantAdminUser?->name ?? 'A'), 0, 1)) }}
                                </span>
                            </span>
                        </a>
                        <div class="dropdown-menu p-2">
                            <div class="d-flex align-items-center bg-light rounded-1 p-2 mb-2">
                                <span class="avatar avatar-lg me-2">
                                    <span class="avatar avatar-lg bg-primary text-white rounded-circle">
                                        {{ strtoupper(substr((string) ($tenantAdminUser?->name ?? 'A'), 0, 1)) }}
                                    </span>
                                </span>
                                <div>
                                    <h6 class="fs-14 fw-medium mb-1">{{ $tenantAdminUser?->name ?? 'Admin User' }}</h6>
                                    <p class="fs-13">{{ $tenantAdminUser?->email ?? 'Administrator' }}</p>
                                </div>
                            </div>

                            <a class="dropdown-item d-flex align-items-center" href="{{ route('automotive.admin.dashboard', $workspaceQuery) }}">
                                <i class="isax isax-element-45 me-2"></i>{{ __('shared.dashboard') }}
                            </a>
                            <a class="dropdown-item d-flex align-items-center" href="{{ route($headerShortcut['route'], $workspaceQuery) }}">
                                <i class="isax {{ $headerShortcut['icon'] }} me-2"></i>{{ $headerShortcut['label'] }}
                            </a>
                            <a class="dropdown-item d-flex align-items-center" href="{{ $customerPortalUrl }}">
                                <i class="isax isax-profile-circle me-2"></i>{{ __('shared.customer_portal') }}
                            </a>

                            <hr class="dropdown-divider my-2">

                            <form method="POST" action="{{ route('automotive.admin.logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item logout d-flex align-items-center border-0 bg-transparent w-100">
                                    <i class="isax isax-logout me-2"></i>{{ __('shared.sign_out') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dropdown mobile-user-menu profile-dropdown">
        <a href="javascript:void(0);" class="dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown" data-bs-auto-close="outside">
            <span class="avatar avatar-md online">
                <span class="avatar avatar-md bg-primary text-white rounded-circle">
                    {{ strtoupper(substr((string) ($tenantAdminUser?->name ?? 'A'), 0, 1)) }}
                </span>
            </span>
        </a>
        <div class="dropdown-menu p-2 mt-0">
            <a class="dropdown-item d-flex align-items-center" href="{{ route('automotive.admin.dashboard', $workspaceQuery) }}">
                <i class="isax isax-element-45 me-2"></i>{{ __('shared.dashboard') }}
            </a>
            <a class="dropdown-item d-flex align-items-center" href="{{ route($headerShortcut['route'], $workspaceQuery) }}">
                <i class="isax {{ $headerShortcut['icon'] }} me-2"></i>{{ $headerShortcut['label'] }}
            </a>
            <a class="dropdown-item d-flex align-items-center" href="{{ $customerPortalUrl }}">
                <i class="isax isax-profile-circle me-2"></i>{{ __('shared.customer_portal') }}
            </a>
            <form method="POST" action="{{ route('automotive.admin.logout') }}">
                @csrf
                <button type="submit" class="dropdown-item logout d-flex align-items-center border-0 bg-transparent w-100">
                    <i class="isax isax-logout me-2"></i>{{ __('shared.sign_out') }}
                </button>
            </form>
        </div>
    </div>
</div>
<!-- Topbar End -->
