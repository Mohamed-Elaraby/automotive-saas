<?php

namespace Tests\Feature\Billing;

use App\Data\AdminNotificationData;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\BillingNotificationService;
use App\Services\Notifications\AdminNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BillingNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_checkout_completed_notification_dispatches_admin_notification_with_expected_payload(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_test_checkout_completed',
            'gateway_checkout_session_id' => 'cs_test_checkout_completed',
            'gateway_customer_id' => 'cus_test_checkout_completed',
            'gateway_price_id' => 'price_test_checkout_completed',
        ]);

        $adminNotificationService = Mockery::mock(AdminNotificationService::class);
        $adminNotificationService->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (AdminNotificationData $data) use ($subscription) {
                return $data->type === 'billing'
                    && $data->title === 'Checkout Session Completed'
                    && $data->severity === 'success'
                    && $data->sourceType === 'subscription'
                    && $data->sourceId === (int) $subscription->id
                    && $data->routeName === 'admin.subscriptions.show'
                    && $data->tenantId === (string) $subscription->tenant_id
                    && ($data->contextPayload['event'] ?? null) === 'checkout_session_completed'
                    && ($data->contextPayload['stripe_event'] ?? null) === 'checkout.session.completed'
                    && ($data->contextPayload['gateway_subscription_id'] ?? null) === 'sub_test_checkout_completed'
                    && ($data->contextPayload['gateway_checkout_session_id'] ?? null) === 'cs_test_checkout_completed'
                    && ($data->contextPayload['gateway_customer_id'] ?? null) === 'cus_test_checkout_completed';
            }));

        $service = new BillingNotificationService($adminNotificationService);

        $service->checkoutCompleted($subscription, [
            'stripe_event' => 'checkout.session.completed',
            'checkout_session_id' => 'cs_test_checkout_completed',
            'checkout_mode' => 'subscription',
            'customer_id' => 'cus_test_checkout_completed',
            'payment_status' => 'paid',
            'subscription_id_from_session' => 'sub_test_checkout_completed',
            'subscription_row_id_from_metadata' => $subscription->id,
        ]);

        $this->assertTrue(true);
    }

    public function test_payment_failed_notification_dispatches_admin_notification_with_expected_payload(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'past_due',
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_test_payment_failed',
            'gateway_checkout_session_id' => 'cs_test_payment_failed',
            'gateway_customer_id' => 'cus_test_payment_failed',
            'gateway_price_id' => 'price_test_payment_failed',
            'payment_failures_count' => 1,
        ]);

        $adminNotificationService = Mockery::mock(AdminNotificationService::class);
        $adminNotificationService->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (AdminNotificationData $data) use ($subscription) {
                return $data->type === 'billing'
                    && $data->title === 'Payment Failed'
                    && $data->severity === 'error'
                    && $data->sourceId === (int) $subscription->id
                    && ($data->contextPayload['event'] ?? null) === 'payment_failed'
                    && ($data->contextPayload['stripe_event'] ?? null) === 'invoice.payment_failed'
                    && ($data->contextPayload['invoice_id'] ?? null) === 'in_test_payment_failed'
                    && ($data->contextPayload['gateway_subscription_id'] ?? null) === 'sub_test_payment_failed';
            }));

        $service = new BillingNotificationService($adminNotificationService);

        $service->paymentFailed($subscription, [
            'stripe_event' => 'invoice.payment_failed',
            'invoice_id' => 'in_test_payment_failed',
            'billing_reason' => 'subscription_cycle',
            'attempt_count' => 1,
            'amount_due' => 19900,
            'amount_paid' => 0,
            'currency' => 'usd',
        ]);

        $this->assertTrue(true);
    }

    public function test_invoice_paid_notification_dispatches_admin_notification_with_expected_payload(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_test_invoice_paid',
            'gateway_checkout_session_id' => 'cs_test_invoice_paid',
            'gateway_customer_id' => 'cus_test_invoice_paid',
            'gateway_price_id' => 'price_test_invoice_paid',
        ]);

        $adminNotificationService = Mockery::mock(AdminNotificationService::class);
        $adminNotificationService->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (AdminNotificationData $data) use ($subscription) {
                return $data->type === 'billing'
                    && $data->title === 'Invoice Paid'
                    && $data->severity === 'success'
                    && $data->sourceId === (int) $subscription->id
                    && ($data->contextPayload['event'] ?? null) === 'invoice_paid'
                    && ($data->contextPayload['stripe_event'] ?? null) === 'invoice.paid'
                    && ($data->contextPayload['invoice_id'] ?? null) === 'in_test_invoice_paid'
                    && ($data->contextPayload['gateway_subscription_id'] ?? null) === 'sub_test_invoice_paid';
            }));

        $service = new BillingNotificationService($adminNotificationService);

        $service->invoicePaid($subscription, [
            'stripe_event' => 'invoice.paid',
            'invoice_id' => 'in_test_invoice_paid',
            'billing_reason' => 'subscription_cycle',
            'amount_due' => 19900,
            'amount_paid' => 19900,
            'currency' => 'usd',
        ]);

        $this->assertTrue(true);
    }

    public function test_subscription_cancelled_notification_dispatches_admin_notification_with_expected_payload(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_test_cancelled',
            'gateway_checkout_session_id' => 'cs_test_cancelled',
            'gateway_customer_id' => 'cus_test_cancelled',
            'gateway_price_id' => 'price_test_cancelled',
        ]);

        $adminNotificationService = Mockery::mock(AdminNotificationService::class);
        $adminNotificationService->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (AdminNotificationData $data) use ($subscription) {
                return $data->type === 'billing'
                    && $data->title === 'Subscription Cancelled'
                    && $data->severity === 'warning'
                    && $data->sourceId === (int) $subscription->id
                    && ($data->contextPayload['event'] ?? null) === 'subscription_cancelled'
                    && ($data->contextPayload['stripe_event'] ?? null) === 'customer.subscription.updated';
            }));

        $service = new BillingNotificationService($adminNotificationService);

        $service->subscriptionCancelled($subscription, [
            'stripe_event' => 'customer.subscription.updated',
            'cancel_at_period_end' => true,
            'stripe_status' => 'active',
            'old_status' => 'active',
            'new_status' => 'active',
        ]);

        $this->assertTrue(true);
    }

    public function test_plan_changed_notification_dispatches_admin_notification_with_expected_payload(): void
    {
        $oldPlan = $this->createPlan('Starter', 'starter', 'price_old');
        $newPlan = $this->createPlan('Growth', 'growth', 'price_new');

        $subscription = $this->createSubscription([
            'status' => 'active',
            'plan_id' => $newPlan->id,
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_test_plan_changed',
            'gateway_checkout_session_id' => 'cs_test_plan_changed',
            'gateway_customer_id' => 'cus_test_plan_changed',
            'gateway_price_id' => 'price_new',
        ]);

        $adminNotificationService = Mockery::mock(AdminNotificationService::class);
        $adminNotificationService->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (AdminNotificationData $data) use ($subscription, $oldPlan, $newPlan) {
                return $data->type === 'billing'
                    && $data->title === 'Subscription Plan Changed'
                    && $data->severity === 'info'
                    && $data->sourceId === (int) $subscription->id
                    && ($data->contextPayload['event'] ?? null) === 'plan_changed'
                    && ($data->contextPayload['old_plan_id'] ?? null) === $oldPlan->id
                    && ($data->contextPayload['new_plan_id'] ?? null) === $newPlan->id
                    && ($data->contextPayload['new_gateway_price_id'] ?? null) === 'price_new';
            }));

        $service = new BillingNotificationService($adminNotificationService);

        $service->planChanged($subscription, [
            'stripe_event' => 'customer.subscription.updated',
            'old_plan_id' => $oldPlan->id,
            'new_plan_id' => $newPlan->id,
            'old_gateway_price_id' => 'price_old',
            'new_gateway_price_id' => 'price_new',
        ]);

        $this->assertTrue(true);
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'tenant_id' => 'tenant-notification-test',
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
            'gateway_customer_id' => 'cus_test_notification',
            'gateway_subscription_id' => 'sub_test_notification_' . uniqid(),
            'gateway_checkout_session_id' => 'cs_test_notification_' . uniqid(),
            'gateway_price_id' => 'price_test_notification',
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
