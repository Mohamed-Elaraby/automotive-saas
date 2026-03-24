<?php
use App\Http\Controllers\Admin\AdminNotificationController;

$topbarNotifications = AdminNotificationController::topbarData();
$topbarNotificationCount = (int) ($topbarNotifications['count'] ?? 0);
$topbarNotificationItems = $topbarNotifications['items'] ?? collect();

$severityBadgeMap = [
    'info' => 'bg-primary',
    'success' => 'bg-success',
    'warning' => 'bg-warning text-dark',
    'error' => 'bg-danger',
];
?>

<li class="nav-item dropdown notification-nav">
    <a href="#" class="dropdown-toggle nav-link position-relative" data-bs-toggle="dropdown">
        <i class="isax isax-notification-bing"></i>
        @if($topbarNotificationCount > 0)
            <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle">
                {{ $topbarNotificationCount > 99 ? '99+' : $topbarNotificationCount }}
            </span>
        @endif
    </a>

    <div class="dropdown-menu notifications dropdown-menu-end">
        <div class="topnav-dropdown-header d-flex justify-content-between align-items-center">
            <span class="notification-title">Notifications</span>
            <a href="{{ route('admin.notifications.index') }}" class="text-decoration-none small">
                View All
            </a>
        </div>

        <div class="noti-content">
            <ul class="notification-list">
                @forelse($topbarNotificationItems as $notification)
                    <li class="notification-message">
                        <div class="d-flex flex-column gap-2 p-2">
                            <a href="{{ $notification->resolvedUrl() }}" class="text-decoration-none">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="fw-semibold text-dark">
                                        {{ \Illuminate\Support\Str::limit((string) $notification->title, 70) }}
                                    </div>
                                    <span class="badge {{ $severityBadgeMap[$notification->severity] ?? 'bg-secondary' }}">
                                        {{ strtoupper((string) $notification->severity) }}
                                    </span>
                                </div>

                                <div class="small text-muted">
                                    {{ \Illuminate\Support\Str::limit((string) $notification->message, 90) }}
                                </div>

                                <div class="small text-muted">
                                    {{ optional($notification->notified_at)->format('Y-m-d H:i:s') ?: '-' }}
                                </div>
                            </a>

                            <div class="d-flex gap-2">
                                <a href="{{ route('admin.notifications.show', $notification->id) }}" class="btn btn-sm btn-outline-primary">
                                    Open
                                </a>

                                <form method="POST" action="{{ route('admin.notifications.mark-read', $notification->id) }}">
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
                            No unread notifications.
                        </div>
                    </li>
                @endforelse
            </ul>
        </div>

        <div class="topnav-dropdown-footer">
            <a href="{{ route('admin.notifications.index') }}">Open Notification Center</a>
        </div>
    </div>
</li>
