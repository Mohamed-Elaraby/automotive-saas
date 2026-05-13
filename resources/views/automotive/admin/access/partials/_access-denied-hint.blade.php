@php($message = $message ?? __('access.access_action_not_available'))
@php($permission = $permission ?? null)

<span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-bs-title="{{ $permission ? $message . ' ' . __('access.missing_permission') . ': ' . $permission : $message }}">
    <button type="button" class="btn {{ $class ?? 'btn-outline-white' }} pe-none" disabled>
        @if(!empty($icon))
            <i class="isax {{ $icon }} me-1"></i>
        @endif
        {{ $label ?? __('access.not_available') }}
    </button>
</span>
