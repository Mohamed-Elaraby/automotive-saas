@php
    $featuresJson = old('features_json', $plan->features ? json_encode($plan->features, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '');
@endphp

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <div>
        <label>Name</label>
        <input type="text" name="name" value="{{ old('name', $plan->name) }}" required>
    </div>

    <div>
        <label>Slug</label>
        <input type="text" name="slug" value="{{ old('slug', $plan->slug) }}" required>
    </div>

    <div>
        <label>Price</label>
        <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $plan->price) }}" required>
    </div>

    <div>
        <label>Currency</label>
        <input type="text" name="currency" value="{{ old('currency', $plan->currency ?: 'AED') }}" maxlength="3" required>
    </div>

    <div>
        <label>Billing Period</label>
        <select name="billing_period" required>
            @foreach (['trial', 'monthly', 'yearly', 'one_time'] as $period)
                <option value="{{ $period }}" @selected(old('billing_period', $plan->billing_period) === $period)>
                {{ ucfirst(str_replace('_', ' ', $period)) }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label>Sort Order</label>
        <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" required>
    </div>

    <div>
        <label>Max Users</label>
        <input type="number" min="1" name="max_users" value="{{ old('max_users', $plan->max_users) }}">
    </div>

    <div>
        <label>Max Branches</label>
        <input type="number" min="1" name="max_branches" value="{{ old('max_branches', $plan->max_branches) }}">
    </div>

    <div>
        <label>Max Products</label>
        <input type="number" min="1" name="max_products" value="{{ old('max_products', $plan->max_products) }}">
    </div>

    <div>
        <label>Max Storage (MB)</label>
        <input type="number" min="1" name="max_storage_mb" value="{{ old('max_storage_mb', $plan->max_storage_mb) }}">
    </div>

    <div style="grid-column:1 / -1;">
        <label>Description</label>
        <textarea name="description" rows="4">{{ old('description', $plan->description) }}</textarea>
    </div>

    <div style="grid-column:1 / -1;">
        <label>Features JSON</label>
        <textarea name="features_json" rows="12" placeholder='{"invoicing": true, "inventory": true}'>{{ $featuresJson }}</textarea>
    </div>

    <div style="grid-column:1 / -1;">
        <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}>
            Active
        </label>
    </div>
</div>
