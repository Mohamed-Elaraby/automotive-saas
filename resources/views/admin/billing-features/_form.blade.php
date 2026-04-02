<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Feature Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" value="{{ old('name', $feature->name) }}" required>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="slug" value="{{ old('slug', $feature->slug) }}" required>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Sort Order <span class="text-danger">*</span></label>
                <input type="number" min="0" class="form-control" name="sort_order" value="{{ old('sort_order', $feature->sort_order ?? 0) }}" required>
            </div>

            <div class="col-md-6 mb-3 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="feature_is_active" {{ old('is_active', $feature->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="feature_is_active">Active</label>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" rows="5" class="form-control">{{ old('description', $feature->description) }}</textarea>
            </div>
        </div>
    </div>
</div>
