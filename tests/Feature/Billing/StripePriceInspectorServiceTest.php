<?php

namespace Tests\Feature\Billing;

use App\Services\Billing\StripePriceInspectorService;
use Mockery;
use Tests\TestCase;

class StripePriceInspectorServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_audit_plan_is_not_aligned_when_stripe_price_is_inactive(): void
    {
        $service = Mockery::mock(StripePriceInspectorService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('inspectPrice')
            ->once()
            ->with('price_inactive_001')
            ->andReturn([
                'success' => true,
                'exists' => true,
                'price_id' => 'price_inactive_001',
                'active' => false,
                'unit_amount' => 19900,
                'unit_amount_decimal' => 199.0,
                'currency' => 'USD',
                'interval' => 'month',
                'interval_count' => 1,
                'product_id' => 'prod_001',
                'product_name' => 'Growth',
                'product_description' => null,
                'message' => 'Stripe price loaded successfully.',
            ]);

        $audit = $service->auditPlan((object) [
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'stripe_price_id' => 'price_inactive_001',
        ]);

        $this->assertFalse($audit['checks']['active_matches']);
        $this->assertFalse($audit['checks']['is_aligned']);
        $this->assertSame('The linked Stripe price exists but is inactive.', $audit['message']);
    }
}
