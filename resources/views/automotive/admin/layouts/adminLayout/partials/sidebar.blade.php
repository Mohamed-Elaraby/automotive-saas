<!-- Sidenav Menu Start -->
@php($selectedWorkspaceProduct = trim((string) data_get($focusedWorkspaceProduct, 'product_code', request()->query('workspace_product'))))
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
                @forelse(($workspaceQuickCreateActions ?? []) as $action)
                    <li>
                        <a href="{{ route($action['route'], $action['params'] ?? []) }}" class="dropdown-item d-flex align-items-center">
                            <i class="isax {{ $action['icon'] }} me-2"></i>{{ $action['label'] }}
                        </a>
                    </li>
                @empty
                    <li>
                        <span class="dropdown-item-text text-muted">No quick actions available</span>
                    </li>
                @endforelse
            </ul>
        </div>
        <!-- /Add -->

        <ul class="menu-list">
            <li>
                <a href="{{ route('automotive.admin.dashboard', data_get($focusedWorkspaceProduct, 'product_code') ? ['workspace_product' => data_get($focusedWorkspaceProduct, 'product_code')] : []) }}"
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
            <a href="{{ route('automotive.admin.dashboard', data_get($focusedWorkspaceProduct, 'product_code') ? ['workspace_product' => data_get($focusedWorkspaceProduct, 'product_code')] : []) }}" class="logo logo-normal">
                <img src="{{ url('theme/img/logo.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('automotive.admin.dashboard', data_get($focusedWorkspaceProduct, 'product_code') ? ['workspace_product' => data_get($focusedWorkspaceProduct, 'product_code')] : []) }}" class="logo-small">
                <img src="{{ url('theme/img/logo-small.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('automotive.admin.dashboard', data_get($focusedWorkspaceProduct, 'product_code') ? ['workspace_product' => data_get($focusedWorkspaceProduct, 'product_code')] : []) }}" class="dark-logo">
                <img src="{{ url('theme/img/logo-white.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('automotive.admin.dashboard', data_get($focusedWorkspaceProduct, 'product_code') ? ['workspace_product' => data_get($focusedWorkspaceProduct, 'product_code')] : []) }}" class="dark-small">
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
                    @if(($tenantWorkspaceProducts ?? collect())->isNotEmpty())
                        <li class="menu-title"><span>Workspace Products</span></li>
                        <li>
                            <ul>
                                @foreach($tenantWorkspaceProducts as $workspaceProduct)
                                    <li class="{{ $selectedWorkspaceProduct !== '' && ($selectedWorkspaceProduct === (string) $workspaceProduct['product_code'] || $selectedWorkspaceProduct === (string) $workspaceProduct['product_slug']) ? 'active' : '' }}">
                                        <a href="{{ route('automotive.admin.dashboard', ['workspace_product' => $workspaceProduct['product_code']]) }}">
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

                    @foreach(($workspaceSidebarSections ?? []) as $section)
                        <li class="menu-title"><span>{{ $section['title'] }}</span></li>
                        <li>
                            <ul>
                                @foreach($section['items'] as $item)
                                    <li class="{{ in_array($page ?? '', $item['pages'] ?? [], true) ? 'active' : '' }}">
                                        <a href="{{ route($item['route'], $item['params'] ?? []) }}">
                                            <i class="isax {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>

                <div class="sidebar-footer">
                    <div class="trial-item bg-white text-center border">
                        <div class="bg-light p-3 text-center">
                            <img src="{{ url('theme/img/icons/upgrade.svg') }}" alt="img">
                        </div>
                        <div class="p-2">
                            <h6 class="fs-14 fw-semibold mb-1">Upgrade to More</h6>
                            <p class="fs-13 mb-2">Subscribe to unlock more features and limits</p>
                            <a href="{{ route('automotive.admin.billing.status', data_get($focusedWorkspaceProduct, 'product_code') ? ['workspace_product' => data_get($focusedWorkspaceProduct, 'product_code')] : []) }}" class="btn btn-sm btn-primary w-100 d-flex align-items-center justify-content-center">
                                <i class="isax isax-crown5 me-1"></i>Upgrade
                            </a>
                        </div>
                        <a href="javascript:void(0);" class="close-icon">
                            <i class="fa-solid fa-x"></i>
                        </a>
                    </div>

                    <ul class="menu-list">
                        <li>
                            <a href="{{ route('automotive.admin.dashboard', data_get($focusedWorkspaceProduct, 'product_code') ? ['workspace_product' => data_get($focusedWorkspaceProduct, 'product_code')] : []) }}"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               data-bs-title="Dashboard">
                                <i class="isax isax-element-45"></i>
                            </a>
                        </li>
                        @if(($selectedWorkspaceProduct ?? '') === 'parts_inventory')
                            <li>
                                <a href="{{ route('automotive.admin.inventory-report.index', ['workspace_product' => 'parts_inventory']) }}"
                                   data-bs-toggle="tooltip"
                                   data-bs-placement="top"
                                   data-bs-title="Inventory Report">
                                    <i class="isax isax-chart-35"></i>
                                </a>
                            </li>
                        @endif
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
