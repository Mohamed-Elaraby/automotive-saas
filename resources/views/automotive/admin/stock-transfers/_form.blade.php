<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <div>
        <label>From Branch</label>
        <select name="from_branch_id" required>
            <option value="">Select source branch</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('from_branch_id') == $branch->id)>
                {{ $branch->name }} ({{ $branch->code }})
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label>To Branch</label>
        <select name="to_branch_id" required>
            <option value="">Select destination branch</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('to_branch_id') == $branch->id)>
                {{ $branch->name }} ({{ $branch->code }})
                </option>
            @endforeach
        </select>
    </div>

    <div style="grid-column:1 / -1;">
        <label>Notes</label>
        <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
    </div>
</div>

<hr style="margin:24px 0;">

<h3>Items</h3>
<p style="color:#6b7280;">Fill at least one row. Leave unused rows empty.</p>

@for ($i = 0; $i < 5; $i++)
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:12px;">
        <div>
            <label>Product</label>
            <select name="items[{{ $i }}][product_id]">
                <option value="">Select product</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected(old("items.$i.product_id") == $product->id)>
                    {{ $product->name }} ({{ $product->sku }})
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label>Quantity</label>
            <input type="number" step="0.001" min="0.001" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity") }}">
        </div>
    </div>
@endfor
