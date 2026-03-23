<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Country</label>
        <select name="country_id" class="form-select" required>
            <option value="">Select</option>
            @foreach($countries as $country)
                <option value="{{ $country->id }}" {{ (int) old('country_id', $city->country_id) === (int) $country->id ? 'selected' : '' }}>
                    {{ $country->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">State</label>
        <select name="state_id" class="form-select" required>
            <option value="">Select</option>
            @foreach($states as $state)
                <option value="{{ $state->id }}" {{ (int) old('state_id', $city->state_id) === (int) $state->id ? 'selected' : '' }}>
                    {{ $state->name }} @if($state->country) - {{ $state->country->name }} @endif
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $city->name) }}" required>
    </div>

    <div class="col-md-4">
        <label class="form-label">Native Name</label>
        <input type="text" name="native_name" class="form-control" value="{{ old('native_name', $city->native_name) }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">Postal Code</label>
        <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code', $city->postal_code) }}">
    </div>

    <div class="col-md-2">
        <label class="form-label">Sort Order</label>
        <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', $city->sort_order ?? 0) }}" required>
    </div>

    <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="is_active" class="form-select" required>
            <option value="1" {{ (string) old('is_active', (int) ($city->is_active ?? true)) === '1' ? 'selected' : '' }}>Active</option>
            <option value="0" {{ (string) old('is_active', (int) ($city->is_active ?? true)) === '0' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
</div>
