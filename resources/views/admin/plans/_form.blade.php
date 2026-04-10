@php
    $selectedFeatureIds = collect(old(
        'feature_ids',
        $plan->relationLoaded('billingFeatures')
            ? $plan->billingFeatures->pluck('id')->all()
            : $plan->billingFeatures()->pluck('billing_features.id')->all()
    ))->map(fn ($id) => (int) $id)->all();
    $previewPrice = old('price', $plan->price);
    $previewCurrency = old('currency', $plan->currency ?: 'USD');
    $previewBillingPeriod = old('billing_period', $plan->billing_period ?: 'monthly');
    $previewProductId = (int) old('product_id', $plan->product_id);
@endphp

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Plan Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" value="{{ old('name', $plan->name) }}" required data-plan-preview="name">
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
                <select name="product_id" class="form-select" required data-plan-preview="product">
                    <option value="">Select a product</option>
                    @foreach($availableProducts as $product)
                        <option
                            value="{{ $product->id }}"
                            data-product-name="{{ $product->name }}"
                            @selected($previewProductId === (int) $product->id)
                        >
                            {{ $product->name }} @if($product->code)({{ $product->code }})@endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="slug" value="{{ old('slug', $plan->slug) }}" required>
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Price <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" class="form-control" name="price" value="{{ $previewPrice }}" required data-plan-preview="price">
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-uppercase" maxlength="3" name="currency" value="{{ $previewCurrency }}" required data-plan-preview="currency">
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Billing Period <span class="text-danger">*</span></label>
                <select name="billing_period" class="form-select" required data-plan-preview="billing_period">
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
                <input type="number" min="1" class="form-control" name="max_users" value="{{ old('max_users', $plan->max_users) }}" data-plan-preview="max_users">
                <small class="text-muted d-block mt-1">Leave empty to hide a user cap from the portal card. If filled, this is the total active users allowed in the tenant workspace.</small>
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Max Branches</label>
                <input type="number" min="1" class="form-control" name="max_branches" value="{{ old('max_branches', $plan->max_branches) }}" data-plan-preview="max_branches">
                <small class="text-muted d-block mt-1">Leave empty when branch count should not be advertised as a packaged limit. If filled, it is the maximum branch records the tenant can operate.</small>
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Max Products</label>
                <input type="number" min="1" class="form-control" name="max_products" value="{{ old('max_products', $plan->max_products) }}" data-plan-preview="max_products">
                <small class="text-muted d-block mt-1">Use this for the catalog ceiling customers are buying. Empty means the limit line stays out of the preview instead of showing a misleading placeholder.</small>
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold">Max Storage (MB)</label>
                <input type="number" min="1" class="form-control" name="max_storage_mb" value="{{ old('max_storage_mb', $plan->max_storage_mb) }}" data-plan-preview="max_storage_mb">
                <small class="text-muted d-block mt-1">Storage is shown in MB in admin but appears as a customer-facing storage line in the portal preview. Leave empty if storage should not be marketed as a plan limit.</small>
            </div>

            <div class="col-12 mb-3">
                <div class="rounded border bg-light p-3">
                    <h6 class="mb-2">Limits Semantics</h6>
                    <ul class="mb-0 text-muted ps-3">
                        <li>Only filled limits appear in the portal preview and paid plan cards.</li>
                        <li>Empty does not mean zero. It means "do not advertise or enforce from this plan field".</li>
                        <li>Use plan features for capabilities, and numeric limits only for measurable caps.</li>
                    </ul>
                </div>
            </div>

            <div class="col-12 mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" rows="4" class="form-control" data-plan-preview="description">{{ old('description', $plan->description) }}</textarea>
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
                                            data-plan-preview="feature"
                                            data-feature-name="{{ $feature->name }}"
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

            <div class="col-12 mt-4">
                <div class="card border">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h6 class="mb-1">Portal Preview</h6>
                            <p class="text-muted mb-0">This mirrors the customer portal card before you save the plan.</p>
                        </div>
                        <span class="badge badge-soft-info" id="plan-preview-billing-label">
                            {{ ucfirst(str_replace('_', ' ', (string) $previewBillingPeriod)) }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-5">
                                <div class="card pricing-starter h-100 mb-0">
                                    <div class="card-body d-flex flex-column">
                                        <div class="border-bottom">
                                            <div class="mb-3">
                                                <div class="d-flex align-items-center justify-content-between gap-2">
                                                    <div>
                                                        <div class="text-muted small mb-1" id="plan-preview-product">
                                                            @php
                                                                $previewProduct = $availableProducts->firstWhere('id', $previewProductId);
                                                            @endphp
                                                            {{ $previewProduct?->name ?: 'Select a product' }}
                                                        </div>
                                                        <h5 class="mb-1" id="plan-preview-name">{{ old('name', $plan->name ?: 'New Plan') }}</h5>
                                                        <p class="mb-0" id="plan-preview-description">{{ old('description', $plan->description ?: 'Plan description will appear here.') }}</p>
                                                    </div>
                                                    <span class="badge bg-soft-info text-info">Preview</span>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <h3 class="d-flex align-items-center mb-1">
                                                    <span id="plan-preview-price">{{ number_format((float) ($previewPrice ?: 0), 2) }}</span>
                                                    <span class="fs-14 fw-normal text-gray-9 ms-1" id="plan-preview-price-suffix">
                                                        @if($previewBillingPeriod === 'yearly')
                                                            /year
                                                        @elseif($previewBillingPeriod === 'one_time')
                                                            one-time
                                                        @elseif($previewBillingPeriod === 'trial')
                                                            /trial
                                                        @else
                                                            /month
                                                        @endif
                                                    </span>
                                                </h3>
                                                <p class="mb-0" id="plan-preview-currency">{{ strtoupper((string) $previewCurrency) }} billing</p>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <div class="mb-1">
                                                <h6 class="fs-16 mb-2">What you get:</h6>
                                            </div>
                                            <div id="plan-preview-list">
                                                <p class="text-dark d-flex align-items-center mb-2 text-truncate">
                                                    <i class="isax isax-tick-circle me-2"></i><span>No plan details yet.</span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-7">
                                <div class="rounded border bg-light p-3 h-100">
                                    <h6 class="mb-3">Preview Notes</h6>
                                    <p class="text-muted mb-3">
                                        Limits and selected features are merged into one customer-facing list, just like the paid plan cards in the portal.
                                    </p>
                                    <ul class="mb-0 text-muted ps-3">
                                        <li>Empty numeric limits are hidden from customers.</li>
                                        <li>Use empty fields when a cap is not part of the sales message, not when the value is zero.</li>
                                        <li>Checked features appear after the limits.</li>
                                        <li>Trial plans force price to `0.00` when saved.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('form');

        if (!form) {
            return;
        }

        const getField = (name) => form.querySelector('[name="' + name + '"]');
        const previewName = document.getElementById('plan-preview-name');
        const previewProduct = document.getElementById('plan-preview-product');
        const previewDescription = document.getElementById('plan-preview-description');
        const previewPrice = document.getElementById('plan-preview-price');
        const previewCurrency = document.getElementById('plan-preview-currency');
        const previewPriceSuffix = document.getElementById('plan-preview-price-suffix');
        const previewBillingLabel = document.getElementById('plan-preview-billing-label');
        const previewList = document.getElementById('plan-preview-list');

        const billingLabel = {
            trial: 'Trial',
            monthly: 'Monthly',
            yearly: 'Yearly',
            one_time: 'One Time'
        };

        const billingSuffix = {
            trial: '/trial',
            monthly: '/month',
            yearly: '/year',
            one_time: 'one-time'
        };

        const formatPrice = (value) => {
            const numeric = parseFloat(value || '0');

            if (Number.isNaN(numeric)) {
                return '0.00';
            }

            return numeric.toFixed(2);
        };

        const collectLimitLines = () => {
            const limits = [
                ['max_users', 'Users'],
                ['max_branches', 'Branches'],
                ['max_products', 'Products'],
                ['max_storage_mb', 'Storage']
            ];

            return limits.map(([fieldName, label]) => {
                const field = getField(fieldName);

                if (!field || !field.value) {
                    return null;
                }

                return label + ' ' + field.value + (label === 'Storage' ? ' MB' : '');
            }).filter(Boolean);
        };

        const collectFeatureLines = () => {
            return Array.from(form.querySelectorAll('input[name="feature_ids[]"]:checked'))
                .map((checkbox) => checkbox.dataset.featureName || '')
                .filter(Boolean);
        };

        const renderPreviewList = () => {
            const items = collectLimitLines().concat(collectFeatureLines());

            if (items.length === 0) {
                previewList.innerHTML = '<p class="text-dark d-flex align-items-center mb-2 text-truncate"><i class="isax isax-tick-circle me-2"></i><span>No plan details yet.</span></p>';
                return;
            }

            previewList.innerHTML = items.map((item) => {
                return '<p class="text-dark d-flex align-items-center mb-2 text-truncate"><i class="isax isax-tick-circle me-2"></i><span>' + item + '</span></p>';
            }).join('');
        };

        const renderPreviewMeta = () => {
            const nameField = getField('name');
            const productField = getField('product_id');
            const descriptionField = getField('description');
            const priceField = getField('price');
            const currencyField = getField('currency');
            const billingField = getField('billing_period');
            const billingValue = billingField ? billingField.value : 'monthly';
            const selectedProductOption = productField ? productField.options[productField.selectedIndex] : null;

            previewProduct.textContent = selectedProductOption && selectedProductOption.value
                ? (selectedProductOption.dataset.productName || selectedProductOption.textContent.trim())
                : 'Select a product';
            previewName.textContent = (nameField && nameField.value.trim()) ? nameField.value.trim() : 'New Plan';
            previewDescription.textContent = (descriptionField && descriptionField.value.trim()) ? descriptionField.value.trim() : 'Plan description will appear here.';
            previewPrice.textContent = billingValue === 'trial' ? '0.00' : formatPrice(priceField ? priceField.value : '0');
            previewCurrency.textContent = ((currencyField ? currencyField.value : 'USD') || 'USD').toUpperCase() + ' billing';
            previewPriceSuffix.textContent = billingSuffix[billingValue] || '/month';
            previewBillingLabel.textContent = billingLabel[billingValue] || 'Monthly';
        };

        const renderAll = () => {
            renderPreviewMeta();
            renderPreviewList();
        };

        form.querySelectorAll('[data-plan-preview]').forEach((field) => {
            const eventName = field.type === 'checkbox' ? 'change' : 'input';
            field.addEventListener(eventName, renderAll);
            if (field.tagName === 'SELECT') {
                field.addEventListener('change', renderAll);
            }
        });

        renderAll();
    });
</script>
