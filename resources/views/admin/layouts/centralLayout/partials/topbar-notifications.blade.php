<?php
use App\Http\Controllers\Admin\AdminNotificationController;

$topbarNotifications = AdminNotificationController::topbarData();
$topbarNotificationCount = (int) ($topbarNotifications['count'] ?? 0);
$topbarNotificationItems = $topbarNotifications['items'] ?? [];

$severityClassMap = [
    'info' => 'bg-primary',
    'success' => 'bg-success',
    'warning' => 'bg-warning',
    'error' => 'bg-danger',
];

$notificationLabels = [
    'unsupported' => __('admin.desktop_alerts_unsupported'),
    'enabled' => __('admin.desktop_alerts_enabled'),
    'blocked' => __('admin.desktop_alerts_blocked'),
    'enable' => __('admin.enable_desktop_alerts'),
    'notification' => __('admin.notification'),
    'close' => __('admin.close'),
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
                    <h6 class="m-0 fs-16 fw-semibold">{{ __('admin.notifications') }}</h6>
                </div>
                <div class="col-auto d-flex gap-2 align-items-center flex-wrap justify-content-end">
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" id="notificationToastToggle" checked>
                        <label class="form-check-label small" for="notificationToastToggle">{{ __('admin.toasts') }}</label>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" id="notificationSoundToggle" checked>
                        <label class="form-check-label small" for="notificationSoundToggle">{{ __('admin.sound') }}</label>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="desktopNotificationPermissionBtn">
                        {{ __('admin.enable_desktop_alerts') }}
                    </button>
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

                    <div class="dropdown-item notification-item py-3 border-bottom" data-notification-id="{{ $notification['id'] ?? '' }}">
                        <div class="d-flex align-items-start gap-2">
                            <div class="flex-shrink-0">
                                <span class="badge {{ $severityClass }}">
                                    {{ strtoupper($severity) }}
                                </span>
                            </div>

                            <div class="flex-grow-1">
                                <a
                                    href="{{ $notification['show_url'] ?? '#' }}"
                                    class="text-decoration-none notification-open-link"
                                    data-notification-id="{{ $notification['id'] ?? '' }}"
                                    data-mark-read-url="{{ $notification['mark_read_url'] ?? '#' }}"
                                >
                                    <div class="fw-semibold text-dark mb-1">
                                        {{ \Illuminate\Support\Str::limit((string) ($notification['title'] ?? ''), 70) }}
                                    </div>
                                    <div class="small text-muted mb-1">
                                        {{ strtoupper(str_replace('_', ' ', (string) ($notification['type'] ?? 'notification'))) }}
                                    </div>
                                    <div class="text-muted small mb-1">
                                        {{ \Illuminate\Support\Str::limit((string) ($notification['message'] ?? ''), 100) }}
                                    </div>
                                    <div class="text-muted small">
                                        {{ $notification['notified_at'] ?? '-' }}
                                    </div>
                                </a>

                                <div class="mt-2 d-flex gap-2">
                                    <a
                                        href="{{ $notification['show_url'] ?? '#' }}"
                                        class="btn btn-sm btn-outline-primary notification-open-link"
                                        data-notification-id="{{ $notification['id'] ?? '' }}"
                                        data-mark-read-url="{{ $notification['mark_read_url'] ?? '#' }}"
                                    >
                                        {{ __('admin.open') }}
                                    </a>

                                    @if(!($notification['is_read'] ?? false))
                                        <form method="POST" action="{{ $notification['mark_read_url'] ?? '#' }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-dark">
                                                {{ __('admin.mark_read') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="dropdown-item notification-item py-4 text-center text-muted" id="topbar-notification-empty-state">
                        {{ __('shared.no_notifications_yet') }}
                    </div>
                @endforelse
            </div>
        </div>

        <div class="p-2 rounded-bottom border-top text-center">
            <a href="{{ route('admin.notifications.index') }}" class="text-center fw-medium fs-14 mb-0">
                {{ __('admin.view_all') }}
            </a>
        </div>
    </div>
</div>

<div id="notification-toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const listContainer = document.getElementById('topbar-notification-list');
        const badge = document.getElementById('topbar-notification-badge');
        const badgeWrapper = document.getElementById('topbar-notification-badge-wrapper');
        const countPill = document.getElementById('topbar-notification-count-pill');
        const streamUrl = @json(route('admin.notifications.stream'));
        const summaryUrl = @json(route('admin.notifications.unread-summary'));
        const csrfToken = @json(csrf_token());
        const toastContainer = document.getElementById('notification-toast-container');
        const toastToggle = document.getElementById('notificationToastToggle');
        const soundToggle = document.getElementById('notificationSoundToggle');
        const desktopPermissionBtn = document.getElementById('desktopNotificationPermissionBtn');
        const notificationLabels = @json($notificationLabels);

        let lastKnownCount = Number({{ $topbarNotificationCount }});
        let lastSeenNotificationIds = new Set(@json(collect($topbarNotificationItems)->pluck('id')->values()));

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

        function updateDesktopPermissionButton() {
            if (!('Notification' in window)) {
                desktopPermissionBtn.textContent = notificationLabels.unsupported;
                desktopPermissionBtn.disabled = true;
                return;
            }

            if (Notification.permission === 'granted') {
                desktopPermissionBtn.textContent = notificationLabels.enabled;
                desktopPermissionBtn.classList.remove('btn-outline-primary');
                desktopPermissionBtn.classList.add('btn-outline-success');
                return;
            }

            if (Notification.permission === 'denied') {
                desktopPermissionBtn.textContent = notificationLabels.blocked;
                desktopPermissionBtn.classList.remove('btn-outline-primary');
                desktopPermissionBtn.classList.add('btn-outline-danger');
                return;
            }

            desktopPermissionBtn.textContent = notificationLabels.enable;
            desktopPermissionBtn.classList.remove('btn-outline-success', 'btn-outline-danger');
            desktopPermissionBtn.classList.add('btn-outline-primary');
        }

        async function requestDesktopPermission() {
            if (!('Notification' in window)) {
                return;
            }

            try {
                await Notification.requestPermission();
                updateDesktopPermissionButton();
            } catch (error) {
            }
        }

        function playCriticalSound() {
            if (!soundToggle.checked) {
                return;
            }

            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, audioContext.currentTime);
            gainNode.gain.setValueAtTime(0.06, audioContext.currentTime);

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.start();
            oscillator.stop(audioContext.currentTime + 0.18);
        }

        function showDesktopNotification(item) {
            if (!('Notification' in window)) {
                return;
            }

            if (Notification.permission !== 'granted') {
                return;
            }

            const severity = String(item.severity || 'info').toUpperCase();
            const title = `${severity} • ${item.title || notificationLabels.notification}`;
            const body = `${(item.type || 'notification').replaceAll('_', ' ').toUpperCase()}\n${item.message || ''}`;

            const browserNotification = new Notification(title, {
                body: body,
                tag: `admin-notification-${item.id}`,
            });

            browserNotification.onclick = function () {
                window.focus();
                window.location.href = item.show_url || item.target_url || @json(route('admin.notifications.index'));
            };
        }

        function showToast(item) {
            if (!toastToggle.checked) {
                return;
            }

            const toastId = 'toast-' + Date.now();
            const severity = String(item.severity || 'info').toLowerCase();
            const badgeClass = severityClassMap[severity] || 'bg-secondary';

            const wrapper = document.createElement('div');
            wrapper.className = 'toast border-0 shadow';
            wrapper.id = toastId;
            wrapper.setAttribute('role', 'alert');
            wrapper.setAttribute('aria-live', 'assertive');
            wrapper.setAttribute('aria-atomic', 'true');
            wrapper.style.cursor = 'pointer';

            wrapper.innerHTML = `
            <div class="toast-header">
                <span class="badge ${badgeClass} me-2">${escapeHtml(severity.toUpperCase())}</span>
                <strong class="me-auto">${escapeHtml(item.title || notificationLabels.notification)}</strong>
                <small>${escapeHtml(item.notified_at || '')}</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="${escapeHtml(notificationLabels.close)}"></button>
            </div>
            <div class="toast-body">
                <div class="small text-muted mb-1">${escapeHtml((item.type || 'notification').replaceAll('_', ' ').toUpperCase())}</div>
                <div>${escapeHtml(item.message || '')}</div>
            </div>
        `;

            wrapper.addEventListener('click', function (event) {
                const closeButton = event.target.closest('.btn-close');
                if (closeButton) {
                    return;
                }

                window.location.href = item.show_url || item.target_url || @json(route('admin.notifications.index'));
            });

            toastContainer.appendChild(wrapper);

            const toast = new bootstrap.Toast(wrapper, { delay: 4500 });
            toast.show();

            wrapper.addEventListener('hidden.bs.toast', function () {
                wrapper.remove();
            });

            if (severity === 'error') {
                playCriticalSound();
            }

            showDesktopNotification(item);
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

            lastKnownCount = normalized;
        }

        function removeNotificationItem(notificationId) {
            const row = listContainer.querySelector(`[data-notification-id="${notificationId}"]`);
            if (row) {
                row.remove();
            }

            if (!listContainer.querySelector('[data-notification-id]')) {
                listContainer.innerHTML = `
                <div class="dropdown-item notification-item py-4 text-center text-muted" id="topbar-notification-empty-state">
                    No notifications yet.
                </div>
            `;
            }
        }

        function renderNotifications(payload) {
            const items = Array.isArray(payload.items) ? payload.items : [];
            const currentIds = new Set(items.map(item => item.id));

            items.forEach((item) => {
                if (!lastSeenNotificationIds.has(item.id)) {
                    showToast(item);
                }
            });

            lastSeenNotificationIds = currentIds;
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
                <div class="dropdown-item notification-item py-3 border-bottom" data-notification-id="${escapeHtml(item.id)}">
                    <div class="d-flex align-items-start gap-2">
                        <div class="flex-shrink-0">
                            <span class="badge ${severityClass}">
                                ${escapeHtml(severity.toUpperCase())}
                            </span>
                        </div>

                        <div class="flex-grow-1">
                            <a
                                href="${escapeHtml(item.show_url || '#')}"
                                class="text-decoration-none notification-open-link"
                                data-notification-id="${escapeHtml(item.id)}"
                                data-mark-read-url="${escapeHtml(item.mark_read_url || '#')}"
                            >
                                <div class="fw-semibold text-dark mb-1">
                                    ${escapeHtml(item.title || '')}
                                </div>
                                <div class="small text-muted mb-1">
                                    ${escapeHtml((item.type || 'notification').replaceAll('_', ' ').toUpperCase())}
                                </div>
                                <div class="text-muted small mb-1">
                                    ${escapeHtml(item.message || '')}
                                </div>
                                <div class="text-muted small">
                                    ${escapeHtml(item.notified_at || '-')}
                                </div>
                            </a>

                            <div class="mt-2 d-flex gap-2">
                                <a
                                    href="${escapeHtml(item.show_url || '#')}"
                                    class="btn btn-sm btn-outline-primary notification-open-link"
                                    data-notification-id="${escapeHtml(item.id)}"
                                    data-mark-read-url="${escapeHtml(item.mark_read_url || '#')}"
                                >
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
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(payload => renderNotifications(payload))
                .catch(() => {});
        }

        async function markReadAndOpen(url, notificationId, markReadUrl) {
            try {
                const response = await fetch(markReadUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const payload = await response.json();

                if (payload.ok) {
                    removeNotificationItem(notificationId);
                    updateCounters(payload.count || 0);
                    lastSeenNotificationIds.delete(Number(notificationId));
                }
            } catch (error) {
            }

            window.location.href = url;
        }

        document.addEventListener('click', function (event) {
            const link = event.target.closest('.notification-open-link');

            if (!link) {
                return;
            }

            event.preventDefault();

            const url = link.getAttribute('href');
            const notificationId = link.dataset.notificationId;
            const markReadUrl = link.dataset.markReadUrl;

            markReadAndOpen(url, notificationId, markReadUrl);
        });

        desktopPermissionBtn.addEventListener('click', requestDesktopPermission);
        updateDesktopPermissionButton();

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
