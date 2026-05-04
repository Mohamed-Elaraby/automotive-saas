@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.inspection_templates') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.inspection_templates_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.add_template') }}</h5></div><div class="card-body">
                <form method="POST" action="{{ route('automotive.admin.maintenance.inspection-templates.store') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">{{ __('tenant.name') }}</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.inspection_type') }}</label><select name="inspection_type" class="form-select">@foreach(['initial','diagnostic','pre_repair','final','qc','delivery'] as $type)<option value="{{ $type }}">{{ __('maintenance.inspection_types.' . $type) }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('tenant.description') }}</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="is_default" value="1"><label class="form-check-label">{{ __('maintenance.default_template') }}</label></div>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked><label class="form-check-label">{{ __('tenant.active') }}</label></div>
                    <h6 class="mb-2">{{ __('maintenance.template_items') }}</h6>
                    @for($i = 0; $i < 6; $i++)
                        <div class="border rounded p-2 mb-2">
                            <input type="text" name="items[{{ $i }}][section]" class="form-control mb-2" placeholder="{{ __('maintenance.section') }}">
                            <input type="text" name="items[{{ $i }}][label]" class="form-control mb-2" placeholder="{{ __('maintenance.item_label') }}">
                            <select name="items[{{ $i }}][default_result]" class="form-select"><option value="not_checked">{{ __('maintenance.results.not_checked') }}</option><option value="good">{{ __('maintenance.results.good') }}</option><option value="needs_attention">{{ __('maintenance.results.needs_attention') }}</option><option value="urgent">{{ __('maintenance.results.urgent') }}</option><option value="not_applicable">{{ __('maintenance.results.not_applicable') }}</option></select>
                        </div>
                    @endfor
                    <button type="submit" class="btn btn-primary">{{ __('tenant.save') }}</button>
                </form>
            </div></div></div>
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.templates') }}</h5></div><div class="card-body">
                @forelse($templates as $template)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between gap-2">
                            <div><h6 class="mb-1">{{ $template->name }}</h6><div class="text-muted small">{{ $template->template_number }} · {{ __('maintenance.inspection_types.' . $template->inspection_type) }} · {{ $template->items->count() }} {{ __('maintenance.items') }}</div></div>
                            <span class="badge {{ $template->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $template->is_active ? __('tenant.active') : __('tenant.inactive') }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_templates') }}</p>
                @endforelse
            </div></div></div>
        </div>
    </div></div>
@endsection
