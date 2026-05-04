@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.advanced_operations') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.advanced_operations_subtitle') }}</p></div>
            <form method="POST" action="{{ route('automotive.admin.maintenance.advanced.refresh') }}">@csrf<button type="submit" class="btn btn-primary">{{ __('maintenance.refresh_advanced') }}</button></form>
        </div>

        <div class="row">
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.preventive_rules') }}</h5></div><div class="card-body">
                <form method="POST" action="{{ route('automotive.admin.maintenance.advanced.preventive-rules.store') }}" class="mb-3">
                    @csrf
                    <div class="mb-2"><input type="text" name="name" class="form-control" placeholder="{{ __('tenant.name') }}" required></div>
                    <div class="mb-2"><select name="service_catalog_item_id" class="form-select"><option value="">{{ __('maintenance.service_catalog') }}</option>@foreach($serviceItems as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
                    <div class="row g-2"><div class="col-md-6"><input type="number" name="mileage_interval" class="form-control" placeholder="{{ __('maintenance.mileage_interval') }}"></div><div class="col-md-6"><input type="number" name="months_interval" class="form-control" placeholder="{{ __('maintenance.months_interval') }}"></div></div>
                    <button class="btn btn-sm btn-primary mt-2" type="submit">{{ __('tenant.save') }}</button>
                </form>
                @foreach($preventiveRules as $rule)
                    <div class="border-bottom pb-2 mb-2"><strong>{{ $rule->name }}</strong><div class="text-muted small">{{ $rule->mileage_interval ?: '-' }} km · {{ $rule->months_interval ?: '-' }} {{ __('maintenance.months') }}</div></div>
                @endforeach
            </div></div></div>
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.delay_alerts') }}</h5></div><div class="card-body">
                @forelse($delayAlerts as $alert)
                    <div class="border-bottom pb-2 mb-2 d-flex justify-content-between"><div><strong>{{ $alert->workOrder?->work_order_number }}</strong><div class="text-muted small">{{ $alert->message }}</div></div><span class="badge bg-warning">{{ $alert->elapsed_minutes }} / {{ $alert->target_minutes }} {{ __('maintenance.minutes') }}</span></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_delay_alerts') }}</p>
                @endforelse
            </div></div></div>
        </div>

        <div class="row">
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.vehicle_health_scores') }}</h5></div><div class="card-body">
                @forelse($healthScores as $score)
                    <div class="border-bottom pb-2 mb-2 d-flex justify-content-between"><div><strong>{{ $score->vehicle?->plate_number ?: $score->vehicle?->vin }}</strong><div class="text-muted small">{{ $score->vehicle?->customer?->name }}</div></div><span class="badge bg-primary">{{ $score->overall_score }}/100</span></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_health_scores') }}</p>
                @endforelse
            </div></div></div>
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.service_recommendations') }}</h5></div><div class="card-body">
                @forelse($recommendations as $recommendation)
                    <div class="border-bottom pb-2 mb-2"><strong>{{ $recommendation->title }}</strong><div class="text-muted small">{{ $recommendation->vehicle?->plate_number }} · {{ __('maintenance.priorities.' . $recommendation->priority) }}</div></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_recommendations') }}</p>
                @endforelse
            </div></div></div>
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.preventive_reminders') }}</h5></div><div class="card-body">
                @forelse($preventiveReminders as $reminder)
                    <div class="border-bottom pb-2 mb-2"><strong>{{ $reminder->vehicle?->plate_number ?: $reminder->vehicle?->vin }}</strong><div class="text-muted small">{{ $reminder->serviceCatalogItem?->name ?: $reminder->rule?->name }} · {{ optional($reminder->due_date)->format('Y-m-d') ?: '-' }}</div></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_preventive_reminders') }}</p>
                @endforelse
            </div></div></div>
        </div>
    </div></div>
@endsection
