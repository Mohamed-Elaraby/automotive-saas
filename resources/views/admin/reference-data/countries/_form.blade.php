<div class="row g-3">
    <div class="col-md-2">
        <label class="form-label">ISO2</label>
        <input type="text" name="iso2" class="form-control" value="{{ old('iso2', $country->iso2) }}" maxlength="2" required>
    </div>

    <div class="col-md-2">
        <label class="form-label">ISO3</label>
        <input type="text" name="iso3" class="form-control" value="{{ old('iso3', $country->iso3) }}" maxlength="3" required>
    </div>

    <div class="col-md-4">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $country->name) }}" required>
    </div>

    <div class="col-md-4">
        <label class="form-label">Native Name</label>
        <input type="text" name="native_name" class="form-control" value="{{ old('native_name', $country->native_name) }}">
    </div>

    <div class="col-md-3">
        <label class="form-label">Phone Code</label>
        <input type="text" name="phone_code" class="form-control" value="{{ old('phone_code', $country->phone_code) }}">
    </div>

    <div class="col-md-3">
        <label class="form-label">Capital</label>
        <input type="text" name="capital" class="form-control" value="{{ old('capital', $country->capital) }}">
    </div>

    <div class="col-md-3">
        <label class="form-label">Currency</label>
        <select name="currency_code" class="form-select">
            <option value="">Select</option>
            @foreach($currencies as $currencyItem)
                <option value="{{ $currencyItem->code }}" {{ old('currency_code', $country->currency_code) === $currencyItem->code ? 'selected' : '' }}>
                    {{ $currencyItem->code }} - {{ $currencyItem->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Sort Order</label>
        <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', $country->sort_order ?? 0) }}" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="is_active" class="form-select" required>
            <option value="1" {{ (string) old('is_active', (int) ($country->is_active ?? true)) === '1' ? 'selected' : '' }}>Active</option>
            <option value="0" {{ (string) old('is_active', (int) ($country->is_active ?? true)) === '0' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
</div>
