@php
    $status = $billingState['status'] ?? 'unknown';

    $badgeClass = match ($status) {
        'trialing' => 'bg-info text-dark',
        'active' => 'bg-success',
        'past_due' => 'bg-warning text-dark',
        'grace_period' => 'bg-warning text-dark',
        'suspended' => 'bg-danger',
        'cancelled' => 'bg-secondary',
        'expired' => 'bg-danger',
        default => 'bg-dark',
    };
@endphp

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <h5 class="mb-0">Subscription Status</h5>
            <span class="badge {{ $badgeClass }}">
                {{ ucfirst(str_replace('_', ' ', $status)) }}
            </span>
        </div>

        <p class="mb-3">{{ $billingState['message'] ?? '-' }}</p>

        <div class="row">
            <div class="col-md-6">
                <p class="mb-2">
                    <strong>Current Plan:</strong>
                    {{ $plan->name ?? 'N/A' }}
                </p>
                <p class="mb-2">
                    <strong>Allow Access:</strong>
                    {{ !empty($billingState['allow_access']) ? 'Yes' : 'No' }}
                </p>
                <p class="mb-2">
                    <strong>Trial:</strong>
                    {{ !empty($billingState['is_trial']) ? 'Yes' : 'No' }}
                </p>
            </div>

            <div class="col-md-6">
                <p class="mb-2">
                    <strong>Period Ends At:</strong>
                    {{ optional($billingState['period_ends_at'] ?? null)?->format('Y-m-d H:i') ?? '-' }}
                </p>
                <p class="mb-2">
                    <strong>Grace Ends At:</strong>
                    {{ optional($billingState['grace_ends_at'] ?? null)?->format('Y-m-d H:i') ?? '-' }}
                </p>
            </div>
        </div>

        @if(in_array($status, ['grace_period', 'past_due', 'suspended', 'expired'], true))
            <div class="alert alert-warning mt-3 mb-0">
                Billing action is required to restore or maintain tenant access.
            </div>
        @endif
    </div>
</div>
