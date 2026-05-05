@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.notifications') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.notifications_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.notifications.stream') }}" class="btn btn-outline-light">{{ __('maintenance.sse_stream') }}</a>
        </div>
        <div class="card"><div class="card-body" id="notificationsList">
            @forelse($notifications as $notification)
                <div class="border-bottom pb-2 mb-2" data-notification-id="{{ $notification->id }}">
                    <div class="d-flex justify-content-between gap-2">
                        <strong>{{ $notification->title }}</strong>
                        <span class="badge bg-light text-dark">{{ $notification->event_type }}</span>
                    </div>
                    <div class="text-muted small">
                        {{ strtoupper($notification->channel) }} · {{ $notification->branch?->name ?: __('maintenance.none') }} · {{ $notification->message }} · {{ optional($notification->created_at)->format('Y-m-d H:i') }}
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0" id="noNotifications">{{ __('maintenance.no_notifications') }}</p>
            @endforelse
        </div></div>
    </div></div>
@endsection

@push('scripts')
    <script>
        (() => {
            if (!window.EventSource) return;

            const list = document.getElementById('notificationsList');
            const streamUrl = @json(route('automotive.admin.maintenance.notifications.stream'));
            let lastId = Number(list?.querySelector('[data-notification-id]')?.dataset.notificationId || 0);
            const source = new EventSource(lastId ? `${streamUrl}?last_id=${lastId}` : streamUrl);

            const render = event => {
                const payload = JSON.parse(event.data || '{}');
                if (!payload.id || document.querySelector(`[data-notification-id="${payload.id}"]`)) return;

                document.getElementById('noNotifications')?.remove();
                const item = document.createElement('div');
                item.className = 'border-bottom pb-2 mb-2';
                item.dataset.notificationId = payload.id;
                item.innerHTML = `
                    <div class="d-flex justify-content-between gap-2">
                        <strong></strong>
                        <span class="badge bg-light text-dark"></span>
                    </div>
                    <div class="text-muted small"></div>
                `;
                item.querySelector('strong').textContent = payload.title || '';
                item.querySelector('.badge').textContent = payload.event_type || '';
                item.querySelector('.text-muted').textContent = `${(payload.channel || '').toUpperCase()} · ${payload.branch || @json(__('maintenance.none'))} · ${payload.message || ''} · ${payload.created_at || ''}`;
                list.prepend(item);
            };

            ['notification.created', 'estimate.sent', 'estimate.approved', 'estimate.partially_approved', 'estimate.rejected', 'vehicle.ready_for_delivery', 'vehicle.delivered', 'parts.requested', 'complaint.created'].forEach(eventType => {
                source.addEventListener(eventType, render);
            });
            source.onmessage = render;
        })();
    </script>
@endpush
