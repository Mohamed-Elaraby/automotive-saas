<?php $page = 'portal-billing'; ?>
@extends('automotive.portal.layouts.portalLayout.mainlayout')

@section('content')
    @php
        $billingFormAction = $canChangeCurrentSubscriptionPlan
            ? route('automotive.portal.billing.change-plan')
            : route('automotive.portal.billing.renew');
        $billingSubmitLabel = $canChangeCurrentSubscriptionPlan
            ? __('portal.confirm_plan_change')
            : ($billingActions['primary_label'] ?? __('portal.renew_subscription'));
        $previewData = ($planChangePreview['ok'] ?? false) ? ($planChangePreview['preview'] ?? null) : null;
        $invoiceRows = $invoiceHistory['invoices'] ?? [];
        $productBillingLabel = $billingProductName ?: __('portal.workspace_product');
    @endphp

    <div class="page-wrapper">
        <div class="content content-two">
            <div class="row justify-content-center">
                <div class="col-xl-11">
                    <div class="mb-3 border-bottom pb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h6 class="mb-1">{{ __('portal.billing_title', ['product' => $productBillingLabel]) }}</h6>
                            <p class="text-muted mb-0">{{ __('portal.billing_intro') }}</p>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <a href="{{ route('automotive.portal') }}" class="btn btn-outline-white">{{ __('portal.back_to_portal') }}</a>
                            @if($allowSystemAccess && !empty($systemUrl))
                                <a href="{{ $systemUrl }}" target="_blank" class="btn btn-primary">{{ __('portal.open_workspace') }}</a>
                            @endif
                        </div>
                    </div>

                    @if(session('success'))
                        <div class="alert alert-success mb-3">{{ session('success') }}</div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger mb-3">{{ session('error') }}</div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger mb-3">
                            <ul class="mb-0 ps-3">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(collect($workspaceProducts)->isNotEmpty())
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                                    <div>
                                        <h6 class="mb-1">{{ __('shared.workspace_products') }}</h6>
                                        <p class="text-muted mb-0">{{ __('portal.switch_billing_context') }}</p>
                                    </div>
                                    <span class="badge bg-soft-info text-info">{{ count($workspaceProducts) }} {{ __('portal.connected_products') }}</span>
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($workspaceProducts as $workspaceProduct)
                                        @php($isFocusedProduct = (string) ($focusedWorkspaceProduct['product_code'] ?? '') === (string) $workspaceProduct['product_code'])
                                        <a
                                            href="{{ route('automotive.portal.billing.status', ['workspace_product' => $workspaceProduct['product_code']]) }}"
                                            class="btn {{ $isFocusedProduct ? 'btn-primary' : 'btn-outline-white' }}"
                                        >
                                            {{ $workspaceProduct['product_name'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-lg-8">
                            @include('automotive.admin.billing.partials.status-card', [
                                'billingState' => $billingState,
                                'plan' => $plan,
                            ])

                            <form id="portal-billing-plan-preview-form" method="GET" action="{{ route('automotive.portal.billing.status') }}" class="mt-4">
                                @if(!empty($focusedWorkspaceProduct['product_code']))
                                    <input type="hidden" name="workspace_product" value="{{ $focusedWorkspaceProduct['product_code'] }}">
                                @endif
                                @include('automotive.admin.billing.partials.plan-selector', [
                                    'availablePlans' => $availablePlans,
                                    'selectedPlanId' => $selectedPlanId,
                                    'billingProductName' => $billingProductName ?? null,
                                ])
                            </form>

                            @if(!empty($selectedPlan))
                                <div class="card mt-4">
                                    <div class="card-body">
                                        <h6 class="mb-3">{{ __('portal.selected_plan_pricing_verification') }}</h6>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <p class="mb-2"><strong>{{ __('portal.selected_plan') }}:</strong> {{ $selectedPlan->name ?? '-' }}</p>
                                                <p class="mb-2"><strong>{{ __('portal.local_price') }}:</strong> {{ $selectedPlan->display_price ?? '-' }}</p>
                                                <p class="mb-2"><strong>{{ __('portal.local_billing_period') }}:</strong> {{ $selectedPlan->billing_period_label ?? '-' }}</p>
                                                <p class="mb-2"><strong>{{ __('portal.stripe_price_id') }}:</strong> {{ $selectedPlan->stripe_price_id ?? '-' }}</p>
                                            </div>

                                            <div class="col-md-6">
                                                <p class="mb-2"><strong>{{ __('portal.stripe_amount') }}:</strong>
                                                    {{ isset($selectedPlanAudit['stripe']['unit_amount_decimal']) && $selectedPlanAudit['stripe']['unit_amount_decimal'] !== null
                                                        ? number_format((float) $selectedPlanAudit['stripe']['unit_amount_decimal'], 2)
                                                        : '-' }}
                                                    {{ $selectedPlanAudit['stripe']['currency'] ?? '' }}
                                                </p>
                                                <p class="mb-2"><strong>{{ __('portal.stripe_interval') }}:</strong> {{ $selectedPlanAudit['stripe']['interval'] ?? '-' }}</p>
                                                <p class="mb-2"><strong>{{ __('portal.stripe_product') }}:</strong> {{ $selectedPlanAudit['stripe']['product_name'] ?? '-' }}</p>
                                                <p class="mb-2"><strong>{{ __('portal.verification') }}:</strong>
                                                    @if(!empty($selectedPlanAudit['checks']['is_aligned']))
                                                        <span class="badge bg-success">{{ __('portal.aligned') }}</span>
                                                    @else
                                                        <span class="badge bg-danger">{{ __('portal.mismatch') }}</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>

                                        @if($isSameCurrentPaidPlan ?? false)
                                            <div class="alert alert-info mt-3 mb-0">
                                                {{ __('portal.already_on_plan', ['product' => $productBillingLabel]) }}
                                            </div>
                                        @elseif(empty($selectedPlanAudit['checks']['is_aligned']))
                                            <div class="alert alert-danger mt-3 mb-0">
                                                {{ $selectedPlanAudit['message'] ?? __('portal.pricing_mismatch') }}
                                            </div>
                                        @else
                                            <div class="alert alert-success mt-3 mb-0">
                                                {{ __('portal.pricing_aligned') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if($canChangeCurrentSubscriptionPlan && !empty($previewData))
                                <div class="card mt-4 border-primary">
                                    <div class="card-body">
                                        <h5 class="mb-3">{{ __('portal.stripe_change_preview') }}</h5>
                                        <p class="text-muted mb-3">{{ __('portal.stripe_change_preview_intro') }}</p>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p class="mb-2"><strong>{{ __('portal.this_change_adjustment') }}:</strong> {{ number_format((float) ($previewData['current_change_total_decimal'] ?? 0), 2) }} {{ $previewData['currency'] ?? 'USD' }}</p>
                                                <p class="mb-2"><strong>{{ __('shared.current_plan') }}:</strong> {{ $plan->name ?? '-' }}</p>
                                                <p class="mb-2"><strong>{{ __('portal.target_plan') }}:</strong> {{ $selectedPlan->name ?? '-' }}</p>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-2"><strong>{{ __('portal.preview_generated_at') }}:</strong> {{ !empty($previewData['proration_date']) ? \Carbon\Carbon::createFromTimestamp($previewData['proration_date'])->format('Y-m-d H:i:s') : '-' }}</p>
                                                <p class="mb-2"><strong>{{ __('portal.amount_due_on_stripe_preview') }}:</strong> {{ number_format((float) ($previewData['amount_due_decimal'] ?? 0), 2) }} {{ $previewData['currency'] ?? 'USD' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <form method="POST" action="{{ $billingFormAction }}" class="mt-4">
                                @csrf
                                @if(!empty($focusedWorkspaceProduct['product_code']))
                                    <input type="hidden" name="workspace_product" value="{{ $focusedWorkspaceProduct['product_code'] }}">
                                @endif
                                @if(!empty($selectedPlanId))
                                    <input type="hidden" name="target_plan_id" value="{{ $selectedPlanId }}">
                                @endif
                                @if($canChangeCurrentSubscriptionPlan && !empty($previewData['proration_date']))
                                    <input type="hidden" name="preview_proration_date" value="{{ $previewData['proration_date'] }}">
                                @endif

                                <div class="card mt-4">
                                    <div class="card-body d-flex flex-wrap gap-2 justify-content-end">
                                        <button type="submit" class="btn btn-primary" @disabled(empty($selectedPlanId) || ($canChangeCurrentSubscriptionPlan && ($isSameCurrentPaidPlan ?? false)))>
                                            {{ $billingSubmitLabel }}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="mb-3">{{ __('portal.billing_summary') }}</h6>
                                    <p class="mb-2"><strong>{{ __('portal.billing_product') }}:</strong> {{ $productBillingLabel }}</p>
                                    <p class="mb-2"><strong>{{ __('portal.tenant') }}:</strong> {{ $tenantId ?? '-' }}</p>
                                    <p class="mb-2"><strong>{{ __('shared.current_plan') }}:</strong> {{ $plan->name ?? 'N/A' }}</p>
                                    <p class="mb-2"><strong>{{ __('portal.current_status') }}:</strong> {{ ucfirst(str_replace('_', ' ', $billingState['status'] ?? 'unknown')) }}</p>
                                    <p class="mb-2"><strong>{{ __('portal.subscription_id') }}:</strong> {{ $subscription->id ?? '-' }}</p>
                                    <p class="mb-2"><strong>{{ __('portal.gateway_customer_id') }}:</strong> {{ $subscription->gateway_customer_id ?? '-' }}</p>
                                    <p class="mb-2"><strong>{{ __('portal.gateway_subscription_id') }}:</strong> {{ $subscription->gateway_subscription_id ?? '-' }}</p>
                                    <p class="mb-4"><strong>{{ __('portal.gateway_price_id') }}:</strong> {{ $subscription->gateway_price_id ?? '-' }}</p>

                                    @if(!empty($subscription->gateway_customer_id))
                                        <div class="d-grid gap-2">
                                            @if($canUpdatePaymentMethodInline ?? false)
                                                <button type="button" id="open-inline-payment-method" class="btn btn-light w-100">{{ __('portal.update_payment_method', ['product' => $productBillingLabel]) }}</button>
                                            @else
                                                <form method="POST" action="{{ route('automotive.portal.billing.portal') }}">
                                                    @csrf
                                                    @if(!empty($focusedWorkspaceProduct['product_code']))
                                                        <input type="hidden" name="workspace_product" value="{{ $focusedWorkspaceProduct['product_code'] }}">
                                                    @endif
                                                    <button type="submit" class="btn btn-light w-100">{{ __('portal.update_payment_method', ['product' => $productBillingLabel]) }}</button>
                                                </form>
                                            @endif

                                            <form method="POST" action="{{ route('automotive.portal.billing.portal') }}">
                                                @csrf
                                                @if(!empty($focusedWorkspaceProduct['product_code']))
                                                    <input type="hidden" name="workspace_product" value="{{ $focusedWorkspaceProduct['product_code'] }}">
                                                @endif
                                                <button type="submit" class="btn btn-outline-primary w-100">{{ __('portal.manage_product_billing', ['product' => $productBillingLabel]) }}</button>
                                            </form>

                                            @if(($billingState['status'] ?? '') === 'active')
                                                <form method="POST" action="{{ route('automotive.portal.billing.cancel-subscription') }}">
                                                    @csrf
                                                    @if(!empty($focusedWorkspaceProduct['product_code']))
                                                        <input type="hidden" name="workspace_product" value="{{ $focusedWorkspaceProduct['product_code'] }}">
                                                    @endif
                                                    <button type="submit" class="btn btn-outline-danger w-100">{{ __('portal.cancel_at_period_end') }}</button>
                                                </form>
                                            @endif

                                            @if(($billingState['status'] ?? '') === 'canceled')
                                                <form method="POST" action="{{ route('automotive.portal.billing.resume-subscription') }}">
                                                    @csrf
                                                    @if(!empty($focusedWorkspaceProduct['product_code']))
                                                        <input type="hidden" name="workspace_product" value="{{ $focusedWorkspaceProduct['product_code'] }}">
                                                    @endif
                                                    <button type="submit" class="btn btn-success w-100">{{ __('portal.resume_subscription') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if($canUpdatePaymentMethodInline ?? false)
                                <div class="card mt-4" id="inline-payment-method-card" style="display:none;">
                                    <div class="card-body">
                                        <h6 class="mb-3">{{ __('portal.secure_payment_method_update') }}</h6>
                                        <div id="payment-method-inline-alert" class="alert d-none"></div>
                                        <div id="payment-method-loader" class="text-muted small mb-3">{{ __('portal.loading_secure_payment_form') }}</div>
                                        <form id="payment-method-update-form">
                                            @csrf
                                            <div id="payment-method-element" class="mb-3"></div>
                                            <div class="d-flex gap-2">
                                                <button type="submit" id="payment-method-submit-btn" class="btn btn-primary" disabled>{{ __('portal.save_payment_method') }}</button>
                                                <button type="button" id="close-inline-payment-method" class="btn btn-outline-secondary">{{ __('portal.close') }}</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif

                            <div class="card mt-4">
                                <div class="card-body">
                                    <h6 class="mb-3">{{ __('portal.invoice_history', ['product' => $productBillingLabel]) }}</h6>
                                    @if(!($invoiceHistory['ok'] ?? true))
                                        <div class="alert alert-warning mb-0">{{ $invoiceHistory['message'] ?? __('portal.unable_invoice_history') }}</div>
                                    @elseif(empty($subscription->gateway_customer_id))
                                        <div class="alert alert-info mb-0">{{ __('portal.invoice_history_after_customer', ['product' => $productBillingLabel]) }}</div>
                                    @elseif(empty($invoiceRows))
                                        <div class="alert alert-light mb-0">{{ __('portal.no_stripe_invoices', ['product' => $productBillingLabel]) }}</div>
                                    @else
                                        <div class="d-flex flex-column gap-3">
                                            @foreach($invoiceRows as $invoice)
                                                <div class="border rounded p-3">
                                                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                                        <div>
                                                            <div class="fw-semibold">{{ $invoice['number'] ?? ($invoice['id'] ?? __('portal.stripe_invoice')) }}</div>
                                                            <div class="small text-muted">{{ !empty($invoice['created_at']) ? \Carbon\Carbon::createFromTimestamp($invoice['created_at'])->format('Y-m-d H:i') : '-' }}</div>
                                                        </div>
                                                        <span class="badge {{
                                                            match (strtolower((string) ($invoice['status'] ?? 'unknown'))) {
                                                                'paid' => 'bg-success',
                                                                'open' => 'bg-warning text-dark',
                                                                'draft' => 'bg-secondary',
                                                                'void' => 'bg-dark',
                                                                'uncollectible' => 'bg-danger',
                                                                default => 'bg-light text-dark',
                                                            }
                                                        }}">{{ ucfirst($invoice['status'] ?? 'unknown') }}</span>
                                                    </div>
                                                    <p class="mb-1"><strong>{{ __('portal.total') }}:</strong> {{ number_format((float) ($invoice['total_decimal'] ?? 0), 2) }} {{ $invoice['currency'] ?? 'USD' }}</p>
                                                    <p class="mb-1"><strong>{{ __('portal.paid') }}:</strong> {{ number_format((float) ($invoice['amount_paid_decimal'] ?? 0), 2) }} {{ $invoice['currency'] ?? 'USD' }}</p>
                                                    <p class="mb-3"><strong>{{ __('portal.due') }}:</strong> {{ number_format((float) ($invoice['amount_due_decimal'] ?? 0), 2) }} {{ $invoice['currency'] ?? 'USD' }}</p>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        @if(!empty($invoice['hosted_invoice_url']))
                                                            <a href="{{ $invoice['hosted_invoice_url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">{{ __('portal.view') }}</a>
                                                        @endif
                                                        @if(!empty($invoice['invoice_pdf']))
                                                            <a href="{{ $invoice['invoice_pdf'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">PDF</a>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const previewForm = document.getElementById('portal-billing-plan-preview-form');
            if (previewForm) {
                previewForm.querySelectorAll('input[name="target_plan_id"]').forEach(function (radio) {
                    radio.addEventListener('change', function () {
                        previewForm.submit();
                    });
                });
            }
        });
    </script>

    @if($canUpdatePaymentMethodInline ?? false)
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const publishableKey = @json($stripePublishableKey ?? '');
                const openBtn = document.getElementById('open-inline-payment-method');
                const closeBtn = document.getElementById('close-inline-payment-method');
                const card = document.getElementById('inline-payment-method-card');
                const loader = document.getElementById('payment-method-loader');
                const alertBox = document.getElementById('payment-method-inline-alert');
                const form = document.getElementById('payment-method-update-form');
                const submitBtn = document.getElementById('payment-method-submit-btn');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());
                const workspaceProduct = @json($focusedWorkspaceProduct['product_code'] ?? null);
                const messages = {
                    loadingSecurePaymentForm: @json(__('portal.loading_secure_payment_form')),
                    unableInitialize: @json(__('portal.unable_initialize_secure_payment')),
                    unableLoad: @json(__('portal.unable_load_secure_payment')),
                    reopenFreshSession: @json(__('portal.reopen_payment_session')),
                    stripeConfirmFailed: @json(__('portal.stripe_confirm_failed')),
                    stripeNoPaymentMethod: @json(__('portal.stripe_no_payment_method')),
                    unableSavePaymentMethod: @json(__('portal.unable_save_payment_method')),
                    paymentMethodUpdated: @json(__('portal.payment_method_updated')),
                    unableUpdatePaymentMethod: @json(__('portal.unable_update_payment_method')),
                };

                let stripe = null;
                let elements = null;
                let paymentElement = null;
                let clientSecret = null;
                let initialized = false;
                let isSubmitting = false;
                let setupIntentConsumed = false;

                function showAlert(type, message) {
                    if (!alertBox) return;
                    alertBox.className = 'alert';
                    alertBox.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
                    alertBox.classList.remove('d-none');
                    alertBox.textContent = message;
                }

                function clearAlert() {
                    if (!alertBox) return;
                    alertBox.className = 'alert d-none';
                    alertBox.textContent = '';
                }

                function resetInlinePaymentState() {
                    if (paymentElement) {
                        try { paymentElement.unmount(); } catch (e) {}
                    }
                    paymentElement = null;
                    elements = null;
                    clientSecret = null;
                    initialized = false;
                    isSubmitting = false;
                    if (loader) {
                        loader.style.display = 'block';
                        loader.textContent = messages.loadingSecurePaymentForm;
                    }
                    const mountNode = document.getElementById('payment-method-element');
                    if (mountNode) {
                        mountNode.innerHTML = '';
                    }
                    if (submitBtn) {
                        submitBtn.disabled = true;
                    }
                }

                async function initializePaymentMethodForm(forceFresh = false) {
                    if (forceFresh) {
                        resetInlinePaymentState();
                    }
                    if (initialized && !setupIntentConsumed) {
                        return;
                    }
                    clearAlert();
                    try {
                        const response = await fetch(@json(route('automotive.portal.billing.payment-method.setup-intent')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ workspace_product: workspaceProduct }),
                        });
                        const payload = await response.json();
                        if (!response.ok || !payload.ok || !payload.client_secret) {
                            throw new Error(payload.message || messages.unableInitialize);
                        }
                        clientSecret = payload.client_secret;
                        stripe = Stripe(publishableKey);
                        elements = stripe.elements({ clientSecret: clientSecret });
                        paymentElement = elements.create('payment');
                        paymentElement.mount('#payment-method-element');
                        initialized = true;
                        setupIntentConsumed = false;
                        submitBtn.disabled = false;
                        if (loader) {
                            loader.style.display = 'none';
                        }
                    } catch (error) {
                        if (loader) {
                            loader.style.display = 'none';
                        }
                        showAlert('error', error.message || messages.unableLoad);
                    }
                }

                async function handleSubmit(event) {
                    event.preventDefault();
                    if (isSubmitting) {
                        return;
                    }
                    if (!stripe || !elements || !clientSecret || setupIntentConsumed) {
                        showAlert('error', messages.reopenFreshSession);
                        return;
                    }
                    clearAlert();
                    isSubmitting = true;
                    submitBtn.disabled = true;
                    try {
                        const result = await stripe.confirmSetup({ elements, confirmParams: {}, redirect: 'if_required' });
                        if (result.error) {
                            throw new Error(result.error.message || messages.stripeConfirmFailed);
                        }
                        const paymentMethodId = result.setupIntent?.payment_method || null;
                        if (!paymentMethodId) {
                            throw new Error(messages.stripeNoPaymentMethod);
                        }
                        const saveResponse = await fetch(@json(route('automotive.portal.billing.payment-method.default')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                payment_method_id: paymentMethodId,
                                workspace_product: workspaceProduct,
                            }),
                        });
                        const savePayload = await saveResponse.json();
                        if (!saveResponse.ok || !savePayload.ok) {
                            throw new Error(savePayload.message || messages.unableSavePaymentMethod);
                        }
                        setupIntentConsumed = true;
                        showAlert('success', savePayload.message || messages.paymentMethodUpdated);
                    } catch (error) {
                        showAlert('error', error.message || messages.unableUpdatePaymentMethod);
                    } finally {
                        isSubmitting = false;
                        submitBtn.disabled = setupIntentConsumed;
                    }
                }

                if (openBtn && card) {
                    openBtn.addEventListener('click', function () {
                        card.style.display = 'block';
                        initializePaymentMethodForm(setupIntentConsumed);
                    });
                }

                if (closeBtn && card) {
                    closeBtn.addEventListener('click', function () {
                        card.style.display = 'none';
                    });
                }

                if (form) {
                    form.addEventListener('submit', handleSubmit);
                }
            });
        </script>
    @endif
@endsection
