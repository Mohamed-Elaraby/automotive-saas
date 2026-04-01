@php
    $tenantAdminUser = auth('automotive_admin')->user();

    $tenantAdminRouteLabels = [
        'automotive.admin.dashboard' => 'Dashboard',
        'automotive.admin.users.*' => 'Users',
        'automotive.admin.branches.*' => 'Branches',
        'automotive.admin.products.*' => 'Products',
        'automotive.admin.inventory-adjustments.*' => 'Inventory Adjustments',
        'automotive.admin.stock-transfers.*' => 'Stock Transfers',
        'automotive.admin.inventory-report.*' => 'Inventory Report',
        'automotive.admin.stock-movements.*' => 'Stock Movements',
        'automotive.admin.billing.*' => 'Plans & Billing',
    ];

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
            <a href="{{ route('automotive.admin.dashboard') }}" class="logo">
                <img src="{{ url('theme/img/logo.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('automotive.admin.dashboard') }}" class="dark-logo">
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
            <div class="nav user-menu nav-list">
                <div class="me-auto d-flex align-items-center" id="header-search">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-divide mb-0">
                            <li class="breadcrumb-item d-flex align-items-center">
                                <a href="{{ route('automotive.admin.dashboard') }}">
                                    <i class="isax isax-home-2 me-1"></i>Tenant Admin
                                </a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $currentTenantAdminSection }}</li>
                        </ol>
                    </nav>
                </div>

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

                            <a class="dropdown-item d-flex align-items-center" href="{{ route('automotive.admin.dashboard') }}">
                                <i class="isax isax-element-45 me-2"></i>Dashboard
                            </a>
                            <a class="dropdown-item d-flex align-items-center" href="{{ route('automotive.admin.billing.status') }}">
                                <i class="isax isax-crown5 me-2"></i>Plans & Billing
                            </a>
                            <a class="dropdown-item d-flex align-items-center" href="{{ route('automotive.admin.inventory-report.index') }}">
                                <i class="isax isax-chart-35 me-2"></i>Inventory Report
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
            <a class="dropdown-item d-flex align-items-center" href="{{ route('automotive.admin.dashboard') }}">
                <i class="isax isax-element-45 me-2"></i>Dashboard
            </a>
            <a class="dropdown-item d-flex align-items-center" href="{{ route('automotive.admin.billing.status') }}">
                <i class="isax isax-crown5 me-2"></i>Plans & Billing
            </a>
            <a class="dropdown-item d-flex align-items-center" href="{{ route('automotive.admin.inventory-report.index') }}">
                <i class="isax isax-chart-35 me-2"></i>Inventory Report
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
