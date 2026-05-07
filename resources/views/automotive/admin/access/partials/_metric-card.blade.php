<div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
    <div class="card flex-fill">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-gray-6">{{ $label }}</span>
                <span class="avatar avatar-sm {{ $iconBg ?? 'bg-primary-transparent' }} rounded-circle">
                    <i class="isax {{ $icon }} {{ $iconColor ?? 'text-primary' }}"></i>
                </span>
            </div>
            <h3 class="mb-1">{{ $value }}</h3>
            <p class="mb-0 text-muted">{{ $hint }}</p>
        </div>
    </div>
</div>
