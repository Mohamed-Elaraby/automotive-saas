@php($metadata = $role->metadata ?? [])

<div class="row">
    <div class="col-lg-6">
        <div class="mb-3">
            <label class="form-label">{{ __('access.product') }} <span class="text-danger">*</span></label>
            <select name="product_key" class="form-select" @disabled($role->exists && $role->is_system)>
                @foreach($productOptions as $product)
                    <option value="{{ $product['key'] }}" @selected(old('product_key', $role->product_key) === $product['key'])>{{ $product['name'] }} ({{ $product['key'] }})</option>
                @endforeach
            </select>
            @if($role->exists && $role->is_system)
                <input type="hidden" name="product_key" value="{{ $role->product_key }}">
            @endif
        </div>
    </div>
    <div class="col-lg-6">
        <div class="mb-3">
            <label class="form-label">{{ __('access.role_name') }} <span class="text-danger">*</span></label>
            <input type="text" name="name" value="{{ old('name', $role->name) }}" class="form-control" @readonly($role->exists && $role->is_system)>
            @if($role->exists && $role->is_system)
                <input type="hidden" name="name" value="{{ $role->name }}">
            @endif
        </div>
    </div>
    <div class="col-12">
        <div class="mb-3">
            <label class="form-label">{{ __('tenant.description') }}</label>
            <textarea name="description" rows="4" class="form-control">{{ old('description', $role->description) }}</textarea>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="mb-3">
            <label class="form-label">{{ __('tenant.status') }}</label>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="role-active" @checked(old('is_active', $role->is_active ?? true))>
                <label class="form-check-label" for="role-active">{{ __('tenant.active') }}</label>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="mb-3">
            <label class="form-label">{{ __('access.template_role') }}</label>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_template" value="1" id="role-template" @checked(old('is_template', $metadata['is_template'] ?? false))>
                <label class="form-check-label" for="role-template">{{ __('access.available_as_template') }}</label>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="mb-3">
            <label class="form-label">{{ __('access.sort_order') }}</label>
            <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $metadata['sort_order'] ?? 0) }}" class="form-control">
        </div>
    </div>
</div>

@if($role->exists && $role->is_system)
    <div class="alert alert-warning">{{ __('access.system_role_edit_warning') }}</div>
@endif

<div class="d-flex justify-content-end gap-2">
    <a href="{{ route('automotive.admin.access.roles.index') }}" class="btn btn-outline-white">{{ __('tenant.cancel') }}</a>
    <button type="submit" class="btn btn-primary d-inline-flex align-items-center">
        <i class="isax isax-save-2 me-1"></i>{{ __('tenant.save') }}
    </button>
</div>
