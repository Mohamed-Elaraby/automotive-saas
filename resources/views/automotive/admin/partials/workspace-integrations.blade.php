@if(!empty($workspaceIntegrations))
    @php
        $renderableWorkspaceIntegrations = collect($workspaceIntegrations)
            ->filter(fn (array $integration): bool => Route::has((string) ($integration['target_route'] ?? '')))
            ->values();
    @endphp
@endif

@if(!empty($workspaceIntegrations) && $renderableWorkspaceIntegrations->isNotEmpty())
    <div class="row">
        <div class="col-xl-12 d-flex">
            <div class="card flex-fill">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="card-title mb-1">{{ $title ?? 'Connected Product Integrations' }}</h5>
                        <p class="text-muted mb-0 small">Available because both products are active in this workspace.</p>
                    </div>
                    <span class="badge bg-primary-transparent">{{ count($workspaceIntegrations) }} Connected</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($renderableWorkspaceIntegrations as $integration)
                            <div class="{{ $columnClass ?? 'col-xl-4' }} d-flex">
                                <div class="border rounded p-3 flex-fill mb-3">
                                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                        <h6 class="mb-0">{{ $integration['title'] }}</h6>
                                        <span class="badge bg-success-transparent text-success">
                                            {{ $integration['target_status_label'] ?? 'ACTIVE' }}
                                        </span>
                                    </div>
                                    @if(!empty($integration['description']))
                                        <p class="text-muted mb-3">{{ $integration['description'] }}</p>
                                    @endif
                                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                        <span class="text-muted small">
                                            Target: {{ $integration['target_product_name'] ?? $integration['target_family'] }}
                                        </span>
                                        <a href="{{ route($integration['target_route'], $integration['target_params'] ?? []) }}" class="btn btn-outline-light">
                                            {{ $integration['target_label'] }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
