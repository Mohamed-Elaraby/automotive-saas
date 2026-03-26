<?php

namespace Tests\Feature\Billing;

use App\Data\AdminNotificationData;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBillingMonitoringStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_completed_admin_notification_data_contains_expected_monitoring_fields(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_monitor_checkout',
            'gateway_checkout_session_id' => 'cs_monitor_checkout',
            'gateway_customer_id' => 'cus_monitor_checkout',
            'gateway_price_id' => 'price_monitor_checkout',
        ]);

        $payload = $this->basePayload($subscription, [
            'event' => 'checkout_session_completed',
            'stripe_event' => 'checkout.session.completed',
            'checkout_session_id' => 'cs_monitor_checkout',
            'checkout_mode' => 'subscription',
            'customer_id' => 'cus_monitor_checkout',
            'payment_status' => 'paid',
            'subscription_id_from_session' => 'sub_monitor_checkout',
            'subscription_row_id_from_metadata' => $subscription->id,
        ]);

        $data = new AdminNotificationData(
            type: 'billing',
            title: 'Checkout Session Completed',
            message: "Checkout session completed successfully for subscription #{$subscription->id}.",
            severity: 'success',
            sourceType: 'subscription',
            sourceId: (int) $subscription->id,
            tenantId: (string) $subscription->tenant_id,
            routeName: 'admin.subscriptions.show',
            contextPayload: $payload,
        );

        $this->assertSame('billing', $data->type);
        $this->assertSame('Checkout Session Completed', $data->title);
        $this->assertSame('success', $data->severity);
        $this->assertSame('subscription', $data->sourceType);
        $this->assertSame((int) $subscription->id, $data->sourceId);
        $this->assertSame((string) $subscription->tenant_id, $data->tenantId);
        $this->assertSame('admin.subscriptions.show', $data->routeName);

        $this->assertSame('checkout_session_completed', $data->contextPayload['event'] ?? null);
        $this->assertSame('checkout.session.completed', $data->contextPayload['stripe_event'] ?? null);
        $this->assertSame('sub_monitor_checkout', $data->contextPayload['gateway_subscription_id'] ?? null);
        $this->assertSame('cs_monitor_checkout', $data->contextPayload['gateway_checkout_session_id'] ?? null);
        $this->assertSame('cus_monitor_checkout', $data->contextPayload['gateway_customer_id'] ?? null);
        $this->assertSame('price_monitor_checkout', $data->contextPayload['gateway_price_id'] ?? null);
    }

    public function test_payment_failed_admin_notification_data_contains_expected_monitoring_fields(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'past_due',
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_monitor_failed',
            'gateway_checkout_session_id' => 'cs_monitor_failed',
            'gateway_customer_id' => 'cus_monitor_failed',
            'gateway_price_id' => 'price_monitor_failed',
            'payment_failures_count' => 2,
        ]);

        $payload = $this->basePayload($subscription, [
            'event' => 'payment_failed',
            'stripe_event' => 'invoice.payment_failed',
            'invoice_id' => 'in_monitor_failed',
            'billing_reason' => 'subscription_cycle',
            'attempt_count' => 2,
            'amount_due' => 19900,
            'amount_paid' => 0,
            'currency' => 'usd',
        ]);

        $data = new AdminNotificationData(
            type: 'billing',
            title: 'Payment Failed',
            message: "Payment failed for subscription #{$subscription->id}.",
            severity: 'error',
            sourceType: 'subscription',
            sourceId: (int) $subscription->id,
            tenantId: (string) $subscription->tenant_id,
            routeName: 'admin.subscriptions.show',
            contextPayload: $payload,
        );

        $this->assertSame('Payment Failed', $data->title);
        $this->assertSame('error', $data->severity);
        $this->assertSame('payment_failed', $data->contextPayload['event'] ?? null);
        $this->assertSame('invoice.payment_failed', $data->contextPayload['stripe_event'] ?? null);
        $this->assertSame('in_monitor_failed', $data->contextPayload['invoice_id'] ?? null);
        $this->assertSame(2, $data->contextPayload['attempt_count'] ?? null);
        $this->assertSame('sub_monitor_failed', $data->contextPayload['gateway_subscription_id'] ?? null);
    }

    public function test_plan_changed_admin_notification_data_contains_old_and_new_plan_references(): void
    {
        $oldPlan = $this->createPlan('Starter', 'starter', 'price_old_monitor');
        $newPlan = $this->createPlan('Growth', 'growth', 'price_new_monitor');

        $subscription = $this->createSubscription([
            'status' => 'active',
            'plan_id' => $newPlan->id,
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_monitor_plan_changed',
            'gateway_checkout_session_id' => 'cs_monitor_plan_changed',
            'gateway_customer_id' => 'cus_monitor_plan_changed',
            'gateway_price_id' => 'price_new_monitor',
        ]);

        $payload = $this->basePayload($subscription, [
            'event' => 'plan_changed',
            'stripe_event' => 'customer.subscription.updated',
            'old_plan_id' => $oldPlan->id,
            'new_plan_id' => $newPlan->id,
            'old_gateway_price_id' => 'price_old_monitor',
            'new_gateway_price_id' => 'price_new_monitor',
        ]);

        $data = new AdminNotificationData(
            type: 'billing',
            title: 'Subscription Plan Changed',
            message: "Subscription #{$subscription->id} changed plans.",
            severity: 'info',
            sourceType: 'subscription',
            sourceId: (int) $subscription->id,
            tenantId: (string) $subscription->tenant_id,
            routeName: 'admin.subscriptions.show',
            contextPayload: $payload,
        );

        $this->assertSame('Subscription Plan Changed', $data->title);
        $this->assertSame('info', $data->severity);
        $this->assertSame('plan_changed', $data->contextPayload['event'] ?? null);
        $this->assertSame($oldPlan->id, $data->contextPayload['old_plan_id'] ?? null);
        $this->assertSame($newPlan->id, $data->contextPayload['new_plan_id'] ?? null);
        $this->assertSame('price_old_monitor', $data->contextPayload['old_gateway_price_id'] ?? null);
        $this->assertSame('price_new_monitor', $data->contextPayload['new_gateway_price_id'] ?? null);
    }

    public function test_subscription_cancelled_admin_notification_data_contains_cancellation_monitoring_fields(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_monitor_cancelled',
            'gateway_checkout_session_id' => 'cs_monitor_cancelled',
            'gateway_customer_id' => 'cus_monitor_cancelled',
            'gateway_price_id' => 'price_monitor_cancelled',
            'cancelled_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $payload = $this->basePayload($subscription, [
            'event' => 'subscription_cancelled',
            'stripe_event' => 'customer.subscription.updated',
            'cancel_at_period_end' => true,
            'stripe_status' => 'active',
            'old_status' => 'active',
            'new_status' => 'active',
        ]);

        $data = new AdminNotificationData(
            type: 'billing',
            title: 'Subscription Cancelled',
            message: "Subscription #{$subscription->id} was marked for cancellation.",
            severity: 'warning',
            sourceType: 'subscription',
            sourceId: (int) $subscription->id,
            tenantId: (string) $subscription->tenant_id,
            routeName: 'admin.subscriptions.show',
            contextPayload: $payload,
        );

        $this->assertSame('warning', $data->severity);
        $this->assertSame('subscription_cancelled', $data->contextPayload['event'] ?? null);
        $this->assertTrue((bool) ($data->contextPayload['cancel_at_period_end'] ?? false));
        $this->assertSame('sub_monitor_cancelled', $data->contextPayload['gateway_subscription_id'] ?? null);
        $this->assertNotNull($data->contextPayload['cancelled_at'] ?? null);
        $this->assertNotNull($data->contextPayload['ends_at'] ?? null);
    }

    protected function basePayload(Subscription $subscription, array $overrides = []): array
    {
        return array_merge([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
            'gateway' => $subscription->gateway,
            'gateway_subscription_id' => $subscription->gateway_subscription_id,
            'gateway_checkout_session_id' => $subscription->gateway_checkout_session_id,
            'gateway_customer_id' => $subscription->gateway_customer_id,
            'gateway_price_id' => $subscription->gateway_price_id,
            'trial_ends_at' => optional($subscription->trial_ends_at)?->format('Y-m-d H:i:s'),
            'grace_ends_at' => optional($subscription->grace_ends_at)?->format('Y-m-d H:i:s'),
            'last_payment_failed_at' => optional($subscription->last_payment_failed_at)?->format('Y-m-d H:i:s'),
            'past_due_started_at' => optional($subscription->past_due_started_at)?->format('Y-m-d H:i:s'),
            'suspended_at' => optional($subscription->suspended_at)?->format('Y-m-d H:i:s'),
            'cancelled_at' => optional($subscription->cancelled_at)?->format('Y-m-d H:i:s'),
            'ends_at' => optional($subscription->ends_at)?->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-monitoring-test',
            'plan_id' => $this->createPlan('Default', 'default-' . uniqid(), 'price_default_' . uniqid())->id,
            'status' => 'active',
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'last_payment_failed_at' => null,
            'past_due_started_at' => null,
            'suspended_at' => null,
            'cancelled_at' => null,
            'ends_at' => null,
            'payment_failures_count' => 0,
            'external_id' => null,
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_monitoring',
            'gateway_subscription_id' => 'sub_test_monitoring_' . uniqid(),
            'gateway_checkout_session_id' => 'cs_test_monitoring_' . uniqid(),
            'gateway_price_id' => 'price_test_monitoring',
        ], $overrides));
    }

    protected function createPlan(string $name, string $slug, ?string $stripePriceId): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug . '-' . uniqid(),
            'description' => $name . ' description',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => $stripePriceId,
        ]);
    }
}
