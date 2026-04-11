<?php $page = 'billing'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    @php
        $canChangeCurrentSubscriptionPlan = $canChangeCurrentSubscriptionPlan ?? false;
        $isReadOnlyBillingContext = $isReadOnlyBillingContext ?? false;
        $billingFormAction = $canChangeCurrentSubscriptionPlan
            ? route('automotive.admin.billing.change-plan')
            : route('automotive.admin.billing.renew');

        $billingSubmitLabel = $canChangeCurrentSubscriptionPlan
            ? 'Confirm Plan Change'
            : ($billingActions['primary_label'] ?? 'Renew Subscription');

        $previewData = ($planChangePreview['ok'] ?? false) ? ($planChangePreview['preview'] ?? null) : null;
        $invoiceRows = $invoiceHistory['invoices'] ?? [];
        $productBillingLabel = $billingProductName ?: 'Workspace Product';
    @endphp

    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => ($billingProductName ?? 'Plans & Billing') . ' Billing',
                'subtitle' => ($isAttachedBillingContext ?? false)
                    ? 'Attached product billing with product-scoped checkout, payment method management, invoice history, and Stripe lifecycle actions.'
                    : 'Trial, subscription, billing access state, and renewal actions.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Plans & Billing'],
                ],
                'actions' => null,
            ])

            @include('automotive.admin.partials.alerts')

            <div class="row">
                <div class="col-lg-8">
                    @include('automotive.admin.billing.partials.status-card', [
                        'billingState' => $billingState,
                        'plan' => $plan,
                    ])

                    <form id="billing-plan-preview-form" method="GET" action="{{ route('automotive.admin.billing.status') }}" class="mt-4">
                        @if(!empty($focusedWorkspaceProduct['product_code']))
                            <input type="hidden" name="workspace_product" value="{{ $focusedWorkspaceProduct['product_code'] }}">
                        @endif
                        @include('automotive.admin.billing.partials.plan-selector', [
                            'availablePlans' => $availablePlans,
                            'selectedPlanId' => $selectedPlanId,
                            'billingProductName' => $billingProductName ?? null,
                        ])
                    </form>

                    @if($isAttachedBillingContext ?? false)
                        <div class="alert alert-info mt-4">
                            This attached product now supports admin-side checkout, plan changes, payment method management, invoice history, and Stripe lifecycle actions in this screen.
                        </div>
                    @endif

                    @if(!empty($selectedPlan))
                        <div class="card mt-4">
                            <div class="card-body">
                                <h6 class="mb-3">Selected Plan Pricing Verification</h6>

                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2"><strong>Selected Plan:</strong> {{ $selectedPlan->name ?? '-' }}</p>
                                        <p class="mb-2"><strong>Local Price:</strong> {{ $selectedPlan->display_price ?? '-' }}</p>
                                        <p class="mb-2"><strong>Local Billing Period:</strong> {{ $selectedPlan->billing_period_label ?? '-' }}</p>
                                        <p class="mb-2"><strong>Stripe Price ID:</strong> {{ $selectedPlan->stripe_price_id ?? '-' }}</p>
                                    </div>

                                    <div class="col-md-6">
                                        <p class="mb-2"><strong>Stripe Amount:</strong>
                                            {{ isset($selectedPlanAudit['stripe']['unit_amount_decimal']) && $selectedPlanAudit['stripe']['unit_amount_decimal'] !== null
                                                ? number_format((float) $selectedPlanAudit['stripe']['unit_amount_decimal'], 2)
                                                : '-' }}
                                            {{ $selectedPlanAudit['stripe']['currency'] ?? '' }}
                                        </p>
                                        <p class="mb-2"><strong>Stripe Interval:</strong> {{ $selectedPlanAudit['stripe']['interval'] ?? '-' }}</p>
                                        <p class="mb-2"><strong>Stripe Product:</strong> {{ $selectedPlanAudit['stripe']['product_name'] ?? '-' }}</p>
                                        <p class="mb-2"><strong>Verification:</strong>
                                            @if(!empty($selectedPlanAudit['checks']['is_aligned']))
                                                <span class="badge bg-success">Aligned</span>
                                            @else
                                                <span class="badge bg-danger">Mismatch</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                @if($isSameCurrentPaidPlan ?? false)
                                    <div class="alert alert-info mt-3 mb-0">
                                        You are already on this plan. Choose another plan to change it, or use Manage {{ $productBillingLabel }} Billing for payment method and cancellation controls.
                                    </div>
                                @elseif(empty($selectedPlanAudit['checks']['is_aligned']))
                                    <div class="alert alert-danger mt-3 mb-0">
                                        {{ $selectedPlanAudit['message'] ?? 'Selected plan pricing does not match Stripe.' }}
                                        Billing action is blocked until this mapping is corrected.
                                    </div>
                                @else
                                    <div class="alert alert-success mt-3 mb-0">
                                        Local plan pricing is aligned with Stripe for this selected plan.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($canChangeCurrentSubscriptionPlan)
                        <div class="alert alert-info mt-4">
                            This tenant already has a live Stripe subscription. Select a plan first to refresh the Stripe preview, then confirm the plan change.
                        </div>
                    @endif

                    @if($canChangeCurrentSubscriptionPlan && !empty($planChangePreview) && !($planChangePreview['ok'] ?? false))
                        <div class="alert alert-warning mt-4">
                            {{ $planChangePreview['message'] ?? 'Unable to preview the Stripe proration right now.' }}
                        </div>
                    @endif

                    @if($canChangeCurrentSubscriptionPlan && !empty($previewData))
                        <div class="card mt-4 border-primary">
                            <div class="card-body">
                                <h5 class="mb-3">Stripe Change Preview</h5>
                                <p class="text-muted mb-3">
                                    This section shows the immediate adjustment for the current plan change only.
                                </p>

                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong>This Change Adjustment:</strong>
                                            {{ number_format((float) ($previewData['current_change_total_decimal'] ?? 0), 2) }}
                                            {{ $previewData['currency'] ?? 'USD' }}
                                        </p>
                                        <p class="mb-2">
                                            <strong>Current Plan:</strong> {{ $plan->name ?? '-' }}
                                        </p>
                                        <p class="mb-2">
                                            <strong>Target Plan:</strong> {{ $selectedPlan->name ?? '-' }}
                                        </p>
                                    </div>

                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong>Preview Generated At:</strong>
                                            {{ !empty($previewData['proration_date']) ? \Carbon\Carbon::createFromTimestamp($previewData['proration_date'])->format('Y-m-d H:i:s') : '-' }}
                                        </p>
                                        <p class="mb-2">
                                            <strong>Amount Due On Stripe Preview:</strong>
                                            {{ number_format((float) ($previewData['amount_due_decimal'] ?? 0), 2) }}
                                            {{ $previewData['currency'] ?? 'USD' }}
                                        </p>
                                    </div>
                                </div>

                                @if(!empty($previewData['current_change_lines']))
                                    <hr>
                                    <h6 class="mb-3">Current Change Lines</h6>

                                    @foreach($previewData['current_change_lines'] as $line)
                                        <div class="border rounded p-3 mb-2">
                                            <div class="d-flex justify-content-between gap-3">
                                                <div>
                                                    <div class="fw-semibold">{{ $line['description'] ?? 'Stripe proration line' }}</div>
                                                    <div class="small text-muted">
                                                        @if(!empty($line['period_start']) && !empty($line['period_end']))
                                                            {{ \Carbon\Carbon::createFromTimestamp($line['period_start'])->format('Y-m-d H:i') }}
                                                            →
                                                            {{ \Carbon\Carbon::createFromTimestamp($line['period_end'])->format('Y-m-d H:i') }}
                                                        @else
                                                            Stripe preview line
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="fw-semibold">
                                                    {{ number_format((float) ($line['amount_decimal'] ?? 0), 2) }}
                                                    {{ $line['currency'] ?? ($previewData['currency'] ?? 'USD') }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="alert alert-light mt-3 mb-0">
                                        Stripe did not return isolated current-change proration lines for this preview.
                                    </div>
                                @endif

                                @if(!empty($previewData['older_pending_proration_lines']))
                                    <hr>
                                    <div class="alert alert-warning mb-3">
                                        Stripe also detected pending proration items from earlier changes in the same billing cycle.
                                        These older pending items are not part of the current change adjustment shown above.
                                    </div>

                                    <p class="mb-2">
                                        <strong>Older Pending Proration Total:</strong>
                                        {{ number_format((float) ($previewData['older_pending_proration_total_decimal'] ?? 0), 2) }}
                                        {{ $previewData['currency'] ?? 'USD' }}
                                    </p>

                                    <details class="mt-3">
                                        <summary class="fw-semibold">Show older pending proration lines</summary>

                                        <div class="mt-3">
                                            @foreach($previewData['older_pending_proration_lines'] as $line)
                                                <div class="border rounded p-3 mb-2">
                                                    <div class="d-flex justify-content-between gap-3">
                                                        <div>
                                                            <div class="fw-semibold">{{ $line['description'] ?? 'Older Stripe proration line' }}</div>
                                                            <div class="small text-muted">
                                                                @if(!empty($line['period_start']) && !empty($line['period_end']))
                                                                    {{ \Carbon\Carbon::createFromTimestamp($line['period_start'])->format('Y-m-d H:i') }}
                                                                    →
                                                                    {{ \Carbon\Carbon::createFromTimestamp($line['period_end'])->format('Y-m-d H:i') }}
                                                                @else
                                                                    Stripe pending line
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="fw-semibold">
                                                            {{ number_format((float) ($line['amount_decimal'] ?? 0), 2) }}
                                                            {{ $line['currency'] ?? ($previewData['currency'] ?? 'USD') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
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
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    @disabled(empty($selectedPlanId) || ($canChangeCurrentSubscriptionPlan && ($isSameCurrentPaidPlan ?? false)))
                                >
                                    {{ $billingSubmitLabel }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Billing Summary</h6>

                            <p class="mb-2"><strong>Billing Product:</strong> {{ $productBillingLabel }}</p>
                            <p class="mb-2"><strong>Tenant:</strong> {{ $tenant->id ?? '-' }}</p>
                            <p class="mb-2"><strong>Current Plan:</strong> {{ $plan->name ?? 'N/A' }}</p>
                            <p class="mb-2"><strong>Current Status:</strong> {{ ucfirst(str_replace('_', ' ', $billingState['status'] ?? 'unknown')) }}</p>
                            <p class="mb-2"><strong>Subscription ID:</strong> {{ $subscription->id ?? '-' }}</p>
                            <p class="mb-2"><strong>Gateway Customer ID:</strong> {{ $subscription->gateway_customer_id ?? '-' }}</p>
                            <p class="mb-2"><strong>Gateway Subscription ID:</strong> {{ $subscription->gateway_subscription_id ?? '-' }}</p>
                            <p class="mb-4"><strong>Gateway Price ID:</strong> {{ $subscription->gateway_price_id ?? '-' }}</p>

                            @if(!empty($subscription->gateway_customer_id))
                                <div class="d-grid gap-2">
                                    @if($canUpdatePaymentMethodInline ?? false)
                                        <button type="button" id="open-inline-payment-method" class="btn btn-light w-100">
                                            Update {{ $productBillingLabel }} Payment Method
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('automotive.admin.billing.portal') }}">
                                            @csrf
                                            @if(!empty($focusedWorkspaceProduct['product_code']))
                                                <input type="hidden" name="workspace_product" value="{{ $focusedWorkspaceProduct['product_code'] }}">
                                            @endif
                                            <button type="submit" class="btn btn-light w-100">
                                                Update {{ $productBillingLabel }} Payment Method
                                            </button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('automotive.admin.billing.portal') }}">
                                        @csrf
                                        @if(!empty($focusedWorkspaceProduct['product_code']))
                                            <input type="hidden" name="workspace_product" value="{{ $focusedWorkspaceProduct['product_code'] }}">
                                        @endif
                                        <button type="submit" class="btn btn-outline-primary w-100">
                                            Manage {{ $productBillingLabel }} Billing
                                        </button>
                                    </form>

                                    @if(($billingState['status'] ?? '') === 'active')
                                        <form method="POST" action="{{ route('automotive.admin.billing.cancel-subscription') }}">
                                            @csrf
                                            @if(!empty($focusedWorkspaceProduct['product_code']))
                                                <input type="hidden" name="workspace_product" value="{{ $focusedWorkspaceProduct['product_code'] }}">
                                            @endif
                                            <button type="submit" class="btn btn-outline-danger w-100">
                                                Cancel at Period End
                                            </button>
                                        </form>
                                    @endif

                                    @if(($billingState['status'] ?? '') === 'canceled')
                                        <form method="POST" action="{{ route('automotive.admin.billing.resume-subscription') }}">
                                            @csrf
                                            @if(!empty($focusedWorkspaceProduct['product_code']))
                                                <input type="hidden" name="workspace_product" value="{{ $focusedWorkspaceProduct['product_code'] }}">
                                            @endif
                                            <button type="submit" class="btn btn-success w-100">
                                                Resume Subscription
                                            </button>
                                        </form>
                                    @endif

                                    @if(in_array($billingState['status'] ?? '', ['past_due', 'grace_period', 'suspended', 'expired'], true))
                                        <form method="POST" action="{{ route('automotive.admin.billing.portal') }}">
                                            @csrf
                                            @if(!empty($focusedWorkspaceProduct['product_code']))
                                                <input type="hidden" name="workspace_product" value="{{ $focusedWorkspaceProduct['product_code'] }}">
                                            @endif
                                            <button type="submit" class="btn btn-warning w-100">
                                                Retry / Reactivate {{ $productBillingLabel }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @else
                                <div class="alert alert-info mb-0">
                                    @if($isAttachedBillingContext ?? false)
                                        {{ $productBillingLabel }} can start checkout and plan changes here. Billing portal actions will appear after the first Stripe customer is linked to this product subscription.
                                    @else
                                        Billing portal will become available after the first Stripe subscription is linked to this tenant.
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($canUpdatePaymentMethodInline ?? false)
                        <div class="card mt-4" id="inline-payment-method-card" style="display: none;">
                            <div class="card-body">
                                <h6 class="mb-3">Secure Payment Method Update</h6>
                                <p class="text-muted mb-3">
                                    Your card details are collected securely by Stripe inside this form.
                                </p>

                                <div id="payment-method-inline-alert" class="alert d-none"></div>

                                <div id="payment-method-loader" class="text-muted small mb-3">
                                    Loading secure payment form...
                                </div>

                                <form id="payment-method-update-form">
                                    @csrf
                                    <div id="payment-method-element" class="mb-3"></div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" id="payment-method-submit-btn" class="btn btn-primary" disabled>
                                            Save Payment Method
                                        </button>

                                        <button type="button" id="close-inline-payment-method" class="btn btn-outline-secondary">
                                            Close
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">{{ $productBillingLabel }} Invoice History</h6>

                            @if(!($invoiceHistory['ok'] ?? true))
                                <div class="alert alert-warning mb-0">
                                    {{ $invoiceHistory['message'] ?? 'Unable to load invoice history right now.' }}
                                </div>
                            @elseif(empty($subscription->gateway_customer_id))
                                <div class="alert alert-info mb-0">
                                    Invoice history for {{ $productBillingLabel }} will appear after the first Stripe customer is linked to this billing context.
                                </div>
                            @elseif(empty($invoiceRows))
                                <div class="alert alert-light mb-0">
                                    No Stripe invoices were found for {{ $productBillingLabel }} yet.
                                </div>
                            @else
                                <div class="d-flex flex-column gap-3">
                                    @foreach($invoiceRows as $invoice)
                                        @php
                                            $status = strtolower((string) ($invoice['status'] ?? 'unknown'));

                                            $statusBadgeClass = match ($status) {
                                                'paid' => 'bg-success',
                                                'open' => 'bg-warning text-dark',
                                                'draft' => 'bg-secondary',
                                                'void' => 'bg-dark',
                                                'uncollectible' => 'bg-danger',
                                                default => 'bg-light text-dark',
                                            };
                                        @endphp

                                        <div class="border rounded p-3">
                                            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                                <div>
                                                    <div class="fw-semibold">{{ $invoice['number'] ?? ($invoice['id'] ?? 'Stripe invoice') }}</div>
                                                    <div class="small text-muted">
                                                        {{ !empty($invoice['created_at']) ? \Carbon\Carbon::createFromTimestamp($invoice['created_at'])->format('Y-m-d H:i') : '-' }}
                                                    </div>
                                                </div>

                                                <span class="badge {{ $statusBadgeClass }}">
                                                    {{ ucfirst($invoice['status'] ?? 'unknown') }}
                                                </span>
                                            </div>

                                            <p class="mb-1">
                                                <strong>Total:</strong>
                                                {{ number_format((float) ($invoice['total_decimal'] ?? 0), 2) }}
                                                {{ $invoice['currency'] ?? 'USD' }}
                                            </p>

                                            <p class="mb-1">
                                                <strong>Paid:</strong>
                                                {{ number_format((float) ($invoice['amount_paid_decimal'] ?? 0), 2) }}
                                                {{ $invoice['currency'] ?? 'USD' }}
                                            </p>

                                            <p class="mb-3">
                                                <strong>Due:</strong>
                                                {{ number_format((float) ($invoice['amount_due_decimal'] ?? 0), 2) }}
                                                {{ $invoice['currency'] ?? 'USD' }}
                                            </p>

                                            <div class="d-flex flex-wrap gap-2">
                                                @if(!empty($invoice['hosted_invoice_url']))
                                                    <a
                                                        href="{{ $invoice['hosted_invoice_url'] }}"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="btn btn-sm btn-outline-primary"
                                                    >
                                                        View
                                                    </a>
                                                @endif

                                                @if(!empty($invoice['invoice_pdf']))
                                                    <a
                                                        href="{{ $invoice['invoice_pdf'] }}"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="btn btn-sm btn-outline-secondary"
                                                    >
                                                        PDF
                                                    </a>
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const previewForm = document.getElementById('billing-plan-preview-form');

            if (previewForm) {
                const radios = previewForm.querySelectorAll('input[name="target_plan_id"]');

                radios.forEach(function (radio) {
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
                        try {
                            paymentElement.unmount();
                        } catch (e) {
                        }
                    }

                    paymentElement = null;
                    elements = null;
                    clientSecret = null;
                    initialized = false;
                    isSubmitting = false;

                    if (loader) {
                        loader.style.display = 'block';
                        loader.textContent = 'Loading secure payment form...';
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
                        const response = await fetch(@json(route('automotive.admin.billing.payment-method.setup-intent')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                workspace_product: workspaceProduct,
                            }),
                        });

                        const payload = await response.json();

                        if (!response.ok || !payload.ok || !payload.client_secret) {
                            throw new Error(payload.message || 'Unable to initialize the secure payment form.');
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

                        showAlert('error', error.message || 'Unable to load the secure payment form right now.');
                    }
                }

                async function handleSubmit(event) {
                    event.preventDefault();

                    if (isSubmitting) {
                        return;
                    }

                    if (!stripe || !elements || !clientSecret || setupIntentConsumed) {
                        showAlert('error', 'Please reopen the payment method form to generate a fresh secure session.');
                        return;
                    }

                    clearAlert();
                    isSubmitting = true;
                    submitBtn.disabled = true;

                    try {
                        const result = await stripe.confirmSetup({
                            elements,
                            confirmParams: {},
                            redirect: 'if_required',
                        });

                        if (result.error) {
                            throw new Error(result.error.message || 'Stripe could not confirm the payment method.');
                        }

                        const setupIntent = result.setupIntent || null;
                        const paymentMethodId = setupIntent?.payment_method || null;

                        if (!paymentMethodId) {
                            throw new Error('Stripe did not return a payment method ID.');
                        }

                        const saveResponse = await fetch(@json(route('automotive.admin.billing.payment-method.default')), {
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
                            throw new Error(savePayload.message || 'Unable to save the default payment method.');
                        }

                        setupIntentConsumed = true;
                        showAlert('success', savePayload.message || 'Payment method updated successfully.');
                    } catch (error) {
                        showAlert('error', error.message || 'Unable to update the payment method right now.');
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
