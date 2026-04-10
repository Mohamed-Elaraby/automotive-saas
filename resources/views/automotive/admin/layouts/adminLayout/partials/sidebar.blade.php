<!-- Sidenav Menu Start -->
<div class="two-col-sidebar" id="two-col-sidebar">
    <div class="twocol-mini">
        <!-- Add -->
        <div class="dropdown">
            <a class="btn btn-primary bg-gradient btn-sm btn-icon rounded-circle d-flex align-items-center justify-content-center"
               data-bs-toggle="dropdown"
               href="javascript:void(0);"
               role="button"
               data-bs-display="static"
               data-bs-reference="parent">
                <i class="isax isax-add"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-start">
                <li>
                    <a href="{{ route('automotive.admin.products.create') }}" class="dropdown-item d-flex align-items-center">
                        <i class="isax isax-box-add me-2"></i>New Product
                    </a>
                </li>
                <li>
                    <a href="{{ route('automotive.admin.branches.create') }}" class="dropdown-item d-flex align-items-center">
                        <i class="isax isax-buildings me-2"></i>New Branch
                    </a>
                </li>
                <li>
                    <a href="{{ route('automotive.admin.users.create') }}" class="dropdown-item d-flex align-items-center">
                        <i class="isax isax-user-add me-2"></i>New User
                    </a>
                </li>
                <li>
                    <a href="{{ route('automotive.admin.inventory-adjustments.create') }}" class="dropdown-item d-flex align-items-center">
                        <i class="isax isax-arrows-swap me-2"></i>Inventory Adjustment
                    </a>
                </li>
                <li>
                    <a href="{{ route('automotive.admin.stock-transfers.create') }}" class="dropdown-item d-flex align-items-center">
                        <i class="isax isax-arrow-right-3 me-2"></i>Stock Transfer
                    </a>
                </li>
            </ul>
        </div>
        <!-- /Add -->

        <ul class="menu-list">
            <li>
                <a href="{{ route('automotive.admin.dashboard') }}"
                   data-bs-toggle="tooltip"
                   data-bs-placement="right"
                   data-bs-title="Dashboard">
                    <i class="isax isax-element-45"></i>
                </a>
            </li>
            <li>
                <form method="POST" action="{{ route('automotive.admin.logout') }}" style="margin:0;">
                    @csrf
                    <button type="submit"
                            class="border-0 bg-transparent p-0"
                            data-bs-toggle="tooltip"
                            data-bs-placement="right"
                            data-bs-title="Logout">
                        <i class="isax isax-login-15"></i>
                    </button>
                </form>
            </li>
        </ul>
    </div>

    <div class="sidebar" id="sidebar-two">
        <!-- Start Logo -->
        <div class="sidebar-logo">
            <a href="{{ route('automotive.admin.dashboard') }}" class="logo logo-normal">
                <img src="{{ url('theme/img/logo.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('automotive.admin.dashboard') }}" class="logo-small">
                <img src="{{ url('theme/img/logo-small.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('automotive.admin.dashboard') }}" class="dark-logo">
                <img src="{{ url('theme/img/logo-white.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('automotive.admin.dashboard') }}" class="dark-small">
                <img src="{{ url('theme/img/logo-small-white.svg') }}" alt="Logo">
            </a>

            <a id="toggle_btn" href="javascript:void(0);">
                <i class="isax isax-menu-1"></i>
            </a>
        </div>
        <!-- End Logo -->

        <!-- Search -->
        <div class="sidebar-search">
            <div class="input-icon-end position-relative">
                <input type="text" class="form-control" placeholder="Search">
                <span class="input-icon-addon">
                    <i class="isax isax-search-normal"></i>
                </span>
            </div>
        </div>
        <!-- /Search -->

        <!--- Sidenav Menu -->
        <div class="sidebar-inner" data-simplebar>
            <div id="sidebar-menu" class="sidebar-menu">
                <ul>
                    <li class="menu-title"><span>Main</span></li>
                    <li>
                        <ul>
                            <li class="{{ $page === 'dashboard' ? 'active subdrop' : '' }}">
                                <a href="{{ route('automotive.admin.dashboard') }}">
                                    <i class="isax isax-element-45"></i><span>Dashboard</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    @if(($tenantWorkspaceProducts ?? collect())->isNotEmpty())
                        <li class="menu-title"><span>Workspace Products</span></li>
                        <li>
                            <ul>
                                @foreach($tenantWorkspaceProducts as $workspaceProduct)
                                    <li>
                                        <a href="{{ route('automotive.admin.dashboard') }}">
                                            <i class="isax {{ $workspaceProduct['is_primary_workspace_product'] ? 'isax-car' : 'isax-layer' }}"></i>
                                            <span>{{ $workspaceProduct['product_name'] }}</span>
                                            <small class="ms-auto text-muted">
                                                {{ $workspaceProduct['is_accessible'] ? 'Connected' : $workspaceProduct['status_label'] }}
                                            </small>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endif

                    <li class="menu-title"><span>Manage</span></li>
                    <li>
                        <ul>
                            <li class="{{ $page === 'users' ? 'active' : '' }}">
                                <a href="{{ route('automotive.admin.users.index') }}">
                                    <i class="isax isax-profile-2user5"></i><span>Users</span>
                                </a>
                            </li>
                            <li class="{{ $page === 'branches' ? 'active' : '' }}">
                                <a href="{{ route('automotive.admin.branches.index') }}">
                                    <i class="isax isax-buildings-25"></i><span>Branches</span>
                                </a>
                            </li>
                            <li class="{{ $page === 'products' ? 'active' : '' }}">
                                <a href="{{ route('automotive.admin.products.index') }}">
                                    <i class="isax isax-box5"></i><span>Products</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-title"><span>Inventory</span></li>
                    <li>
                        <ul>
                            <li class="{{ $page === 'inventory-adjustments' ? 'active' : '' }}">
                                <a href="{{ route('automotive.admin.inventory-adjustments.index') }}">
                                    <i class="isax isax-arrow-right-3"></i><span>Inventory Adjustments</span>
                                </a>
                            </li>
                            <li class="{{ $page === 'stock-transfers' ? 'active' : '' }}">
                                <a href="{{ route('automotive.admin.stock-transfers.index') }}">
                                    <i class="isax isax-arrow-right-35"></i><span>Stock Transfers</span>
                                </a>
                            </li>
                            <li class="{{ $page === 'inventory-report' ? 'active' : '' }}">
                                <a href="{{ route('automotive.admin.inventory-report.index') }}">
                                    <i class="isax isax-chart-35"></i><span>Inventory Report</span>
                                </a>
                            </li>
                            <li class="{{ $page === 'stock-movements' ? 'active' : '' }}">
                                <a href="{{ route('automotive.admin.stock-movements.index') }}">
                                    <i class="isax isax-arrow-3"></i><span>Stock Movement Report</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-title"><span>Subscription</span></li>
                    <li>
                        <ul>
                            <li class="{{ $page === 'billing' ? 'active' : '' }}">
                                <a href="{{ route('automotive.admin.billing.status') }}">
                                    <i class="isax isax-crown5"></i><span>Plans & Billing</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>

                <div class="sidebar-footer">
                    <div class="trial-item bg-white text-center border">
                        <div class="bg-light p-3 text-center">
                            <img src="{{ url('theme/img/icons/upgrade.svg') }}" alt="img">
                        </div>
                        <div class="p-2">
                            <h6 class="fs-14 fw-semibold mb-1">Upgrade to More</h6>
                            <p class="fs-13 mb-2">Subscribe to unlock more features and limits</p>
                            <a href="javascript:void(0);" class="btn btn-sm btn-primary w-100 d-flex align-items-center justify-content-center">
                                <i class="isax isax-crown5 me-1"></i>Upgrade
                            </a>
                        </div>
                        <a href="javascript:void(0);" class="close-icon">
                            <i class="fa-solid fa-x"></i>
                        </a>
                    </div>

                    <ul class="menu-list">
                        <li>
                            <a href="{{ route('automotive.admin.dashboard') }}"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               data-bs-title="Dashboard">
                                <i class="isax isax-element-45"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('automotive.admin.inventory-report.index') }}"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               data-bs-title="Inventory Report">
                                <i class="isax isax-chart-35"></i>
                            </a>
                        </li>
                        <li>
                            <form method="POST" action="{{ route('automotive.admin.logout') }}" style="margin:0;">
                                @csrf
                                <button type="submit"
                                        class="border-0 bg-transparent p-0"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-title="Logout">
                                    <i class="isax isax-login-15"></i>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Sidenav Menu End -->
