<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <div>
        <label>Name</label>
        <input type="text" name="name" value="{{ old('name', $branch->name) }}" required>
    </div>

    <div>
        <label>Code</label>
        <input type="text" name="code" value="{{ old('code', $branch->code) }}" required>
    </div>

    <div>
        <label>Phone</label>
        <input type="text" name="phone" value="{{ old('phone', $branch->phone) }}">
    </div>

    <div>
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email', $branch->email) }}">
    </div>

    <div style="grid-column:1 / -1;">
        <label>Address</label>
        <textarea name="address" rows="4">{{ old('address', $branch->address) }}</textarea>
    </div>

    <div style="grid-column:1 / -1;">
        <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $branch->is_active ?? true) ? 'checked' : '' }}>
            Active
        </label>
    </div>
</div>
