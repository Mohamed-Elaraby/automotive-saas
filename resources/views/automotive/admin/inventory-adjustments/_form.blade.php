<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <div>
        <label>Branch</label>
        <select name="branch_id" required>
            <option value="">Select branch</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>
                {{ $branch->name }} ({{ $branch->code }})
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label>Product</label>
        <select name="product_id" required>
            <option value="">Select product</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                {{ $product->name }} ({{ $product->sku }})
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label>Type</label>
        <select name="type" required>
            <option value="">Select type</option>
            <option value="opening" @selected(old('type') === 'opening')>Opening Stock</option>
            <option value="adjustment_in" @selected(old('type') === 'adjustment_in')>Adjustment In</option>
            <option value="adjustment_out" @selected(old('type') === 'adjustment_out')>Adjustment Out</option>
        </select>
    </div>

    <div>
        <label>Quantity</label>
        <input type="number" step="0.001" min="0.001" name="quantity" value="{{ old('quantity') }}" required>
    </div>

    <div style="grid-column:1 / -1;">
        <label>Notes</label>
        <textarea name="notes" rows="4">{{ old('notes') }}</textarea>
    </div>
</div>
