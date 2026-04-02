@php
    $selectedFeatureIds = collect(old(
        'feature_ids',
        $plan->relationLoaded('billingFeatures')
            ? $plan->billingFeatures->pluck('id')->all()
            : $plan->billingFeatures()->pluck('billing_features.id')->all()
    ))->map(fn ($id) => (int) $id)->all();
@endphp

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Plan Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" value="{{ old('name', $plan->name) }}" required>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="slug" value="{{ old('slug', $plan->slug) }}" required>
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Price <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" class="form-control" name="price" value="{{ old('price', $plan->price) }}" required>
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-uppercase" maxlength="3" name="currency" value="{{ old('currency', $plan->currency ?: 'USD') }}" required>
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Billing Period <span class="text-danger">*</span></label>
                <select name="billing_period" class="form-select" required>
                    @foreach (['trial', 'monthly', 'yearly', 'one_time'] as $period)
                        <option value="{{ $period }}" @selected(old('billing_period', $plan->billing_period) === $period)>
                            {{ ucfirst(str_replace('_', ' ', $period)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Sort Order <span class="text-danger">*</span></label>
                <input type="number" min="0" class="form-control" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" required>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Stripe Price ID</label>
                <input type="text" class="form-control" name="stripe_price_id" value="{{ old('stripe_price_id', $plan->stripe_price_id) }}" placeholder="price_xxxxxxxxxxxxxxxxx">
                <small class="text-muted">Leave empty for trial plans or plans not linked to Stripe yet.</small>
            </div>

            <div class="col-md-6 mb-3 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="is_active">Active</label>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Max Users</label>
                <input type="number" min="1" class="form-control" name="max_users" value="{{ old('max_users', $plan->max_users) }}">
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Max Branches</label>
                <input type="number" min="1" class="form-control" name="max_branches" value="{{ old('max_branches', $plan->max_branches) }}">
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Max Products</label>
                <input type="number" min="1" class="form-control" name="max_products" value="{{ old('max_products', $plan->max_products) }}">
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Max Storage (MB)</label>
                <input type="number" min="1" class="form-control" name="max_storage_mb" value="{{ old('max_storage_mb', $plan->max_storage_mb) }}">
            </div>

            <div class="col-12 mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" rows="4" class="form-control">{{ old('description', $plan->description) }}</textarea>
            </div>

            <div class="col-12 mb-0">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <label class="form-label fw-semibold mb-1">Plan Features</label>
                        <p class="text-muted mb-0">Choose from the shared billing feature catalog used across all plans.</p>
                    </div>
                    <a href="{{ route('admin.billing-features.index') }}" class="btn btn-outline-white btn-sm">
                        Manage Features Catalog
                    </a>
                </div>

                <div class="border rounded p-3 bg-light">
                    @if($availableFeatures->isEmpty())
                        <div class="text-muted">
                            No billing features are available yet. Create them first from the features catalog.
                        </div>
                    @else
                        <div class="row">
                            @foreach($availableFeatures as $feature)
                                <div class="col-lg-4 col-md-6">
                                    <div class="form-check form-checkbox-success mb-3">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            name="feature_ids[]"
                                            value="{{ $feature->id }}"
                                            id="feature_{{ $feature->id }}"
                                            @checked(in_array((int) $feature->id, $selectedFeatureIds, true))
                                        >
                                        <label class="form-check-label" for="feature_{{ $feature->id }}">
                                            <span class="d-block fw-semibold text-dark">{{ $feature->name }}</span>
                                            <span class="d-block text-muted small">
                                                {{ $feature->description ?: ('Slug: ' . $feature->slug) }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <small class="text-muted">Use the catalog so the same feature names stay consistent across all plans.</small>
            </div>
        </div>
    </div>
</div>
