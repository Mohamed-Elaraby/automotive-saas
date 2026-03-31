<!-- Sidenav Menu Start -->
<div class="two-col-sidebar" id="two-col-sidebar">
    <div class="twocol-mini">
        <div class="dropdown">
            <a class="btn btn-primary bg-gradient btn-sm btn-icon rounded-circle d-flex align-items-center justify-content-center"
               href="{{ route('automotive.portal') }}"
               data-bs-toggle="tooltip"
               data-bs-placement="right"
               data-bs-title="Portal Overview">
                <i class="isax isax-home-2"></i>
            </a>
        </div>

        <ul class="menu-list">
            <li>
                <a href="{{ route('automotive.portal') }}"
                   data-bs-toggle="tooltip"
                   data-bs-placement="right"
                   data-bs-title="Overview">
                    <i class="isax isax-home-2"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('automotive.portal') }}#paid-plans"
                   data-bs-toggle="tooltip"
                   data-bs-placement="right"
                   data-bs-title="Plans & Billing">
                    <i class="isax isax-crown"></i>
                </a>
            </li>
            @if(!empty($systemUrl) && $allowSystemAccess)
                <li>
                    <a href="{{ $systemUrl }}"
                       target="_blank"
                       data-bs-toggle="tooltip"
                       data-bs-placement="right"
                       data-bs-title="Open My System">
                        <i class="isax isax-export-1"></i>
                    </a>
                </li>
            @endif
            <li>
                <form method="POST" action="{{ route('automotive.logout') }}" style="margin:0;">
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
        <div class="sidebar-logo">
            <a href="{{ route('automotive.portal') }}" class="logo logo-normal">
                <img src="{{ asset('theme/img/logo.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('automotive.portal') }}" class="logo-small">
                <img src="{{ asset('theme/img/logo-small.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('automotive.portal') }}" class="dark-logo">
                <img src="{{ asset('theme/img/logo-white.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('automotive.portal') }}" class="dark-small">
                <img src="{{ asset('theme/img/logo-small-white.svg') }}" alt="Logo">
            </a>

            <a id="toggle_btn" href="javascript:void(0);">
                <i class="isax isax-menu-1"></i>
            </a>
        </div>

        <div class="sidebar-search">
            <div class="input-icon-end position-relative">
                <input type="text" class="form-control" placeholder="Search">
                <span class="input-icon-addon">
                    <i class="isax isax-search-normal"></i>
                </span>
            </div>
        </div>

        <div class="sidebar-inner" data-simplebar>
            <div id="sidebar-menu" class="sidebar-menu">
                <ul>
                    <li class="menu-title"><span>Customer Portal</span></li>
                    <li>
                        <ul>
                            <li class="{{ request()->routeIs('automotive.portal') ? 'active' : '' }}">
                                <a href="{{ route('automotive.portal') }}">
                                    <i class="isax isax-home-2"></i><span>Overview</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('automotive.portal') }}#paid-plans">
                                    <i class="isax isax-crown"></i><span>Plans & Billing</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('automotive.portal') }}#paid-plans">
                                    <i class="isax isax-card"></i><span>Checkout Options</span>
                                </a>
                            </li>
                            @if(!empty($systemUrl) && $allowSystemAccess)
                                <li>
                                    <a href="{{ $systemUrl }}" target="_blank">
                                        <i class="isax isax-export-1"></i><span>Open My System</span>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>

                    <li class="menu-title"><span>Account</span></li>
                    <li>
                        <ul>
                            <li>
                                <a href="javascript:void(0);">
                                    <i class="isax isax-profile-circle"></i><span>{{ $user->name ?? 'Portal User' }}</span>
                                </a>
                            </li>
                            <li>
                                <form method="POST" action="{{ route('automotive.logout') }}" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="btn btn-link text-start text-decoration-none w-100 d-flex align-items-center px-3 py-2 border-0 bg-transparent">
                                        <i class="isax isax-logout me-2"></i><span>Sign Out</span>
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>

                <div class="sidebar-footer">
                    <div class="trial-item bg-white text-center border">
                        <div class="bg-light p-3 text-center">
                            <img src="{{ asset('theme/img/icons/upgrade.svg') }}" alt="img">
                        </div>
                        <div class="p-2">
                            <h6 class="fs-14 fw-semibold mb-1">Next Step</h6>
                            <p class="fs-13 mb-2">Start your trial or pick a paid plan from the portal.</p>
                            <a href="{{ route('automotive.portal') }}#paid-plans" class="btn btn-sm btn-primary w-100 d-flex align-items-center justify-content-center">
                                <i class="isax isax-arrow-right-3 me-1"></i>Continue
                            </a>
                        </div>
                    </div>

                    <ul class="menu-list">
                        <li>
                            <a href="{{ route('automotive.portal') }}"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               data-bs-title="Overview">
                                <i class="isax isax-home-2"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('automotive.portal') }}#paid-plans"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               data-bs-title="Plans & Billing">
                                <i class="isax isax-crown"></i>
                            </a>
                        </li>
                        <li>
                            <form method="POST" action="{{ route('automotive.logout') }}" style="margin:0;">
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
