<?php
use App\Http\Controllers\Admin\AdminNotificationController;

$topbarNotifications = AdminNotificationController::topbarData();
$topbarNotificationCount = (int) ($topbarNotifications['count'] ?? 0);
$topbarNotificationItems = $topbarNotifications['items'] ?? collect();

$severityClassMap = [
    'info' => 'bg-primary',
    'success' => 'bg-success',
    'warning' => 'bg-warning',
    'error' => 'bg-danger',
];
?>

<div class="notification_item me-2">
    <a href="#" class="btn btn-menubar position-relative" id="notification_popup" data-bs-toggle="dropdown" data-bs-auto-close="outside">
        <i class="isax isax-notification-bing5"></i>
        @if($topbarNotificationCount > 0)
            <span class="position-absolute badge bg-danger border border-white rounded-pill" style="top: 6px; right: 6px;">
                {{ $topbarNotificationCount > 99 ? '99+' : $topbarNotificationCount }}
            </span>
        @else
            <span class="position-absolute badge bg-success border border-white"></span>
        @endif
    </a>

    <div class="dropdown-menu p-0 dropdown-menu-end dropdown-menu-lg" style="min-height: 300px;">
        <div class="p-2 border-bottom">
            <div class="row align-items-center">
                <div class="col">
                    <h6 class="m-0 fs-16 fw-semibold">Notifications</h6>
                </div>
                <div class="col-auto">
                    <span class="badge bg-light text-dark">{{ $topbarNotificationCount }}</span>
                </div>
            </div>
        </div>

        <div class="notification-body position-relative z-2 rounded-0" data-simplebar>
            @forelse($topbarNotificationItems as $notification)
                <div class="dropdown-item notification-item py-3 border-bottom">
                    <div class="d-flex align-items-start gap-2">
                        <div class="flex-shrink-0">
                            <span class="badge {{ $severityClassMap[$notification->severity] ?? 'bg-secondary' }}">
                                {{ strtoupper((string) $notification->severity) }}
                            </span>
                        </div>

                        <div class="flex-grow-1">
                            <a href="{{ route('admin.notifications.show', $notification->id) }}" class="text-decoration-none">
                                <div class="fw-semibold text-dark mb-1">
                                    {{ \Illuminate\Support\Str::limit((string) $notification->title, 70) }}
                                </div>
                                <div class="text-muted small mb-1">
                                    {{ \Illuminate\Support\Str::limit((string) $notification->message, 100) }}
                                </div>
                                <div class="text-muted small">
                                    {{ optional($notification->notified_at)->format('Y-m-d H:i:s') ?: '-' }}
                                </div>
                            </a>

                            <div class="mt-2 d-flex gap-2">
                                <a href="{{ route('admin.notifications.show', $notification->id) }}" class="btn btn-sm btn-outline-primary">
                                    Open
                                </a>

                                @if(! $notification->is_read)
                                    <form method="POST" action="{{ route('admin.notifications.mark-read', $notification->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-dark">
                                            Mark Read
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="dropdown-item notification-item py-4 text-center text-muted">
                    No notifications yet.
                </div>
            @endforelse
        </div>

        <div class="p-2 rounded-bottom border-top text-center">
            <a href="{{ route('admin.notifications.index') }}" class="text-center fw-medium fs-14 mb-0">
                View All
            </a>
        </div>
    </div>
</div>
