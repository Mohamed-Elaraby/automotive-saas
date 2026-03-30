@extends('automotive.front.layouts.auth')

@section('title', 'Create Account - Automotive SaaS')

@section('auth-styles')
    .card {
        max-width: 520px;
    }
    .secondary-button {
        width: auto;
        padding: 12px 16px;
        font-size: 14px;
        background: #0f766e;
    }
    .hint {
        font-size: 12px;
        color: #6b7280;
        margin-top: 6px;
    }
    .inline-row {
        display: flex;
        gap: 10px;
        align-items: stretch;
    }
    .inline-row input {
        flex: 1;
    }
    .coupon-preview {
        margin-top: 12px;
        border-radius: 10px;
        padding: 12px 14px;
        font-size: 14px;
        display: none;
    }
    .coupon-preview.success {
        display: block;
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .coupon-preview.error {
        display: block;
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .coupon-preview .meta {
        margin-top: 8px;
        font-size: 13px;
        color: inherit;
        line-height: 1.6;
    }
    .coupon-preview ul {
        margin: 8px 0 0;
        padding-left: 18px;
    }
@endsection

@section('content')
    <div class="wrapper">
        <div class="card">
            <h1>Create Your Customer Portal Account</h1>
            <p>Register first, reserve your preferred subdomain, then continue from the customer portal to start a free trial or choose a paid plan.</p>

            @if ($errors->any())
                <div class="error">
                    <strong>Please review the form.</strong>
                    @if ($errors->has('register'))
                        <div style="margin-top:8px;">{{ $errors->first('register') }}</div>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('automotive.register.submit') }}">
                @csrf

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required>
                    @error('name') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label for="email">Business Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required>
                    @error('email') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input id="company_name" type="text" name="company_name" value="{{ old('company_name') }}" required>
                    @error('company_name') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label for="subdomain">Subdomain</label>
                    <input id="subdomain" type="text" name="subdomain" value="{{ old('subdomain') }}" required>
                    <div class="hint">Example: mido -> mido.automotive.seven-scapital.com</div>
                    @error('subdomain') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label for="coupon_code">Coupon Code</label>
                    <div class="inline-row">
                        <input id="coupon_code" type="text" name="coupon_code" value="{{ old('coupon_code') }}" placeholder="Optional coupon code">
                        <button type="button" id="checkCouponButton" class="secondary-button">Check Coupon</button>
                    </div>
                    <div class="hint">Optional. If valid for trial reservation, it will be stored on your account and reused later from the portal.</div>
                    @error('coupon_code') <div class="field-error">{{ $message }}</div> @enderror

                    <div id="couponPreviewBox" class="coupon-preview"></div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required>
                    @error('password') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required>
                </div>

                <button type="submit">Create Account &amp; Continue</button>
            </form>

            <div class="hint" style="margin-top:18px; text-align:center;">
                Already have an account?
                <a href="{{ route('automotive.login') }}" style="color:#1d4ed8;">Sign in to your portal</a>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const button = document.getElementById('checkCouponButton');
            const subdomainInput = document.getElementById('subdomain');
            const couponInput = document.getElementById('coupon_code');
            const previewBox = document.getElementById('couponPreviewBox');
            const csrfToken = document.querySelector('input[name="_token"]')?.value ?? '';

            function showPreview(type, html) {
                previewBox.className = 'coupon-preview ' + type;
                previewBox.innerHTML = html;
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            async function checkCoupon() {
                const subdomain = subdomainInput.value.trim();
                const couponCode = couponInput.value.trim();

                if (!subdomain || !couponCode) {
                    showPreview('error', '<strong>Please enter both subdomain and coupon code first.</strong>');
                    return;
                }

                button.disabled = true;
                button.textContent = 'Checking...';

                try {
                    const response = await fetch('{{ route('automotive.register.coupon-preview') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            subdomain: subdomain,
                            coupon_code: couponCode
                        })
                    });

                    const data = await response.json();

                    if (response.ok && data.ok) {
                        const coupon = data.coupon || {};
                        const meta = data.eligibility?.meta || {};
                        const discountLabel = coupon.discount_type === 'percentage'
                            ? `${coupon.discount_value}%`
                            : `${coupon.discount_value} ${coupon.currency_code || ''}`.trim();

                        const html = `
                            <strong>${escapeHtml(data.message || 'Coupon is valid.')}</strong>
                            <div class="meta">
                                <div><strong>Coupon:</strong> ${escapeHtml(coupon.code || '')}</div>
                                <div><strong>Name:</strong> ${escapeHtml(coupon.name || '')}</div>
                                <div><strong>Discount:</strong> ${escapeHtml(discountLabel)}</div>
                                <div><strong>First billing cycle only:</strong> ${coupon.first_billing_cycle_only ? 'Yes' : 'No'}</div>
                                <div><strong>Applies to all plans:</strong> ${coupon.applies_to_all_plans ? 'Yes' : 'No'}</div>
                                <div><strong>Plan validation:</strong> ${meta.plan_validation_deferred ? 'Will run later when the paid plan is selected.' : 'Validated now.'}</div>
                            </div>
                        `;

                        showPreview('success', html);
                    } else {
                        const errors = data.errors || {};
                        const couponErrors = Array.isArray(errors.coupon_code) ? errors.coupon_code : [];
                        const genericMessage = data.message || 'This coupon cannot be used right now.';

                        let html = `<strong>${escapeHtml(genericMessage)}</strong>`;

                        if (couponErrors.length > 0) {
                            html += '<ul>' + couponErrors.map(error => `<li>${escapeHtml(error)}</li>`).join('') + '</ul>';
                        }

                        showPreview('error', html);
                    }
                } catch (error) {
                    showPreview('error', '<strong>Unable to validate the coupon right now. Please try again.</strong>');
                } finally {
                    button.disabled = false;
                    button.textContent = 'Check Coupon';
                }
            }

            button.addEventListener('click', checkCoupon);
        })();
    </script>
@endsection
