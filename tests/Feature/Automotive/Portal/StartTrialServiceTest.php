<?php

namespace Tests\Feature\Automotive\Portal;

use App\Models\Plan;
use App\Models\User;
use App\Services\Automotive\StartTrialService;
use App\Services\Billing\TrialSignupCouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TestCase;

class StartTrialServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_rolls_back_central_records_when_trial_provisioning_fails(): void
    {
        Event::fake([TenantCreated::class]);

        Plan::query()->create([
            'name' => 'Trial',
            'slug' => 'trial',
            'description' => 'Trial plan',
            'price' => 0,
            'currency' => 'USD',
            'billing_period' => 'trial',
            'is_active' => true,
            'stripe_product_id' => null,
            'stripe_price_id' => null,
        ]);

        $couponService = Mockery::mock(TrialSignupCouponService::class);
        $couponService->shouldReceive('validateForTrialSignup')
            ->once()
            ->andReturn([
                'ok' => true,
                'coupon' => null,
                'eligibility' => null,
            ]);
        $couponService->shouldNotReceive('attachCouponToSubscription');

        $this->app->instance(TrialSignupCouponService::class, $couponService);

        Artisan::shouldReceive('call')
            ->once()
            ->with('tenants:migrate', Mockery::on(function (array $payload): bool {
                return ($payload['--force'] ?? null) === true
                    && ! empty($payload['--tenants'][0]);
            }))
            ->andThrow(new \RuntimeException('Tenant migration failed.'));

        $service = app(StartTrialService::class);

        $result = $service->start([
            'name' => 'Trial User',
            'email' => 'trial-user@example.test',
            'password' => 'secret-pass',
            'company_name' => 'Trial Company',
            'subdomain' => 'trial-company',
            'coupon_code' => '',
            'base_host' => 'example.test',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame(500, $result['status']);
        $this->assertSame('Provisioning failed.', $result['message']);

        $centralUser = User::query()->where('email', 'trial-user@example.test')->first();

        $this->assertNotNull($centralUser);
        $this->assertDatabaseMissing('tenants', ['id' => 'trial-company']);
        $this->assertDatabaseMissing('domains', ['tenant_id' => 'trial-company']);
        $this->assertDatabaseMissing('subscriptions', ['tenant_id' => 'trial-company']);
        $this->assertDatabaseMissing('tenant_users', ['tenant_id' => 'trial-company']);

        $this->assertSame(0, DB::table('coupon_redemptions')->where('tenant_id', 'trial-company')->count());
    }
}
