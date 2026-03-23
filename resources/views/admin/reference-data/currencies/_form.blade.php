<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Code</label>
        <input type="text" name="code" class="form-control" value="{{ old('code', $currency->code) }}" maxlength="3" required>
    </div>

    <div class="col-md-5">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $currency->name) }}" required>
    </div>

    <div class="col-md-2">
        <label class="form-label">Symbol</label>
        <input type="text" name="symbol" class="form-control" value="{{ old('symbol', $currency->symbol) }}">
    </div>

    <div class="col-md-2">
        <label class="form-label">Native Symbol</label>
        <input type="text" name="native_symbol" class="form-control" value="{{ old('native_symbol', $currency->native_symbol) }}">
    </div>

    <div class="col-md-3">
        <label class="form-label">Decimal Places</label>
        <input type="number" name="decimal_places" class="form-control" min="0" max="6" value="{{ old('decimal_places', $currency->decimal_places ?? 2) }}" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Thousands Separator</label>
        <input type="text" name="thousands_separator" class="form-control" value="{{ old('thousands_separator', $currency->thousands_separator ?? ',') }}" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Decimal Separator</label>
        <input type="text" name="decimal_separator" class="form-control" value="{{ old('decimal_separator', $currency->decimal_separator ?? '.') }}" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Sort Order</label>
        <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', $currency->sort_order ?? 0) }}" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="is_active" class="form-select" required>
            <option value="1" {{ (string) old('is_active', (int) ($currency->is_active ?? true)) === '1' ? 'selected' : '' }}>Active</option>
            <option value="0" {{ (string) old('is_active', (int) ($currency->is_active ?? true)) === '0' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
</div>
