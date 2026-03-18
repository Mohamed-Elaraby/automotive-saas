<!-- Sidenav Menu Start -->
<div class="two-col-sidebar" id="two-col-sidebar">
    <div class="twocol-mini">
        <ul class="menu-list">
            <li>
                <a href="{{ route('admin.dashboard') }}"
                   data-bs-toggle="tooltip"
                   data-bs-placement="right"
                   data-bs-title="Dashboard">
                    <i class="isax isax-element-45"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.plans.index') }}"
                   data-bs-toggle="tooltip"
                   data-bs-placement="right"
                   data-bs-title="Plans">
                    <i class="isax isax-crown5"></i>
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar" id="sidebar-two">
        <!-- Start Logo -->
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
                            <li class="{{ ($page ?? '') === 'admin-dashboard' ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.dashboard') }}">
                                    <i class="isax isax-element-45"></i><span>Dashboard</span>
                                </a>
                            </li>
                            <li class="{{ in_array(($page ?? ''), ['membership-plans', 'plan-create', 'plan-edit']) ? 'active subdrop' : '' }}">
                                <a href="{{ route('admin.plans.index') }}">
                                    <i class="isax isax-crown5"></i><span>Plans</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>

                <div class="sidebar-footer">
                    <ul class="menu-list">
                        <li>
                            <a href="{{ route('admin.dashboard') }}"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               data-bs-title="Dashboard">
                                <i class="isax isax-element-45"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.plans.index') }}"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               data-bs-title="Plans">
                                <i class="isax isax-crown5"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Sidenav Menu End -->
