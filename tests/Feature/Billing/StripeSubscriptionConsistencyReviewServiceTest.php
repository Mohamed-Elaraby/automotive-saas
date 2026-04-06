<?php

namespace Tests\Feature\Billing;

use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\TenantProductSubscription;
use App\Services\Billing\StripeSubscriptionConsistencyReviewService;
use App\Services\Billing\StripeSubscriptionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StripeSubscriptionConsistencyReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_reports_local_consistency_gaps_without_sync(): void
    {
        $plan = $this->createPlan('price_expected_001');

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-review-gap',
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'payment_failures_count' => 0,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_review_gap',
            'gateway_subscription_id' => null,
            'gateway_checkout_session_id' => 'cs_review_gap',
            'gateway_price_id' => 'price_wrong_001',
        ]);

        BillingInvoice::query()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'gateway' => 'stripe',
            'gateway_invoice_id' => 'in_review_mixed_001',
            'gateway_customer_id' => 'cus_review_gap',
            'gateway_subscription_id' => 'sub_other_001',
            'invoice_number' => 'INV-MIXED-001',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'USD',
            'total_minor' => 1000,
            'total_decimal' => 10.00,
            'amount_paid_minor' => 1000,
            'amount_paid_decimal' => 10.00,
            'amount_due_minor' => 0,
            'amount_due_decimal' => 0.00,
            'issued_at' => now(),
            'raw_payload' => [],
        ]);

        $review = app(StripeSubscriptionConsistencyReviewService::class)
            ->review(false, null, $subscription->id, 10)
            ->first();

        $this->assertSame('NEEDS_REVIEW', $review['result']);
        $this->assertSame('SKIPPED', $review['sync_state']);
        $this->assertStringContainsString('recoverable_missing_gateway_subscription_id', $review['issues_after']);
        $this->assertStringContainsString('local_plan_price_mismatch', $review['issues_after']);
        $this->assertStringContainsString('missing_product_subscription_mirror', $review['issues_after']);
        $this->assertStringContainsString('mixed_customer_invoice_history', $review['issues_after']);
        $this->assertSame(1, $review['mixed_customer_invoice_count']);
    }

    public function test_it_can_report_a_clean_state_after_sync_and_mirror_alignment(): void
    {
        $plan = $this->createPlan('price_sync_clean_001');

        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-review-sync',
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'payment_failures_count' => 1,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_old_sync',
            'gateway_subscription_id' => null,
            'gateway_checkout_session_id' => 'cs_sync_clean',
            'gateway_price_id' => 'price_stale_before',
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $subscription->tenant_id,
            'product_id' => $plan->product_id,
            'plan_id' => $plan->id,
            'legacy_subscription_id' => $subscription->id,
            'status' => 'past_due',
            'payment_failures_count' => 1,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_old_sync',
            'gateway_subscription_id' => null,
            'gateway_checkout_session_id' => 'cs_sync_clean',
            'gateway_price_id' => 'price_stale_before',
        ]);

        $syncService = Mockery::mock(StripeSubscriptionSyncService::class);
        $syncService->shouldReceive('syncLocalStripeSubscription')
            ->once()
            ->andReturnUsing(function (Subscription $subscription) use ($plan) {
                $subscription->fill([
                    'status' => 'active',
                    'gateway_customer_id' => 'cus_sync_clean',
                    'gateway_subscription_id' => 'sub_sync_clean_001',
                    'gateway_price_id' => 'price_sync_clean_001',
                ]);
                $subscription->save();

                TenantProductSubscription::query()
                    ->where('legacy_subscription_id', $subscription->id)
                    ->update([
                        'status' => 'active',
                        'gateway_customer_id' => 'cus_sync_clean',
                        'gateway_subscription_id' => 'sub_sync_clean_001',
                        'gateway_price_id' => 'price_sync_clean_001',
                    ]);

                return $subscription->fresh();
            });

        $this->app->instance(StripeSubscriptionSyncService::class, $syncService);

        BillingInvoice::query()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'gateway' => 'stripe',
            'gateway_invoice_id' => 'in_sync_clean_001',
            'gateway_customer_id' => 'cus_sync_clean',
            'gateway_subscription_id' => 'sub_sync_clean_001',
            'invoice_number' => 'INV-CLEAN-001',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'USD',
            'total_minor' => 19900,
            'total_decimal' => 199.00,
            'amount_paid_minor' => 19900,
            'amount_paid_decimal' => 199.00,
            'amount_due_minor' => 0,
            'amount_due_decimal' => 0.00,
            'issued_at' => now(),
            'raw_payload' => [],
        ]);

        $review = app(StripeSubscriptionConsistencyReviewService::class)
            ->review(true, null, $subscription->id, 10)
            ->first();

        $this->assertSame('OK', $review['result']);
        $this->assertSame('SYNCED', $review['sync_state']);
        $this->assertSame('MATCHED', $review['mirror_status']);
        $this->assertSame('active', $review['status_after']);
        $this->assertSame('sub_sync_clean_001', $review['gateway_subscription_id_after']);
        $this->assertSame(1, $review['subscription_invoice_count']);
        $this->assertSame('OK', $review['issues_after']);
    }

    protected function createPlan(string $stripePriceId): Plan
    {
        return Plan::query()->create([
            'product_id' => Product::query()->where('code', 'automotive_service')->value('id'),
            'name' => 'Review Plan',
            'slug' => 'review-plan-' . uniqid(),
            'description' => 'Review test plan',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => $stripePriceId,
        ]);
    }
}
