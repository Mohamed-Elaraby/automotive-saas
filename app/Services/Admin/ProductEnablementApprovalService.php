<?php

namespace App\Services\Admin;

use App\Models\ProductEnablementRequest;
use App\Models\TenantProductSubscription;
use Illuminate\Support\Facades\DB;

class ProductEnablementApprovalService
{
    public function approve(ProductEnablementRequest $request): TenantProductSubscription
    {
        return DB::transaction(function () use ($request): TenantProductSubscription {
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

                return $manualSubscription->fresh();
            }

            return TenantProductSubscription::query()->create([
                'tenant_id' => $request->tenant_id,
                'product_id' => $request->product_id,
                'plan_id' => null,
                'status' => 'active',
                'payment_failures_count' => 0,
            ]);
        });
    }
}
