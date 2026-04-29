<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('admin.code') }}</label>
        <input type="text" name="code" value="{{ old('code', $product->code) }}" class="form-control" required>
        @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('admin.name') }}</label>
        <input type="text" name="name" value="{{ old('name', $product->name) }}" class="form-control" required>
        @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('admin.slug') }}</label>
        <input type="text" name="slug" value="{{ old('slug', $product->slug) }}" class="form-control" required>
        @error('slug')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('admin.sort_order') }}</label>
        <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $product->sort_order ?? 0) }}" class="form-control" required>
        @error('sort_order')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('admin.status') }}</label>
        <select name="is_active" class="form-select" required>
            <option value="1" @selected((int) old('is_active', $product->is_active ? 1 : 0) === 1)>{{ __('admin.active') }}</option>
            <option value="0" @selected((int) old('is_active', $product->is_active ? 1 : 0) === 0)>{{ __('admin.inactive') }}</option>
        </select>
        @error('is_active')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label">{{ __('admin.description') }}</label>
        <textarea name="description" rows="4" class="form-control">{{ old('description', $product->description) }}</textarea>
        @error('description')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
</div>
