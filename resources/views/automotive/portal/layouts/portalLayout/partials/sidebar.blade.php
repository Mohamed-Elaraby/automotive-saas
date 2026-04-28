<!-- Sidenav Menu Start -->
@php
    $portalActiveNav = $portalActiveNav ?? (request()->routeIs('automotive.portal.settings') ? 'settings' : 'overview');
    $portalPlansTarget = !empty($hasExplicitProductSelection)
        ? route('automotive.portal', ['product' => $selectedProduct['slug'] ?? request()->query('product')]) . '#paid-plans'
        : route('automotive.portal') . '#products-catalog';
    $portalPlansLabel = !empty($hasExplicitProductSelection) ? __('portal.plans_billing') : __('shared.choose_product');
@endphp
<div class="two-col-sidebar" id="two-col-sidebar">
    <div class="twocol-mini">
        <div class="dropdown">
            <a class="btn btn-primary bg-gradient btn-sm btn-icon rounded-circle d-flex align-items-center justify-content-center"
               href="{{ route('automotive.portal') }}"
               data-bs-toggle="tooltip"
               data-bs-placement="right"
               data-bs-title="{{ __('shared.portal_overview') }}">
                <i class="isax isax-home-2"></i>
            </a>
        </div>

        <ul class="menu-list">
            <li>
                <a href="{{ route('automotive.portal') }}"
                   data-bs-toggle="tooltip"
                   data-bs-placement="right"
                   data-bs-title="{{ __('shared.portal_overview') }}">
                    <i class="isax isax-home-2"></i>
                </a>
            </li>
            <li>
                <a href="{{ route('automotive.portal.settings') }}"
                   data-bs-toggle="tooltip"
                   data-bs-placement="right"
                   data-bs-title="{{ __('shared.account_settings') }}">
                    <i class="isax isax-setting-2"></i>
                </a>
            </li>
            <li>
                <a href="{{ $portalPlansTarget }}"
                   data-bs-toggle="tooltip"
                   data-bs-placement="right"
                   data-bs-title="{{ $portalPlansLabel }}">
                    <i class="isax isax-crown"></i>
                </a>
            </li>
            @if(!empty($systemUrl) && $allowSystemAccess)
                <li>
                    <a href="{{ $systemUrl }}"
                       target="_blank"
                       data-bs-toggle="tooltip"
                       data-bs-placement="right"
                       data-bs-title="{{ __('shared.open_my_system') }}">
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
                            data-bs-title="{{ __('shared.sign_out') }}">
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
                <input type="text" class="form-control" placeholder="{{ __('shared.search') }}">
                <span class="input-icon-addon">
                    <i class="isax isax-search-normal"></i>
                </span>
            </div>
        </div>

        <div class="sidebar-inner" data-simplebar>
            <div id="sidebar-menu" class="sidebar-menu">
                <ul>
                    <li class="menu-title"><span>{{ __('shared.customer_portal') }}</span></li>
                    <li>
                        <ul>
                            <li class="{{ request()->routeIs('automotive.portal') ? 'active' : '' }}">
                                <a href="{{ route('automotive.portal') }}">
                                    <i class="isax isax-home-2"></i><span>{{ __('shared.portal_overview') }}</span>
                                </a>
                            </li>
                            <li class="{{ $portalActiveNav === 'settings' ? 'active' : '' }}">
                                <a href="{{ route('automotive.portal.settings') }}">
                                    <i class="isax isax-setting-2"></i><span>{{ __('shared.account_settings') }}</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ $portalPlansTarget }}">
                                    <i class="isax isax-crown"></i><span>{{ $portalPlansLabel }}</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ $portalPlansTarget }}">
                                    <i class="isax isax-card"></i><span>{{ !empty($hasExplicitProductSelection) ? __('portal.checkout_options') : __('portal.browse_products') }}</span>
                                </a>
                            </li>
                            @if(!empty($systemUrl) && $allowSystemAccess)
                                <li>
                                    <a href="{{ $systemUrl }}" target="_blank">
                                        <i class="isax isax-export-1"></i><span>{{ __('shared.open_my_system') }}</span>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>

                    <li class="menu-title"><span>{{ __('portal.account') }}</span></li>
                    <li>
                        <ul>
                            <li>
                                <a href="javascript:void(0);">
                                    <i class="isax isax-profile-circle"></i><span>{{ $user->name ?? __('portal.portal_user') }}</span>
                                </a>
                            </li>
                            <li>
                                <form method="POST" action="{{ route('automotive.logout') }}" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="btn btn-link text-start text-decoration-none w-100 d-flex align-items-center px-3 py-2 border-0 bg-transparent">
                                        <i class="isax isax-logout me-2"></i><span>{{ __('shared.sign_out') }}</span>
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
                            <h6 class="fs-14 fw-semibold mb-1">{{ __('portal.next_step') }}</h6>
                            <p class="fs-13 mb-2">{{ !empty($hasExplicitProductSelection) ? __('portal.next_step_plan') : __('portal.next_step_choose_product') }}</p>
                            <a href="{{ $portalPlansTarget }}" class="btn btn-sm btn-primary w-100 d-flex align-items-center justify-content-center">
                                <i class="isax isax-arrow-right-3 me-1"></i>{{ !empty($hasExplicitProductSelection) ? __('portal.continue') : __('shared.choose_product') }}
                            </a>
                        </div>
                    </div>

                    <ul class="menu-list">
                        <li>
                            <a href="{{ route('automotive.portal') }}"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               data-bs-title="{{ __('shared.portal_overview') }}">
                                <i class="isax isax-home-2"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('automotive.portal.settings') }}"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               data-bs-title="{{ __('shared.account_settings') }}">
                                <i class="isax isax-setting-2"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ $portalPlansTarget }}"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               data-bs-title="{{ $portalPlansLabel }}">
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
                                        data-bs-title="{{ __('shared.sign_out') }}">
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
