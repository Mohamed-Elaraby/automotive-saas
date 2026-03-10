<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <div>
        <label>Name</label>
        <input type="text" name="name" value="{{ old('name', $product->name) }}" required>
    </div>

    <div>
        <label>SKU</label>
        <input type="text" name="sku" value="{{ old('sku', $product->sku) }}" required>
    </div>

    <div>
        <label>Barcode</label>
        <input type="text" name="barcode" value="{{ old('barcode', $product->barcode) }}">
    </div>

    <div>
        <label>Unit</label>
        <input type="text" name="unit" value="{{ old('unit', $product->unit ?: 'pcs') }}" required>
    </div>

    <div>
        <label>Cost Price</label>
        <input type="number" step="0.01" min="0" name="cost_price" value="{{ old('cost_price', $product->cost_price ?? 0) }}" required>
    </div>

    <div>
        <label>Sale Price</label>
        <input type="number" step="0.01" min="0" name="sale_price" value="{{ old('sale_price', $product->sale_price ?? 0) }}" required>
    </div>

    <div>
        <label>Min Stock Alert</label>
        <input type="number" min="0" name="min_stock_alert" value="{{ old('min_stock_alert', $product->min_stock_alert ?? 0) }}">
    </div>

    <div style="display:flex;align-items:end;">
        <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $product->is_active ?? true) ? 'checked' : '' }}>
            Active
        </label>
    </div>

    <div style="grid-column:1 / -1;">
        <label>Description</label>
        <textarea name="description" rows="4">{{ old('description', $product->description) }}</textarea>
    </div>
</div>
