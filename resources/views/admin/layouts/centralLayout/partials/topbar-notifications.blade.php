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

        <span id="topbar-notification-badge-wrapper" class="{{ $topbarNotificationCount > 0 ? '' : 'd-none' }}">
            <span id="topbar-notification-badge" class="position-absolute badge bg-danger border border-white rounded-pill" style="top: 6px; right: 6px;">
                {{ $topbarNotificationCount > 99 ? '99+' : $topbarNotificationCount }}
            </span>
        </span>
    </a>

    <div class="dropdown-menu p-0 dropdown-menu-end dropdown-menu-lg" style="min-height: 300px;">
        <div class="p-2 border-bottom">
            <div class="row align-items-center">
                <div class="col">
                    <h6 class="m-0 fs-16 fw-semibold">Notifications</h6>
                </div>
                <div class="col-auto">
                    <span id="topbar-notification-count-pill" class="badge bg-light text-dark">{{ $topbarNotificationCount }}</span>
                </div>
            </div>
        </div>

        <div class="notification-body position-relative z-2 rounded-0" data-simplebar>
            <div id="topbar-notification-list">
                @forelse($topbarNotificationItems as $notification)
                    @php
                        $severity = strtolower((string) ($notification['severity'] ?? 'info'));
                        $severityClass = $severityClassMap[$severity] ?? 'bg-secondary';
                    @endphp

                    <div class="dropdown-item notification-item py-3 border-bottom">
                        <div class="d-flex align-items-start gap-2">
                            <div class="flex-shrink-0">
                                <span class="badge {{ $severityClass }}">
                                    {{ strtoupper($severity) }}
                                </span>
                            </div>

                            <div class="flex-grow-1">
                                <a href="{{ $notification['show_url'] ?? '#' }}" class="text-decoration-none">
                                    <div class="fw-semibold text-dark mb-1">
                                        {{ \Illuminate\Support\Str::limit((string) ($notification['title'] ?? ''), 70) }}
                                    </div>
                                    <div class="text-muted small mb-1">
                                        {{ \Illuminate\Support\Str::limit((string) ($notification['message'] ?? ''), 100) }}
                                    </div>
                                    <div class="text-muted small">
                                        {{ $notification['notified_at'] ?? '-' }}
                                    </div>
                                </a>

                                <div class="mt-2 d-flex gap-2">
                                    <a href="{{ $notification['show_url'] ?? '#' }}" class="btn btn-sm btn-outline-primary">
                                        Open
                                    </a>

                                    @if(!($notification['is_read'] ?? false))
                                        <form method="POST" action="{{ $notification['mark_read_url'] ?? '#' }}">
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
                    <div class="dropdown-item notification-item py-4 text-center text-muted" id="topbar-notification-empty-state">
                        No notifications yet.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="p-2 rounded-bottom border-top text-center">
            <a href="{{ route('admin.notifications.index') }}" class="text-center fw-medium fs-14 mb-0">
                View All
            </a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const listContainer = document.getElementById('topbar-notification-list');
        const badge = document.getElementById('topbar-notification-badge');
        const badgeWrapper = document.getElementById('topbar-notification-badge-wrapper');
        const countPill = document.getElementById('topbar-notification-count-pill');
        const streamUrl = @json(route('admin.notifications.stream'));
        const summaryUrl = @json(route('admin.notifications.unread-summary'));
        const csrfToken = @json(csrf_token());

        const severityClassMap = {
            info: 'bg-primary',
            success: 'bg-success',
            warning: 'bg-warning',
            error: 'bg-danger',
        };

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function updateCounters(count) {
            const normalized = Number(count || 0);

            countPill.textContent = normalized;

            if (normalized > 0) {
                badgeWrapper.classList.remove('d-none');
                badge.textContent = normalized > 99 ? '99+' : String(normalized);
            } else {
                badgeWrapper.classList.add('d-none');
                badge.textContent = '0';
            }
        }

        function renderNotifications(payload) {
            const items = Array.isArray(payload.items) ? payload.items : [];

            updateCounters(payload.count || 0);

            if (items.length === 0) {
                listContainer.innerHTML = `
                <div class="dropdown-item notification-item py-4 text-center text-muted" id="topbar-notification-empty-state">
                    No notifications yet.
                </div>
            `;
                return;
            }

            listContainer.innerHTML = items.map((item) => {
                const severity = String(item.severity || 'info').toLowerCase();
                const severityClass = severityClassMap[severity] || 'bg-secondary';

                return `
                <div class="dropdown-item notification-item py-3 border-bottom">
                    <div class="d-flex align-items-start gap-2">
                        <div class="flex-shrink-0">
                            <span class="badge ${severityClass}">
                                ${escapeHtml(severity.toUpperCase())}
                            </span>
                        </div>

                        <div class="flex-grow-1">
                            <a href="${escapeHtml(item.show_url || '#')}" class="text-decoration-none">
                                <div class="fw-semibold text-dark mb-1">
                                    ${escapeHtml(item.title || '')}
                                </div>
                                <div class="text-muted small mb-1">
                                    ${escapeHtml(item.message || '')}
                                </div>
                                <div class="text-muted small">
                                    ${escapeHtml(item.notified_at || '-')}
                                </div>
                            </a>

                            <div class="mt-2 d-flex gap-2">
                                <a href="${escapeHtml(item.show_url || '#')}" class="btn btn-sm btn-outline-primary">
                                    Open
                                </a>

                                ${item.is_read ? '' : `
                                    <form method="POST" action="${escapeHtml(item.mark_read_url || '#')}">
                                        <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                                        <button type="submit" class="btn btn-sm btn-outline-dark">
                                            Mark Read
                                        </button>
                                    </form>
                                `}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            }).join('');
        }

        function fetchFallback() {
            fetch(summaryUrl, {
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(payload => renderNotifications(payload))
                .catch(() => {});
        }

        try {
            const eventSource = new EventSource(streamUrl, { withCredentials: true });

            eventSource.addEventListener('notifications', function (event) {
                const payload = JSON.parse(event.data);
                renderNotifications(payload);
            });

            eventSource.onerror = function () {
                setTimeout(fetchFallback, 3000);
            };
        } catch (error) {
            fetchFallback();
            setInterval(fetchFallback, 15000);
        }
    });
</script>
