@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.notifications') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.notifications_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.notifications.stream') }}" class="btn btn-outline-light">{{ __('maintenance.sse_stream') }}</a>
        </div>
        <div class="card"><div class="card-body">
            @forelse($notifications as $notification)
                <div class="border-bottom pb-2 mb-2"><div class="d-flex justify-content-between"><strong>{{ $notification->title }}</strong><span class="badge bg-light text-dark">{{ $notification->event_type }}</span></div><div class="text-muted small">{{ $notification->branch?->name }} · {{ $notification->message }} · {{ optional($notification->created_at)->format('Y-m-d H:i') }}</div></div>
            @empty
                <p class="text-muted mb-0">{{ __('maintenance.no_notifications') }}</p>
            @endforelse
        </div></div>
    </div></div>
@endsection
