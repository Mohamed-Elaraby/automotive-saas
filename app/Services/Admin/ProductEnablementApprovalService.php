<?php

namespace App\Services\Admin;

use App\Data\CustomerPortalNotificationData;
use App\Models\ProductEnablementRequest;
use App\Models\TenantProductSubscription;
use App\Services\Notifications\CustomerPortalNotificationService;
use Illuminate\Support\Facades\DB;

class ProductEnablementApprovalService
{
    public function __construct(
        protected CustomerPortalNotificationService $customerPortalNotificationService
    ) {
    }

    public function approve(ProductEnablementRequest $request): TenantProductSubscription
    {
        return DB::transaction(function () use ($request): TenantProductSubscription {
            $request->loadMissing(['product', 'user']);

            $request->update([
                'status' => 'approved',
                'approved_at' => now(),
                'rejected_at' => null,
            ]);

            $attachedSubscription = TenantProductSubscription::query()
                ->where('tenant_id', $request->tenant_id)
                ->where('product_id', $request->product_id)
                ->whereNotIn('status', ['expired', 'canceled'])
                ->orderByDesc('id')
                ->first();

            if ($attachedSubscription) {
                $this->emitDecisionNotifications($request, 'approved', true);

                return $attachedSubscription;
            }

            $manualSubscription = TenantProductSubscription::query()
                ->where('tenant_id', $request->tenant_id)
                ->where('product_id', $request->product_id)
                ->whereNull('legacy_subscription_id')
                ->orderByDesc('id')
                ->first();

            if ($manualSubscription) {
                $manualSubscription->fill([
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'grace_ends_at' => null,
                    'last_payment_failed_at' => null,
                    'past_due_started_at' => null,
                    'suspended_at' => null,
                    'cancelled_at' => null,
                    'payment_failures_count' => 0,
                    'ends_at' => null,
                    'external_id' => null,
                    'gateway' => null,
                    'gateway_customer_id' => null,
                    'gateway_subscription_id' => null,
                    'gateway_checkout_session_id' => null,
                    'gateway_price_id' => null,
                ])->save();

                $freshSubscription = $manualSubscription->fresh();
                $this->emitDecisionNotifications($request, 'approved', true);

                return $freshSubscription;
            }

            $subscription = TenantProductSubscription::query()->create([
                'tenant_id' => $request->tenant_id,
                'product_id' => $request->product_id,
                'plan_id' => null,
                'status' => 'active',
                'payment_failures_count' => 0,
            ]);

            $this->emitDecisionNotifications($request, 'approved', true);

            return $subscription;
        });
    }

    public function reject(ProductEnablementRequest $request): void
    {
        DB::transaction(function () use ($request): void {
            $request->loadMissing(['product', 'user']);

            $request->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'approved_at' => null,
            ]);

            $this->emitDecisionNotifications($request, 'rejected', false);
        });
    }

    protected function emitDecisionNotifications(ProductEnablementRequest $request, string $decision, bool $attached): void
    {
        $productName = (string) ($request->product?->name ?: 'Requested product');
        $decisionLabel = $decision === 'approved' ? 'approved' : 'rejected';

        $portalTitle = $decision === 'approved'
            ? 'Product enablement approved'
            : 'Product enablement request rejected';

        $portalMessage = $decision === 'approved'
            ? "{$productName} is now available in your workspace."
            : "Your request to enable {$productName} was rejected. You can review the product and submit a new request later.";

        if (! empty($request->user_id)) {
            $this->customerPortalNotificationService->create(new CustomerPortalNotificationData(
                userId: (int) $request->user_id,
                type: 'product_enablement_request',
                title: $portalTitle,
                message: $portalMessage,
                severity: $decision === 'approved' ? 'success' : 'warning',
                tenantId: $request->tenant_id,
                productId: $request->product_id,
                targetUrl: filled($request->product?->slug)
                    ? route('automotive.portal', ['product' => $request->product->slug])
                    : route('automotive.portal'),
                contextPayload: [
                    'event' => 'product_enablement_request_' . $decisionLabel,
                    'request_id' => $request->id,
                    'attached' => $attached,
                ],
            ));
        }
    }
}
