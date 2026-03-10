<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <div>
        <label>Name</label>
        <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
    </div>

    <div>
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
    </div>

    <div>
        <label>Password {{ isset($isEdit) && $isEdit ? '(leave blank to keep current password)' : '' }}</label>
        <input type="password" name="password" {{ isset($isEdit) && $isEdit ? '' : 'required' }}>
    </div>

    <div>
        <label>Password Confirmation</label>
        <input type="password" name="password_confirmation" {{ isset($isEdit) && $isEdit ? '' : 'required' }}>
    </div>
</div>
