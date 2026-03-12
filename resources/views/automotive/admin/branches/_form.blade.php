@csrf

<div class="row">
    <div class="col-lg-6 col-md-12">
        <div class="form-group mb-3">
            <label class="form-label">Name <span class="text-danger">*</span></label>
            <input
                type="text"
                name="name"
                class="form-control @error('name') is-invalid @enderror"
                value="{{ old('name', $branch->name ?? '') }}"
                required
            >
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-lg-6 col-md-12">
        <div class="form-group mb-3">
            <label class="form-label">Code</label>
            <input
                type="text"
                name="code"
                class="form-control @error('code') is-invalid @enderror"
                value="{{ old('code', $branch->code ?? '') }}"
            >
            @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-lg-6 col-md-12">
        <div class="form-group mb-3">
            <label class="form-label">Phone</label>
            <input
                type="text"
                name="phone"
                class="form-control @error('phone') is-invalid @enderror"
                value="{{ old('phone', $branch->phone ?? '') }}"
            >
            @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-lg-6 col-md-12">
        <div class="form-group mb-3">
            <label class="form-label">Email</label>
            <input
                type="email"
                name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email', $branch->email ?? '') }}"
            >
            @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-12">
        <div class="form-group mb-3">
            <label class="form-label">Address</label>
            <textarea
                name="address"
                rows="3"
                class="form-control @error('address') is-invalid @enderror"
            >{{ old('address', $branch->address ?? '') }}</textarea>
            @error('address')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-lg-4 col-md-6">
        <div class="form-group mb-3">
            <label class="form-label">Min Stock Alert Default</label>
            <input
                type="number"
                step="0.01"
                min="0"
                name="min_stock_alert"
                class="form-control @error('min_stock_alert') is-invalid @enderror"
                value="{{ old('min_stock_alert', $branch->min_stock_alert ?? 0) }}"
            >
            @error('min_stock_alert')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-lg-4 col-md-6">
        <div class="form-group mb-3">
            <label class="form-label">Status <span class="text-danger">*</span></label>
            <select name="is_active" class="form-control @error('is_active') is-invalid @enderror" required>
                <option value="1" {{ (string) old('is_active', $branch->is_active ?? 1) === '1' ? 'selected' : '' }}>
                    Active
                </option>
                <option value="0" {{ (string) old('is_active', $branch->is_active ?? 1) === '0' ? 'selected' : '' }}>
                    Inactive
                </option>
            </select>
            @error('is_active')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-12">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="isax isax-save-2 me-1"></i> Save
            </button>

            <a href="{{ route('automotive.admin.branches.index') }}" class="btn btn-light">
                Cancel
            </a>
        </div>
    </div>
</div>
