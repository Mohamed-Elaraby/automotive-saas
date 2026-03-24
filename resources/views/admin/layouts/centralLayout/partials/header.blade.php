<?php
use App\Http\Controllers\Admin\SystemErrorLogController;

$topbarSystemErrors = SystemErrorLogController::topbarData();
$topbarSystemErrorCount = (int) ($topbarSystemErrors['count'] ?? 0);
$topbarSystemErrorItems = $topbarSystemErrors['items'] ?? collect();

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

                <li class="nav-item dropdown notification-nav">
                    <a href="#" class="dropdown-toggle nav-link position-relative" data-bs-toggle="dropdown">
                        <i class="isax isax-notification-bing"></i>
                        @if($topbarSystemErrorCount > 0)
                            <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle">
                                {{ $topbarSystemErrorCount > 99 ? '99+' : $topbarSystemErrorCount }}
                            </span>
                        @endif
                    </a>

                    <div class="dropdown-menu notifications dropdown-menu-end">
                        <div class="topnav-dropdown-header d-flex justify-content-between align-items-center">
                            <span class="notification-title">System Errors</span>
                            <a href="{{ route('admin.system-errors.index') }}" class="text-decoration-none small">
                                View All
                            </a>
                        </div>

                        <div class="noti-content">
                            <ul class="notification-list">
                                @forelse($topbarSystemErrorItems as $errorItem)
                                    <li class="notification-message">
                                        <div class="d-flex flex-column gap-2 p-2">
                                            <a href="{{ route('admin.system-errors.show', $errorItem->id) }}" class="text-decoration-none">
                                                <div class="fw-semibold text-dark">
                                                    {{ \Illuminate\Support\Str::limit((string) $errorItem->message, 80) }}
                                                </div>
                                                <div class="small text-muted">
                                                    {{ $errorItem->exception_class ?? '-' }}
                                                </div>
                                                <div class="small text-muted">
                                                    {{ optional($errorItem->occurred_at)->format('Y-m-d H:i:s') ?: '-' }}
                                                </div>
                                            </a>

                                            <div class="d-flex gap-2">
                                                <a href="{{ route('admin.system-errors.show', $errorItem->id) }}" class="btn btn-sm btn-outline-primary">
                                                    Open
                                                </a>

                                                <form method="POST" action="{{ route('admin.system-errors.mark-read', $errorItem->id) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-dark">
                                                        Mark Read
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </li>
                                @empty
                                    <li class="notification-message">
                                        <div class="p-3 text-muted">
                                            No unread system errors.
                                        </div>
                                    </li>
                                @endforelse
                            </ul>
                        </div>

                        <div class="topnav-dropdown-footer">
                            <a href="{{ route('admin.system-errors.index') }}">Open Error Center</a>
                        </div>
                    </div>
                </li>

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
