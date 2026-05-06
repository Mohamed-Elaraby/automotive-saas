@php
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit';
@endphp

@csrf

<div class="row">
    <div class="col-lg-6 col-md-12">
        <div class="form-group mb-3">
            <label class="form-label">{{ __('tenant.name') }} <span class="text-danger">*</span></label>
            <input
                type="text"
                name="name"
                class="form-control @error('name') is-invalid @enderror"
                value="{{ old('name', $user->name ?? '') }}"
                required
            >
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-lg-6 col-md-12">
        <div class="form-group mb-3">
            <label class="form-label">{{ __('tenant.email') }} <span class="text-danger">*</span></label>
            <input
                type="email"
                name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email', $user->email ?? '') }}"
                required
            >
            @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-lg-6 col-md-12">
        <div class="form-group mb-3">
            <label class="form-label">
                {{ __('tenant.password') }}
                @if(!$isEdit)
                    <span class="text-danger">*</span>
                @endif
                @if($isEdit)
                    <small class="text-muted">{{ __('tenant.password_keep_current') }}</small>
                @endif
            </label>

            <input
                type="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                {{ $isEdit ? '' : 'required' }}
            >

            @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-lg-6 col-md-12">
        <div class="form-group mb-3">
            <label class="form-label">
                {{ __('tenant.password_confirmation') }}
                @if(!$isEdit)
                    <span class="text-danger">*</span>
                @endif
            </label>
            <input
                type="password"
                name="password_confirmation"
                class="form-control"
                {{ $isEdit ? '' : 'required' }}
            >
        </div>
    </div>

    @if($supportsMaintenanceAccess ?? false)
        <div class="col-lg-6 col-md-12">
            <div class="form-group mb-3">
                <label class="form-label">{{ __('tenant.maintenance_role') }}</label>
                <select
                    name="maintenance_role"
                    class="form-select @error('maintenance_role') is-invalid @enderror"
                >
                    <option value="">{{ __('tenant.legacy_full_access') }}</option>
                    @foreach($roleLabels as $role => $label)
                        <option value="{{ $role }}" @selected(old('maintenance_role', $user->maintenance_role ?? '') === $role)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">{{ __('tenant.maintenance_role_help') }}</div>
                @error('maintenance_role')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="col-lg-6 col-md-12">
            <div class="form-group mb-3">
                <label class="form-label">{{ __('tenant.maintenance_permissions') }}</label>
                <div class="form-control-plaintext">
                    <a href="{{ route('automotive.admin.maintenance.settings.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="isax isax-shield-tick me-1"></i> {{ __('tenant.manage_detailed_permissions') }}
                    </a>
                </div>
                <div class="form-text">{{ __('tenant.maintenance_permissions_help') }}</div>
            </div>
        </div>
    @endif

    <div class="col-12">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="isax isax-save-2 me-1"></i> {{ __('tenant.save') }}
            </button>

            <a href="{{ route('automotive.admin.users.index') }}" class="btn btn-light">
                {{ __('tenant.cancel') }}
            </a>
        </div>
    </div>
</div>
