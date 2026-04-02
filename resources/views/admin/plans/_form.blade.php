@php
    $planFeatureLines = old(
        'features_text',
        collect($plan->relationLoaded('planFeatures') ? $plan->planFeatures : ($plan->planFeatures()->get() ?? collect()))
            ->pluck('title')
            ->filter()
            ->implode(PHP_EOL)
    );
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
                <label class="form-label fw-semibold">Plan Features</label>
                <textarea name="features_text" rows="8" class="form-control" placeholder="Inventory management&#10;Barcode support&#10;Advanced reports">{{ $planFeatureLines }}</textarea>
                <small class="text-muted">Add one feature per line. These lines will be stored as separate feature records for the plan.</small>
            </div>
        </div>
    </div>
</div>
