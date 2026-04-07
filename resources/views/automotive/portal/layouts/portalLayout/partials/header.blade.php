<?php
use App\Http\Controllers\Automotive\Front\CustomerPortalNotificationController;

$portalTopbarNotifications = CustomerPortalNotificationController::topbarData((int) ($user->id ?? 0));
$portalTopbarNotificationCount = (int) ($portalTopbarNotifications['count'] ?? 0);
$portalTopbarNotificationItems = $portalTopbarNotifications['items'] ?? [];

$portalSeverityClassMap = [
    'info' => 'bg-primary',
    'success' => 'bg-success',
    'warning' => 'bg-warning',
    'error' => 'bg-danger',
];
?>

<!-- Topbar Start -->
<div class="header">
    <div class="main-header">

        <div class="header-left">
            <a href="{{ route('automotive.portal') }}" class="logo">
                <img src="{{ asset('theme/img/logo.svg') }}" alt="Logo">
            </a>
            <a href="{{ route('automotive.portal') }}" class="dark-logo">
                <img src="{{ asset('theme/img/logo-white.svg') }}" alt="Logo">
            </a>
        </div>

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
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-divide mb-0">
                            <li class="breadcrumb-item d-flex align-items-center">
                                <a href="{{ route('automotive.portal') }}">
                                    <i class="isax isax-home-2 me-1"></i>Customer Portal
                                </a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Overview</li>
                        </ol>
                    </nav>
                </div>

                <div class="d-flex align-items-center">
                    <div class="dropdown me-2">
                        <a href="javascript:void(0);" class="btn btn-menubar position-relative" id="portal-notification-popup" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <i class="isax isax-notification-bing5"></i>
                            <span id="portal-topbar-notification-badge-wrapper" class="{{ $portalTopbarNotificationCount > 0 ? '' : 'd-none' }}">
                                <span id="portal-topbar-notification-badge" class="position-absolute badge bg-danger border border-white rounded-pill" style="top: 6px; right: 6px;">
                                    {{ $portalTopbarNotificationCount > 99 ? '99+' : $portalTopbarNotificationCount }}
                                </span>
                            </span>
                        </a>

                        <div class="dropdown-menu p-0 dropdown-menu-end dropdown-menu-lg" style="min-height: 240px;">
                            <div class="p-2 border-bottom d-flex align-items-center justify-content-between">
                                <h6 class="m-0 fs-16 fw-semibold">Notifications</h6>
                                <span id="portal-topbar-notification-count-pill" class="badge bg-light text-dark">{{ $portalTopbarNotificationCount }}</span>
                            </div>

                            <div class="notification-body position-relative z-2 rounded-0" data-simplebar>
                                <div id="portal-topbar-notification-list">
                                    @forelse($portalTopbarNotificationItems as $notification)
                                        @php
                                            $portalSeverity = strtolower((string) ($notification['severity'] ?? 'info'));
                                            $portalSeverityClass = $portalSeverityClassMap[$portalSeverity] ?? 'bg-secondary';
                                        @endphp
                                        <div class="dropdown-item notification-item py-3 border-bottom" data-notification-id="{{ $notification['id'] ?? '' }}">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="flex-shrink-0">
                                                    <span class="badge {{ $portalSeverityClass }}">
                                                        {{ strtoupper($portalSeverity) }}
                                                    </span>
                                                </div>

                                                <div class="flex-grow-1">
                                                    <a
                                                        href="{{ $notification['target_url'] ?? route('automotive.portal') }}"
                                                        class="text-decoration-none portal-notification-open-link"
                                                        data-notification-id="{{ $notification['id'] ?? '' }}"
                                                        data-mark-read-url="{{ $notification['mark_read_url'] ?? '#' }}"
                                                    >
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
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="dropdown-item notification-item py-4 text-center text-muted" id="portal-topbar-notification-empty-state">
                                            No notifications yet.
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="me-2 theme-item">
                        <a href="javascript:void(0);" id="dark-mode-toggle" class="theme-toggle btn btn-menubar">
                            <i class="isax isax-moon"></i>
                        </a>
                        <a href="javascript:void(0);" id="light-mode-toggle" class="theme-toggle btn btn-menubar">
                            <i class="isax isax-sun-1"></i>
                        </a>
                    </div>

                    <div class="dropdown profile-dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <span class="avatar online">
                                <span class="avatar avatar-md bg-primary text-white rounded-circle">
                                    {{ strtoupper(substr((string) ($user->name ?? 'U'), 0, 1)) }}
                                </span>
                            </span>
                        </a>
                        <div class="dropdown-menu p-2">
                            <div class="d-flex align-items-center bg-light rounded-1 p-2 mb-2">
                                <span class="avatar avatar-lg me-2">
                                    <span class="avatar avatar-lg bg-primary text-white rounded-circle">
                                        {{ strtoupper(substr((string) ($user->name ?? 'U'), 0, 1)) }}
                                    </span>
                                </span>
                                <div>
                                    <h6 class="fs-14 fw-medium mb-1">{{ $user->name ?? 'Portal User' }}</h6>
                                    <p class="fs-13">{{ $user->email ?? 'Customer Portal' }}</p>
                                </div>
                            </div>

                            <a class="dropdown-item d-flex align-items-center" href="{{ route('automotive.portal') }}">
                                <i class="isax isax-home-2 me-2"></i>Portal Overview
                            </a>

                            <a class="dropdown-item d-flex align-items-center" href="{{ route('automotive.portal') }}#paid-plans">
                                <i class="isax isax-crown me-2"></i>Plans & Billing
                            </a>

                            @if(!empty($systemUrl) && $allowSystemAccess)
                                <a class="dropdown-item d-flex align-items-center" href="{{ $systemUrl }}" target="_blank">
                                    <i class="isax isax-export-1 me-2"></i>Open My System
                                </a>
                            @endif

                            <hr class="dropdown-divider my-2">

                            <form method="POST" action="{{ route('automotive.logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item logout d-flex align-items-center border-0 bg-transparent w-100">
                                    <i class="isax isax-logout me-2"></i>Sign Out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dropdown mobile-user-menu profile-dropdown">
        <a href="javascript:void(0);" class="dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown" data-bs-auto-close="outside">
            <span class="avatar avatar-md online">
                <span class="avatar avatar-md bg-primary text-white rounded-circle">
                    {{ strtoupper(substr((string) ($user->name ?? 'U'), 0, 1)) }}
                </span>
            </span>
        </a>
        <div class="dropdown-menu p-2 mt-0">
            <a class="dropdown-item d-flex align-items-center" href="{{ route('automotive.portal') }}">
                <i class="isax isax-home-2 me-2"></i>Portal Overview
            </a>
            <a class="dropdown-item d-flex align-items-center" href="{{ route('automotive.portal') }}#paid-plans">
                <i class="isax isax-crown me-2"></i>Plans & Billing
            </a>
            @if(!empty($systemUrl) && $allowSystemAccess)
                <a class="dropdown-item d-flex align-items-center" href="{{ $systemUrl }}" target="_blank">
                    <i class="isax isax-export-1 me-2"></i>Open My System
                </a>
            @endif
            <form method="POST" action="{{ route('automotive.logout') }}">
                @csrf
                <button type="submit" class="dropdown-item logout d-flex align-items-center border-0 bg-transparent w-100">
                    <i class="isax isax-logout me-2"></i>Sign Out
                </button>
            </form>
        </div>
    </div>
