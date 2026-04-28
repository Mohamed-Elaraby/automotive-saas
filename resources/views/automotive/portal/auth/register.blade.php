<?php $page = 'automotive/portal/register'; ?>
@extends('automotive.portal.layouts.portalLayout.mainlayout')

@section('content')
    <div class="container-fuild">
        <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100">
            <div class="row justify-content-center align-items-center vh-100 overflow-auto flex-wrap">
                <div class="col-lg-5 mx-auto">
                    <form method="POST" action="{{ route('automotive.register.submit') }}" class="d-flex justify-content-center align-items-center">
                        @csrf

                        <div class="d-flex flex-column justify-content-lg-center p-4 p-lg-0 pt-lg-4 pb-0 flex-fill">
                            <div class="mx-auto mb-5 text-center">
                                {{ __('portal.brand') }}
                                <div class="mt-3 d-inline-flex">
                                    @include('shared.partials.language-switcher')
                                </div>
                            </div>

                            <div class="card border-0 p-lg-3 shadow-lg rounded-2">
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <h5 class="mb-2">{{ __('portal.create_account') }}</h5>
                                        <p class="mb-0">{{ __('portal.register_intro') }}</p>
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
                                        <label class="form-label">{{ __('portal.full_name') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-user"></i>
                                            </span>
                                            <input id="name" type="text" name="name" value="{{ old('name') }}" class="form-control border-start-0 ps-0" placeholder="{{ __('portal.enter_full_name') }}" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">{{ __('portal.business_email') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-sms-notification"></i>
                                            </span>
                                            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control border-start-0 ps-0" placeholder="{{ __('portal.enter_email_address') }}" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">{{ __('portal.company_name') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-buildings"></i>
                                            </span>
                                            <input id="company_name" type="text" name="company_name" value="{{ old('company_name') }}" class="form-control border-start-0 ps-0" placeholder="{{ __('portal.enter_company_name') }}" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">{{ __('portal.subdomain') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-global"></i>
                                            </span>
                                            <input id="subdomain" type="text" name="subdomain" value="{{ old('subdomain') }}" class="form-control border-start-0 ps-0" placeholder="{{ __('portal.enter_preferred_subdomain') }}" required>
                                        </div>
                                        <small class="text-muted d-block mt-2">{{ __('portal.subdomain_example') }}</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">{{ __('portal.coupon_code') }}</label>
                                        <div class="d-flex gap-2">
                                            <input id="coupon_code" type="text" name="coupon_code" value="{{ old('coupon_code') }}" class="form-control" placeholder="{{ __('portal.optional_coupon_code') }}">
                                            <button type="button" id="checkCouponButton" class="btn btn-outline-primary flex-shrink-0 w-auto">{{ __('portal.check_coupon') }}</button>
                                        </div>
                                        <small class="text-muted d-block mt-2">{{ __('portal.coupon_help') }}</small>
                                        <div id="couponPreviewBox" class="mt-3"></div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">{{ __('portal.password') }}</label>
                                        <div class="pass-group input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-lock"></i>
                                            </span>
                                            <span class="isax toggle-password isax-eye-slash"></span>
                                            <input id="password" type="password" name="password" class="pass-input form-control border-start-0 ps-0" placeholder="****************" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">{{ __('portal.confirm_password') }}</label>
                                        <div class="pass-group input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-lock"></i>
                                            </span>
                                            <span class="isax toggle-passwords isax-eye-slash"></span>
                                            <input id="password_confirmation" type="password" name="password_confirmation" class="pass-input form-control border-start-0 ps-0" placeholder="****************" required>
                                        </div>
                                    </div>

                                    <div class="mb-1">
                                        <button type="submit" class="btn bg-primary-gradient text-white w-100">{{ __('portal.create_account_continue') }}</button>
                                    </div>

                                    <div class="text-center mt-3">
                                        <h6 class="fw-normal fs-14 text-dark mb-0">
                                            {{ __('portal.already_have_account') }}
                                            <a href="{{ route('automotive.login') }}" class="hover-a"> {{ __('portal.sign_in_to_your_portal') }}</a>
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
            const labels = {
                enterBoth: @json(__('portal.enter_both_subdomain_coupon')),
                checking: @json(__('portal.checking')),
                checkCoupon: @json(__('portal.check_coupon')),
                valid: @json(__('portal.coupon_valid')),
                coupon: @json(__('portal.coupon_code')),
                name: @json(__('portal.name')),
                discount: @json(__('portal.discount')),
                firstBillingCycleOnly: @json(__('portal.first_billing_cycle_only')),
                appliesToAllPlans: @json(__('portal.applies_to_all_plans')),
                planValidation: @json(__('portal.plan_validation')),
                yes: @json(__('portal.yes')),
                no: @json(__('portal.no')),
                deferred: @json(__('portal.plan_validation_deferred')),
                validatedNow: @json(__('portal.validated_now')),
                cannotUse: @json(__('portal.coupon_cannot_be_used')),
                unable: @json(__('portal.unable_validate_coupon')),
            };

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
                    showPreview('error', `<strong>${escapeHtml(labels.enterBoth)}</strong>`);
                    return;
                }

                button.disabled = true;
                button.textContent = labels.checking;

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
                            <strong>${escapeHtml(data.message || labels.valid)}</strong>
                            <div class="mt-2">
                                <div><strong>${escapeHtml(labels.coupon)}:</strong> ${escapeHtml(coupon.code || '')}</div>
                                <div><strong>${escapeHtml(labels.name)}:</strong> ${escapeHtml(coupon.name || '')}</div>
                                <div><strong>${escapeHtml(labels.discount)}:</strong> ${escapeHtml(discountLabel)}</div>
                                <div><strong>${escapeHtml(labels.firstBillingCycleOnly)}:</strong> ${coupon.first_billing_cycle_only ? escapeHtml(labels.yes) : escapeHtml(labels.no)}</div>
                                <div><strong>${escapeHtml(labels.appliesToAllPlans)}:</strong> ${coupon.applies_to_all_plans ? escapeHtml(labels.yes) : escapeHtml(labels.no)}</div>
                                <div><strong>${escapeHtml(labels.planValidation)}:</strong> ${meta.plan_validation_deferred ? escapeHtml(labels.deferred) : escapeHtml(labels.validatedNow)}</div>
                            </div>
                        `;

                        showPreview('success', html);
                    } else {
                        const errors = data.errors || {};
                        const couponErrors = Array.isArray(errors.coupon_code) ? errors.coupon_code : [];
                        const genericMessage = data.message || labels.cannotUse;

                        let html = `<strong>${escapeHtml(genericMessage)}</strong>`;

                        if (couponErrors.length > 0) {
                            html += '<ul class="mb-0 mt-2 ps-3">' + couponErrors.map(error => `<li>${escapeHtml(error)}</li>`).join('') + '</ul>';
                        }

                        showPreview('error', html);
                    }
                } catch (error) {
                    showPreview('error', `<strong>${escapeHtml(labels.unable)}</strong>`);
                } finally {
                    button.disabled = false;
                    button.textContent = labels.checkCoupon;
                }
            }

            button.addEventListener('click', checkCoupon);
        })();
    </script>
@endsection
