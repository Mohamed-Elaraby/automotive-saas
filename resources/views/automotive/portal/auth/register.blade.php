<?php $page = 'automotive/portal/register'; ?>
@extends('automotive.layouts.portalLayout.mainlayout')

@section('content')
    <div class="container-fuild">
        <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100">
            <div class="row justify-content-center align-items-center vh-100 overflow-auto flex-wrap">
                <div class="col-lg-5 mx-auto">
                    <form method="POST" action="{{ route('automotive.register.submit') }}" class="d-flex justify-content-center align-items-center">
                        @csrf

                        <div class="d-flex flex-column justify-content-lg-center p-4 p-lg-0 pt-lg-4 pb-0 flex-fill">
                            <div class="mx-auto mb-5 text-center">
                                Automotive Customer Portal
                            </div>

                            <div class="card border-0 p-lg-3 shadow-lg rounded-2">
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <h5 class="mb-2">Create Account</h5>
                                        <p class="mb-0">Register first, reserve your preferred subdomain, then continue from the portal</p>
                                    </div>

                                    @if($errors->any())
                                        <div class="alert alert-danger mb-3">
                                            <ul class="mb-0 ps-3">
                                                @foreach($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    <div class="mb-3">
                                        <label class="form-label">Full Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-user"></i>
                                            </span>
                                            <input id="name" type="text" name="name" value="{{ old('name') }}" class="form-control border-start-0 ps-0" placeholder="Enter Full Name" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Business Email</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-sms-notification"></i>
                                            </span>
                                            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control border-start-0 ps-0" placeholder="Enter Email Address" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Company Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-buildings"></i>
                                            </span>
                                            <input id="company_name" type="text" name="company_name" value="{{ old('company_name') }}" class="form-control border-start-0 ps-0" placeholder="Enter Company Name" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Subdomain</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-global"></i>
                                            </span>
                                            <input id="subdomain" type="text" name="subdomain" value="{{ old('subdomain') }}" class="form-control border-start-0 ps-0" placeholder="Enter Preferred Subdomain" required>
                                        </div>
                                        <small class="text-muted d-block mt-2">Example: mido -> mido.automotive.seven-scapital.com</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Coupon Code</label>
                                        <div class="d-flex gap-2">
                                            <input id="coupon_code" type="text" name="coupon_code" value="{{ old('coupon_code') }}" class="form-control" placeholder="Optional coupon code">
                                            <button type="button" id="checkCouponButton" class="btn btn-outline-primary flex-shrink-0 w-auto">Check Coupon</button>
                                        </div>
                                        <small class="text-muted d-block mt-2">Optional. If valid for trial reservation, it will be stored on your account and reused later from the portal.</small>
                                        <div id="couponPreviewBox" class="mt-3"></div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <div class="pass-group input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-lock"></i>
                                            </span>
                                            <span class="isax toggle-password isax-eye-slash"></span>
                                            <input id="password" type="password" name="password" class="pass-input form-control border-start-0 ps-0" placeholder="****************" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Confirm Password</label>
                                        <div class="pass-group input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-lock"></i>
                                            </span>
                                            <span class="isax toggle-passwords isax-eye-slash"></span>
                                            <input id="password_confirmation" type="password" name="password_confirmation" class="pass-input form-control border-start-0 ps-0" placeholder="****************" required>
                                        </div>
                                    </div>

                                    <div class="mb-1">
                                        <button type="submit" class="btn bg-primary-gradient text-white w-100">Create Account &amp; Continue</button>
                                    </div>

                                    <div class="text-center mt-3">
                                        <h6 class="fw-normal fs-14 text-dark mb-0">
                                            Already have an account?
                                            <a href="{{ route('automotive.login') }}" class="hover-a"> Sign in to your portal</a>
                                        </h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
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
                previewBox.className = type === 'success'
                    ? 'alert alert-success mb-0'
                    : 'alert alert-danger mb-0';
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
                            <div class="mt-2">
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
                            html += '<ul class="mb-0 mt-2 ps-3">' + couponErrors.map(error => `<li>${escapeHtml(error)}</li>`).join('') + '</ul>';
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
