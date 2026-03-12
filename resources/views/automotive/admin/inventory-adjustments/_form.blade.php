@csrf

@php
    $branches = $branches ?? collect();
    $products = $products ?? collect();
@endphp

<div class="row">
    <div class="col-lg-4 col-md-6">
        <div class="form-group mb-3">
            <label class="form-label">Type <span class="text-danger">*</span></label>
            <select name="type" class="form-control @error('type') is-invalid @enderror" required>
                <option value="">Select type</option>
                <option value="opening" {{ old('type', $inventoryAdjustment->type ?? '') === 'opening' ? 'selected' : '' }}>
                    Opening
                </option>
                <option value="adjustment_in" {{ old('type', $inventoryAdjustment->type ?? '') === 'adjustment_in' ? 'selected' : '' }}>
                    Adjustment In
                </option>
                <option value="adjustment_out" {{ old('type', $inventoryAdjustment->type ?? '') === 'adjustment_out' ? 'selected' : '' }}>
                    Adjustment Out
                </option>
            </select>
            @error('type')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-lg-4 col-md-6">
        <div class="form-group mb-3">
            <label class="form-label">Branch <span class="text-danger">*</span></label>
            <select name="branch_id" class="form-control @error('branch_id') is-invalid @enderror" required>
                <option value="">Select branch</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}"
                        {{ (string) old('branch_id', $inventoryAdjustment->branch_id ?? '') === (string) $branch->id ? 'selected' : '' }}>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
            @error('branch_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-lg-4 col-md-12">
        <div class="form-group mb-3">
            <label class="form-label">Product <span class="text-danger">*</span></label>
            <select name="product_id" class="form-control @error('product_id') is-invalid @enderror" required>
                <option value="">Select product</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}"
                        {{ (string) old('product_id', $inventoryAdjustment->product_id ?? '') === (string) $product->id ? 'selected' : '' }}>
                        {{ $product->name }}
                    </option>
                @endforeach
            </select>
            @error('product_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-lg-4 col-md-6">
        <div class="form-group mb-3">
            <label class="form-label">Quantity <span class="text-danger">*</span></label>
            <input
                type="number"
                step="0.01"
                min="0.01"
                name="quantity"
                value="{{ old('quantity', $inventoryAdjustment->quantity ?? '') }}"
                class="form-control @error('quantity') is-invalid @enderror"
                required
            >
            @error('quantity')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-12">
        <div class="form-group mb-3">
            <label class="form-label">Notes</label>
            <textarea
                name="notes"
                rows="3"
                class="form-control @error('notes') is-invalid @enderror"
            >{{ old('notes', $inventoryAdjustment->notes ?? '') }}</textarea>
            @error('notes')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-12">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="isax isax-save-2 me-1"></i> Save
            </button>

            <a href="{{ route('automotive.admin.inventory-adjustments.index') }}" class="btn btn-light">
                Cancel
            </a>
        </div>
    </div>
</div>
