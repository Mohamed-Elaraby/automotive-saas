<?php

namespace Tests\Feature\Billing;

use App\Services\Billing\Gateways\StripePaymentGateway;
use App\Services\Billing\StripePriceInspectorService;
use Mockery;
use Tests\TestCase;

class StripePaymentGatewayTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_rejects_checkout_when_linked_stripe_price_is_inactive(): void
    {
        $inspector = Mockery::mock(StripePriceInspectorService::class);
        $inspector->shouldReceive('auditPlan')
            ->once()
            ->andReturn([
                'stripe' => [
                    'exists' => true,
                    'active' => false,
                ],
                'checks' => [
                    'is_aligned' => false,
                ],
            ]);

        $gateway = new StripePaymentGateway($inspector);

        $result = $gateway->createRenewalSession([
            'stripe_price_id' => 'price_inactive_001',
            'plan_for_audit' => [
                'id' => 1,
                'price' => 199,
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'stripe_price_id' => 'price_inactive_001',
            ],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(
            'The selected paid plan is linked to an inactive Stripe price. Sync the plan prices in admin before checkout.',
            $result['message']
        );
    }
}
