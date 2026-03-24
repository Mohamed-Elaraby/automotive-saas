<?php
$headerUser = auth()->user();
$headerUserName = $headerUser?->name ?: 'Admin User';
$headerUserEmail = $headerUser?->email ?: 'no-email@example.com';
$headerUserInitial = strtoupper(substr((string) $headerUserName, 0, 1));
?>

<div class="header">
    <div class="main-header">

        <div class="header-left">
            <a href="{{ route('admin.dashboard') }}" class="logo">
                <img src="{{ url('theme/img/logo.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('admin.dashboard') }}" class="dark-logo">
                <img src="{{ url('theme/img/logo-white.svg') }}" alt="Logo">
            </a>
        </div>

        <a id="mobile_btn" class="mobile_btn" href="#sidebar">
            <i class="isax isax-menu-1"></i>
        </a>

        <div class="header-user">
            <ul class="nav user-menu">

                @include('admin.layouts.centralLayout.partials.topbar-notifications')

                <li class="nav-item dropdown has-arrow main-drop">
                    <a href="#" class="dropdown-toggle nav-link userset" data-bs-toggle="dropdown">
                        <span class="user-info">
                            <span class="user-letter">
                                {{ $headerUserInitial }}
                            </span>
                            <span class="user-detail">
                                <span class="user-name">{{ $headerUserName }}</span>
                                <span class="user-role">{{ $headerUserEmail }}</span>
                            </span>
                        </span>
                    </a>

                    <div class="dropdown-menu menu-drop-user dropdown-menu-end">
                        <div class="profilename">
                            <div class="profileset">
                                <span class="user-img">
                                    <span class="user-letter">
                                        {{ $headerUserInitial }}
                                    </span>
                                </span>
                                <div class="profilesets">
                                    <h6>{{ $headerUserName }}</h6>
                                    <h5>{{ $headerUserEmail }}</h5>
                                </div>
                            </div>
                            <hr class="m-0">

                            @if($headerUser)
                                <a class="dropdown-item logout pb-0" href="{{ route('logout') }}"
                                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="me-2 isax isax-logout"></i>Logout
                                </a>

                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            @endif
                        </div>
                    </div>
                </li>

            </ul>
        </div>
    </div>
</div>
