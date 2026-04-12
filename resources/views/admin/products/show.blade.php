<?php $page = 'products-show'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h5 class="mb-0">Product Builder</h5>
                        <span class="badge {{ $product->is_active ? 'bg-success' : 'bg-secondary' }}">
                            {{ $product->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <span class="badge bg-info">{{ $builderCompletionPercent }}% Ready</span>
                    </div>
                    <p class="text-muted mb-0">Build the full lifecycle for <strong>{{ $product->name }}</strong> from central setup to customer portal visibility.</p>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-outline-white">Edit Product</a>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-white">Back to Products</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row g-4 mb-4">
                <div class="col-xl-8">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3">
                                <div>
                                    <div class="text-muted text-uppercase small mb-1">Product Identity</div>
                                    <h4 class="mb-1">{{ $product->name }}</h4>
                                    <div class="text-muted">{{ $product->description ?: 'No product description yet.' }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted">Code</div>
                                    <div class="fw-semibold">{{ $product->code }}</div>
                                    <div class="small text-muted mt-2">Slug</div>
                                    <div class="fw-semibold">{{ $product->slug }}</div>
                                </div>
                            </div>

                            <div class="progress progress-sm mb-3">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $builderCompletionPercent }}%" aria-valuenow="{{ $builderCompletionPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>

                            <div class="row g-3">
                                @foreach($builderChecklist as $item)
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 h-100 {{ $item['completed'] ? 'border-success bg-success-subtle' : 'border-warning bg-warning-subtle' }}">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <span class="badge {{ $item['completed'] ? 'bg-success' : 'bg-warning text-dark' }}">
                                                    {{ $item['completed'] ? 'Done' : 'Pending' }}
                                                </span>
                                                <div class="fw-semibold">{{ $item['label'] }}</div>
                                            </div>
                                            <div class="text-muted small">{{ $item['description'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="mb-3">Lifecycle Snapshot</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Plans</span>
                                <span class="fw-semibold">{{ $product->plans_count }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Active Plans</span>
                                <span class="fw-semibold">{{ $product->active_plans_count }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Paid Plans</span>
                                <span class="fw-semibold">{{ $product->paid_plans_count }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Trial Plans</span>
                                <span class="fw-semibold">{{ $product->trial_plans_count }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Capabilities</span>
                                <span class="fw-semibold">{{ $product->capabilities_count }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Experience Draft</span>
                                <span class="fw-semibold">{{ empty($experienceDraft) ? 'No' : 'Yes' }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Runtime Modules Draft</span>
                                <span class="fw-semibold">{{ count($runtimeModulesDraft) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Integrations Draft</span>
                                <span class="fw-semibold">{{ count($integrationDraft) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Manifest Workflow</span>
                                <span class="fw-semibold">{{ strtoupper((string) ($manifestWorkflow['status'] ?? 'draft')) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Apply Queue</span>
                                <span class="fw-semibold">{{ strtoupper((string) ($manifestApplyQueue['status'] ?? 'queued')) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subscriptions</span>
                                <span class="fw-semibold">{{ $product->tenant_product_subscriptions_count }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Enablement Requests</span>
                                <span class="fw-semibold">{{ $product->enablement_requests_count }}</span>
                            </div>

                            <hr>

                            <h6 class="mb-2">Workspace Mapping</h6>
                            @if($manifestFamily)
                                <div class="mb-2">
                                    <span class="badge bg-success">Mapped</span>
                                    <span class="ms-2 fw-semibold">{{ $manifestFamily }}</span>
                                </div>
                                <div class="small text-muted mb-2">
                                    {{ data_get($familyDefinition, 'experience.title', 'Workspace family exists in the manifest.') }}
                                </div>
                                <div class="small text-muted">
                                    Runtime modules: {{ count((array) data_get($familyDefinition, 'runtime_modules', [])) }}
                                </div>
                            @else
                                <div class="alert alert-warning mb-0">
                                    This product is not mapped in `config/workspace_products.php` yet, so runtime/sidebar behavior is not fully defined.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-xl-7">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <div>
                                    <h6 class="mb-1">Builder Steps</h6>
                                    <p class="text-muted mb-0">Use these steps to move the product from draft definition to portal-ready lifecycle.</p>
                                </div>
                            </div>

                            <div class="list-group">
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-semibold">1. Base Product Definition</div>
                                            <div class="text-muted small">Identity, code, slug, ordering, and visibility status.</div>
                                        </div>
                                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-semibold">2. Portal Capabilities</div>
                                            <div class="text-muted small">Customer-facing benefits shown in the shared workspace catalog.</div>
                                        </div>
                                        <a href="{{ route('admin.products.capabilities.index', $product) }}" class="btn btn-sm btn-outline-primary">Manage</a>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-semibold">3. Billing Plans</div>
                                            <div class="text-muted small">Trial or paid plans used by the portal checkout and billing flow.</div>
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <a href="{{ route('admin.plans.create', ['product_id' => $product->id]) }}" class="btn btn-sm btn-primary">Add Plan</a>
                                            <a href="{{ route('admin.plans.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-outline-primary">View Plans</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-semibold">4. Workspace Experience Builder</div>
                                            <div class="text-muted small">Capture portal copy, aliases, runtime modules, and planned integrations from the UI.</div>
                                        </div>
                                        <a href="{{ route('admin.products.experience.edit', $product) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-semibold">5. Workspace Runtime Mapping</div>
                                            <div class="text-muted small">Capture structured runtime modules before wiring them into workspace manifest and tenant routes.</div>
                                        </div>
                                        <a href="{{ route('admin.products.runtime-modules.edit', $product) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-semibold">6. Workspace Manifest Wiring</div>
                                            <div class="text-muted small">Link the product to a workspace family, sidebar behavior, and final runtime resolution in code.</div>
                                        </div>
                                        <a href="{{ route('admin.products.manifest-sync.show', $product) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-semibold">7. Manifest Apply Queue</div>
                                            <div class="text-muted small">Track ownership, blockers, and actual code/runtime execution after manifest approval.</div>
                                        </div>
                                        <a href="{{ route('admin.products.manifest-apply-queue.show', $product) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-semibold">8. Integration Builder</div>
                                            <div class="text-muted small">Define cross-product links before wiring them into runtime integration catalogs.</div>
                                        </div>
                                        <a href="{{ route('admin.products.integrations.edit', $product) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-semibold">9. Portal Publication</div>
                                            <div class="text-muted small">When active + capabilities + plans are ready, the product can appear logically in the customer portal.</div>
                                        </div>
                                        <a href="{{ route('admin.products.portal-publication.show', $product) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h6 class="mb-3">Lifecycle Validation</h6>
                            <div class="list-group mb-3">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Portal Publication</span>
                                    <span class="badge {{ $validationSummary['publication']['ready'] ? 'bg-success' : 'bg-warning text-dark' }}">{{ $validationSummary['publication']['ready'] ? 'Ready' : count($validationSummary['publication']['blockers']) . ' blockers' }}</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Manifest Sync Approval</span>
                                    <span class="badge {{ $validationSummary['manifest_sync']['ready'] ? 'bg-success' : 'bg-warning text-dark' }}">{{ $validationSummary['manifest_sync']['ready'] ? 'Ready' : count($validationSummary['manifest_sync']['blockers']) . ' blockers' }}</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Manifest Apply Execution</span>
                                    <span class="badge {{ $validationSummary['apply_queue']['ready'] ? 'bg-success' : 'bg-warning text-dark' }}">{{ $validationSummary['apply_queue']['ready'] ? 'Ready' : count($validationSummary['apply_queue']['blockers']) . ' blockers' }}</span>
                                </div>
                            </div>
                            <div class="small text-muted">
                                Shared lifecycle rules now gate publication, manifest approval, and apply execution.
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Recent Product Plans</h6>
                            @if($latestPlans->isEmpty())
                                <div class="alert alert-light mb-0">No plans exist for this product yet.</div>
                            @else
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Period</th>
                                            <th>Status</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($latestPlans as $plan)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('admin.plans.show', $plan) }}" class="fw-semibold">
                                                        {{ $plan->name }}
                                                    </a>
                                                </td>
                                                <td>{{ ucfirst(str_replace('_', ' ', (string) $plan->billing_period)) }}</td>
                                                <td>
                                                    <span class="badge {{ $plan->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                        {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body border-bottom">
                            <h6 class="mb-3">Lifecycle Audit Trail</h6>
                            @if($auditEntries === [])
                                <div class="alert alert-light mb-0">No lifecycle actions have been recorded for this product yet.</div>
                            @else
                                <div class="list-group">
                                    @foreach($auditEntries as $entry)
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start gap-3">
                                                <div>
                                                    <div class="fw-semibold">{{ $entry['action'] ?? 'unknown' }}</div>
                                                    <div class="small text-muted">Actor: {{ $entry['actor'] ?? 'system' }}</div>
                                                    @if(!empty($entry['details']))
                                                        <div class="small text-muted">Details: {{ json_encode($entry['details'], JSON_UNESCAPED_SLASHES) }}</div>
                                                    @endif
                                                </div>
                                                <div class="small text-muted text-end">{{ $entry['recorded_at'] ?? '-' }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="card-body">
                            <h6 class="mb-3">Portal Outcome</h6>
                            <ul class="mb-0 text-muted ps-3">
                                <li>If the product has active plans, the customer portal can show `Browse Product Plans` after explicit selection.</li>
                                <li>If the product lacks plans, it should stay on enablement/discovery behavior until billing is ready.</li>
                                <li>Once subscribed, the product becomes part of the shared tenant workspace and gets a state-driven CTA.</li>
                                <li>Runtime routes still depend on workspace manifest and tenant-access wiring, not just central product creation.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