</div>
<!-- Topbar End -->

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const listContainer = document.getElementById('portal-topbar-notification-list');
        const badge = document.getElementById('portal-topbar-notification-badge');
        const badgeWrapper = document.getElementById('portal-topbar-notification-badge-wrapper');
        const countPill = document.getElementById('portal-topbar-notification-count-pill');
        const streamUrl = @json(route('automotive.portal.notifications.stream'));
        const csrfToken = @json(csrf_token());

        let lastKnownCount = Number({{ $portalTopbarNotificationCount }});
        let lastSeenNotificationIds = new Set(@json(collect($portalTopbarNotificationItems)->pluck('id')->values()));

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
            const safeCount = Number(count || 0);
            lastKnownCount = safeCount;
            countPill.textContent = safeCount;
            if (safeCount > 0) {
                badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
                badgeWrapper.classList.remove('d-none');
            } else {
                badgeWrapper.classList.add('d-none');
            }
        }

        function renderItems(items) {
            if (!Array.isArray(items) || items.length === 0) {
                listContainer.innerHTML = '<div class="dropdown-item notification-item py-4 text-center text-muted" id="portal-topbar-notification-empty-state">No notifications yet.</div>';
                return;
            }

            listContainer.innerHTML = items.map((item) => {
                const severity = String(item.severity || 'info').toLowerCase();
                const badgeClass = severityClassMap[severity] || 'bg-secondary';

                return `
                    <div class="dropdown-item notification-item py-3 border-bottom" data-notification-id="${escapeHtml(item.id)}">
                        <div class="d-flex align-items-start gap-2">
                            <div class="flex-shrink-0">
                                <span class="badge ${badgeClass}">${escapeHtml(severity.toUpperCase())}</span>
                            </div>
                            <div class="flex-grow-1">
                                <a href="${escapeHtml(item.target_url || '#')}" class="text-decoration-none portal-notification-open-link" data-notification-id="${escapeHtml(item.id)}" data-mark-read-url="${escapeHtml(item.mark_read_url || '#')}">
                                    <div class="fw-semibold text-dark mb-1">${escapeHtml(item.title || 'Notification')}</div>
                                    <div class="text-muted small mb-1">${escapeHtml(item.message || '')}</div>
                                    <div class="text-muted small">${escapeHtml(item.notified_at || '-')}</div>
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        async function markRead(url) {
            if (!url || url === '#') {
                return;
            }

            try {
                await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });
            } catch (error) {
            }
        }

        document.addEventListener('click', function (event) {
            const link = event.target.closest('.portal-notification-open-link');
            if (!link) {
                return;
            }

            markRead(link.dataset.markReadUrl || '');
        });

        if (window.EventSource) {
            const source = new EventSource(streamUrl);

            source.addEventListener('notifications', function (event) {
                try {
                    const payload = JSON.parse(event.data);
                    const items = Array.isArray(payload.items) ? payload.items : [];
                    const currentIds = new Set(items.map((item) => item.id));

                    renderItems(items);
                    updateCounters(payload.count || 0);

                    currentIds.forEach((id) => lastSeenNotificationIds.add(id));
                } catch (error) {
                }
            });
        }
    });
</script>
