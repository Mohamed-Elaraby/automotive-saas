@php
    $tenantAdminUser = auth('automotive_admin')->user();
    $tenantAdminImpersonation = session('tenant_admin_impersonation', []);
    $isTenantAdminImpersonating = is_array($tenantAdminImpersonation) && ($tenantAdminImpersonation['active'] ?? false);
    $focusedWorkspaceProductCode = (string) request()->attributes->get('workspace_product_code', request()->query('workspace_product', 'automotive_service'));
    $focusedWorkspaceProductFamily = $focusedWorkspaceProductFamily ?? 'automotive_service';
    $workspaceQuery = $focusedWorkspaceProductCode !== '' ? ['workspace_product' => $focusedWorkspaceProductCode] : [];

    $tenantAdminRouteLabels = [
        'automotive.admin.dashboard' => 'Dashboard',
        'automotive.admin.users.*' => 'Users',
        'automotive.admin.branches.*' => 'Branches',
        'automotive.admin.products.*' => 'Stock Items',
        'automotive.admin.inventory-adjustments.*' => 'Inventory Adjustments',
        'automotive.admin.stock-transfers.*' => 'Stock Transfers',
        'automotive.admin.inventory-report.*' => 'Inventory Report',
        'automotive.admin.stock-movements.*' => 'Stock Movement Report',
        'automotive.admin.billing.*' => 'Subscription Access',
        'automotive.admin.modules.workshop-operations' => 'Workshop Operations',
        'automotive.admin.modules.supplier-catalog' => 'Supplier Catalog',
        'automotive.admin.modules.general-ledger' => 'General Ledger',
    ];

    $headerShortcut = match ($focusedWorkspaceProductFamily) {
        'parts_inventory' => [
            'route' => 'automotive.admin.inventory-report.index',
            'label' => 'Inventory Report',
            'icon' => 'isax-chart-35',
        ],
        'accounting' => [
            'route' => 'automotive.admin.modules.general-ledger',
            'label' => 'General Ledger',
            'icon' => 'isax-wallet-3',
        ],
        default => [
            'route' => 'automotive.admin.modules.workshop-operations',
            'label' => 'Workshop Operations',
            'icon' => 'isax-car',
        ],
    };

    $currentTenantAdminSection = 'Workspace';

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
                                    <i class="isax isax-home-2 me-1"></i>Tenant Admin
                                </a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $currentTenantAdminSection }}</li>
                        </ol>
                    </nav>
                </div>

                @if($isTenantAdminImpersonating)
                    <div class="alert alert-warning border-0 py-2 px-3 mb-0 me-3 d-flex align-items-center flex-wrap gap-2">
                        <div class="fw-medium">
                            Impersonation Mode:
                            <span class="fw-normal">
                                {{ $tenantAdminImpersonation['central_admin_email'] ?? 'unknown' }}
                                as
                                {{ $tenantAdminImpersonation['tenant_user_email'] ?? ($tenantAdminUser?->email ?? 'tenant user') }}
                            </span>
                        </div>

                        <form method="POST" action="{{ route('automotive.admin.stop-impersonation') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-dark">
                                Stop
                            </button>
                        </form>
                    </div>
                @endif

                <div class="d-flex align-items-center">
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
                                <i class="isax isax-element-45 me-2"></i>Dashboard
                            </a>
                            <a class="dropdown-item d-flex align-items-center" href="{{ route($headerShortcut['route'], $workspaceQuery) }}">
                                <i class="isax {{ $headerShortcut['icon'] }} me-2"></i>{{ $headerShortcut['label'] }}
                            </a>

                            <hr class="dropdown-divider my-2">

                            <form method="POST" action="{{ route('automotive.admin.logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item logout d-flex align-items-center border-0 bg-transparent w-100">
                                    <i class="isax isax-logout me-2"></i>Sign Out
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
                <i class="isax isax-element-45 me-2"></i>Dashboard
            </a>
            <a class="dropdown-item d-flex align-items-center" href="{{ route($headerShortcut['route'], $workspaceQuery) }}">
                <i class="isax {{ $headerShortcut['icon'] }} me-2"></i>{{ $headerShortcut['label'] }}
            </a>
            <form method="POST" action="{{ route('automotive.admin.logout') }}">
                @csrf
                <button type="submit" class="dropdown-item logout d-flex align-items-center border-0 bg-transparent w-100">
                    <i class="isax isax-logout me-2"></i>Sign Out
                </button>
            </form>
        </div>
    </div>
</div>
<!-- Topbar End -->
