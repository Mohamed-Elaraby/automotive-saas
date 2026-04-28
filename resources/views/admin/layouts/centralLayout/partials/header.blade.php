<!-- Topbar Start -->
<div class="header">
    <div class="main-header">

        <!-- Logo -->
        <div class="header-left">
            <a href="{{ route('admin.dashboard') }}" class="logo">
                <img src="{{ url('theme/img/logo.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('admin.dashboard') }}" class="dark-logo">
                <img src="{{ url('theme/img/logo-white.svg') }}" alt="Logo">
            </a>
        </div>

        <!-- Sidebar Menu Toggle Button -->
        <a id="mobile_btn" class="mobile_btn" href="#sidebar">
            <span class="bar-icon">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </a>

        <div class="header-user">
            <div class="nav user-menu nav-list">
                <div class="me-auto d-flex align-items-center" id="header-search">

                    <!-- Add -->
                    <div class="dropdown me-3">
                        <a class="btn btn-primary bg-gradient btn-xs btn-icon rounded-circle d-flex align-items-center justify-content-center"
                           data-bs-toggle="dropdown"
                           href="javascript:void(0);"
                           role="button">
                            <i class="isax isax-add text-white"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-start p-2">
                            <li>
                                <a href="{{ route('admin.plans.create') }}" class="dropdown-item d-flex align-items-center">
                                    <i class="isax isax-add-circle me-2"></i>{{ __('shared.new_plan') }}
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('admin.plans.index') }}" class="dropdown-item d-flex align-items-center">
                                    <i class="isax isax-transaction-minus me-2"></i>{{ __('shared.all_plans') }}
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-divide mb-0">
                            @if(Route::is('admin.dashboard'))
                                <li class="breadcrumb-item d-flex align-items-center">
                                    <a href="{{ route('admin.dashboard') }}">
                                        <i class="isax isax-home-2 me-1"></i>{{ __('shared.home') }}
                                    </a>
                                </li>
                                <li class="breadcrumb-item active" aria-current="page">{{ __('shared.dashboard') }}</li>
                            @endif

                            @if(Route::is('admin.plans.index'))
                                <li class="breadcrumb-item d-flex align-items-center">
                                    <a href="{{ route('admin.dashboard') }}">
                                        <i class="isax isax-home-2 me-1"></i>{{ __('shared.home') }}
                                    </a>
                                </li>
                                <li class="breadcrumb-item active" aria-current="page">{{ __('shared.plans') }}</li>
                            @endif

                            @if(Route::is('admin.plans.create'))
                                <li class="breadcrumb-item d-flex align-items-center">
                                    <a href="{{ route('admin.dashboard') }}">
                                        <i class="isax isax-home-2 me-1"></i>{{ __('shared.home') }}
                                    </a>
                                </li>
                                <li class="breadcrumb-item d-flex align-items-center">
                                    <a href="{{ route('admin.plans.index') }}">{{ __('shared.plans') }}</a>
                                </li>
                                <li class="breadcrumb-item active" aria-current="page">{{ __('shared.create') }}</li>
                            @endif

                            @if(Route::is('admin.plans.edit'))
                                <li class="breadcrumb-item d-flex align-items-center">
                                    <a href="{{ route('admin.dashboard') }}">
                                        <i class="isax isax-home-2 me-1"></i>{{ __('shared.home') }}
                                    </a>
                                </li>
                                <li class="breadcrumb-item d-flex align-items-center">
                                    <a href="{{ route('admin.plans.index') }}">{{ __('shared.plans') }}</a>
                                </li>
                                <li class="breadcrumb-item active" aria-current="page">{{ __('shared.edit') }}</li>
                            @endif
                        </ol>
                    </nav>
                </div>

                <div class="d-flex align-items-center">

                    <!-- Search -->
                    <div class="input-icon-end position-relative me-2">
                        <input type="text" class="form-control" placeholder="{{ __('shared.search') }}">
                        <span class="input-icon-addon">
                            <i class="isax isax-search-normal"></i>
                        </span>
                    </div>
                    <!-- /Search -->

                    @include('shared.partials.language-switcher')

                    <!-- Notification -->
                @include('admin.layouts.centralLayout.partials.topbar-notifications')

                <!-- Light/Dark Mode Button -->
                    <div class="me-2 theme-item">
                        <a href="javascript:void(0);" id="dark-mode-toggle" class="theme-toggle btn btn-menubar">
                            <i class="isax isax-moon"></i>
                        </a>
                        <a href="javascript:void(0);" id="light-mode-toggle" class="theme-toggle btn btn-menubar">
                            <i class="isax isax-sun-1"></i>
                        </a>
                    </div>

                    <!-- User Dropdown -->
                    <div class="dropdown profile-dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <span class="avatar online">
                                <img src="{{ url('theme/img/profiles/avatar-01.jpg') }}" alt="Img" class="img-fluid rounded-circle">
                            </span>
                        </a>
                        <div class="dropdown-menu p-2">
                            <div class="d-flex align-items-center bg-light rounded-1 p-2 mb-2">
                                <span class="avatar avatar-lg me-2">
                                    <img src="{{ url('theme/img/profiles/avatar-01.jpg') }}" alt="img" class="rounded-circle">
                                </span>
                                <div>
                                    <h6 class="fs-14 fw-medium mb-1">{{ auth('admin')->user()?->name ?? 'Admin User' }}</h6>
                                    <p class="fs-13">{{ auth('admin')->user()?->email ?? 'Administrator' }}</p>
                                </div>
                            </div>

                            <a class="dropdown-item d-flex align-items-center" href="javascript:void(0);">
                                <i class="isax isax-profile-circle me-2"></i>{{ __('shared.profile_settings') }}
                            </a>

                            <a class="dropdown-item d-flex align-items-center" href="{{ route('admin.plans.index') }}">
                                <i class="isax isax-document-text me-2"></i>{{ __('shared.plans') }}
                            </a>

                            <div class="form-check form-switch form-check-reverse d-flex align-items-center justify-content-between dropdown-item mb-0">
                                <label class="form-check-label" for="notify"><i class="isax isax-notification me-2"></i>{{ __('shared.notifications') }}</label>
                                <input class="form-check-input" type="checkbox" role="switch" id="notify">
                            </div>

                            <hr class="dropdown-divider my-2">

                            @if(auth('admin')->check())
                                <a class="dropdown-item logout d-flex align-items-center" href="{{ route('admin.logout') }}"
                                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="isax isax-logout me-2"></i>{{ __('shared.sign_out') }}
                                </a>

                                <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            @else
                                <a class="dropdown-item logout d-flex align-items-center" href="{{ route('admin.login') }}">
                                    <i class="isax isax-logout me-2"></i>{{ __('shared.sign_in') }}
                                </a>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div class="dropdown mobile-user-menu profile-dropdown">
        <a href="javascript:void(0);" class="dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown" data-bs-auto-close="outside">
            <span class="avatar avatar-md online">
                <img src="{{ url('theme/img/profiles/avatar-01.jpg') }}" alt="Img" class="img-fluid rounded-circle">
            </span>
        </a>
        <div class="dropdown-menu p-2 mt-0">
            <a class="dropdown-item d-flex align-items-center" href="javascript:void(0);">
                <i class="isax isax-profile-circle me-2"></i>Profile Settings
            </a>
            <a class="dropdown-item d-flex align-items-center" href="{{ route('admin.plans.index') }}">
                <i class="isax isax-document-text me-2"></i>{{ __('shared.plans') }}
            </a>

            @if(auth('admin')->check())
                <a class="dropdown-item logout d-flex align-items-center" href="{{ route('admin.logout') }}"
                   onclick="event.preventDefault(); document.getElementById('mobile-logout-form').submit();">
                    <i class="isax isax-logout me-2"></i>{{ __('shared.sign_out') }}
                </a>

                <form id="mobile-logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">
                    @csrf
                </form>
            @else
                <a class="dropdown-item logout d-flex align-items-center" href="{{ route('admin.login') }}">
                    <i class="isax isax-logout me-2"></i>{{ __('shared.sign_in') }}
                </a>
            @endif
        </div>
    </div>
    <!-- /Mobile Menu -->

</div>
<!-- Topbar End -->
