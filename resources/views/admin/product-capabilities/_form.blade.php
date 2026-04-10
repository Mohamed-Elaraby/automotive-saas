<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Code</label>
        <input type="text" name="code" value="{{ old('code', $capability->code) }}" class="form-control" required>
        @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Name</label>
        <input type="text" name="name" value="{{ old('name', $capability->name) }}" class="form-control" required>
        @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Slug</label>
        <input type="text" name="slug" value="{{ old('slug', $capability->slug) }}" class="form-control" required>
        @error('slug')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Sort Order</label>
        <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $capability->sort_order ?? 0) }}" class="form-control" required>
        @error('sort_order')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="is_active" class="form-select" required>
            <option value="1" @selected((int) old('is_active', $capability->is_active ? 1 : 0) === 1)>Active</option>
            <option value="0" @selected((int) old('is_active', $capability->is_active ? 1 : 0) === 0)>Inactive</option>
        </select>
        @error('is_active')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" rows="4" class="form-control">{{ old('description', $capability->description) }}</textarea>
        @error('description')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
</div>
