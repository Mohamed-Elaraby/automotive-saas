<?php

namespace Tests\Feature\Automotive\Portal;

use App\Models\Plan;
use App\Models\Product;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Automotive\StartTrialService;
use App\Services\Billing\TrialSignupCouponService;
use Illuminate\Support\Facades\Config;
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

        $automotiveProductId = Product::query()->where('code', 'automotive_service')->value('id');

        Plan::query()->create([
            'product_id' => $automotiveProductId,
            'name' => 'Trial',
            'slug' => 'trial',
            'description' => 'Trial plan',
            'price' => 0,
            'currency' => 'USD',
            'billing_period' => 'trial',
            'trial_days' => 14,
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
            'product_id' => $automotiveProductId,
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame(500, $result['status']);
        $this->assertSame('Provisioning failed.', $result['message']);

        $centralUser = User::query()->where('email', 'trial-user@example.test')->first();

        $this->assertNotNull($centralUser);
        $this->assertDatabaseMissing('tenants', ['id' => 'trial-company']);
        $this->assertDatabaseMissing('domains', ['tenant_id' => 'trial-company']);
        $this->assertDatabaseMissing('subscriptions', ['tenant_id' => 'trial-company']);
        $this->assertDatabaseMissing('tenant_product_subscriptions', ['tenant_id' => 'trial-company']);
        $this->assertDatabaseMissing('tenant_users', ['tenant_id' => 'trial-company']);

        $this->assertSame(0, DB::table('coupon_redemptions')->where('tenant_id', 'trial-company')->count());
    }

    public function test_it_creates_central_trial_records_and_returns_login_url_on_success(): void
    {
        Event::fake([TenantCreated::class]);
        Config::set('tenancy.bootstrappers', []);

        $automotiveProductId = Product::query()->where('code', 'automotive_service')->value('id');

        Plan::query()->create([
            'product_id' => $automotiveProductId,
            'name' => 'Trial',
            'slug' => 'trial',
            'description' => 'Trial plan',
            'price' => 0,
            'currency' => 'USD',
            'billing_period' => 'trial',
            'trial_days' => 14,
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
                    && ($payload['--tenants'][0] ?? null) === 'trial-success';
            }))
            ->andReturn(0);

        $service = app(StartTrialService::class);

        $result = $service->start([
            'name' => 'Success User',
            'email' => 'trial-success@example.test',
            'password' => 'secret-pass',
            'company_name' => 'Success Company',
            'subdomain' => 'trial-success',
            'coupon_code' => '',
            'base_host' => 'example.test',
            'product_id' => $automotiveProductId,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(201, $result['status']);
        $this->assertSame('trial-success', $result['tenant_id']);
        $this->assertSame('trial-success.example.test', $result['domain']);
        $this->assertSame('https://trial-success.example.test/workspace', $result['login_url']);

        $centralUser = User::query()->where('email', 'trial-success@example.test')->firstOrFail();

        $this->assertDatabaseHas('tenants', ['id' => 'trial-success']);
        $this->assertDatabaseHas('domains', [
            'tenant_id' => 'trial-success',
            'domain' => 'trial-success.example.test',
        ]);
        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => 'trial-success',
            'user_id' => $centralUser->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => 'trial-success',
            'status' => 'trialing',
        ]);
        $this->assertDatabaseHas('tenant_product_subscriptions', [
            'tenant_id' => 'trial-success',
            'status' => 'trialing',
        ]);

        $productSubscription = TenantProductSubscription::query()
            ->where('tenant_id', 'trial-success')
            ->first();

        $this->assertNotNull($productSubscription);
        $this->assertNotNull($productSubscription->legacy_subscription_id);
    }

    public function test_it_can_create_a_trial_for_a_non_automotive_first_product(): void
    {
        Event::fake([TenantCreated::class]);
        Config::set('tenancy.bootstrappers', []);

        $product = Product::query()->create([
            'code' => 'accounting_trial_' . uniqid(),
            'name' => 'Accounting Suite',
            'slug' => 'accounting-suite-' . uniqid(),
            'is_active' => true,
        ]);

        $trialPlan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Accounting Trial',
            'slug' => 'accounting-trial-' . uniqid(),
            'description' => 'Accounting trial plan',
            'price' => 0,
            'currency' => 'USD',
            'billing_period' => 'trial',
            'trial_days' => 21,
            'is_active' => true,
            'stripe_product_id' => null,
            'stripe_price_id' => null,
        ]);

        $couponService = Mockery::mock(TrialSignupCouponService::class);
        $couponService->shouldReceive('validateForTrialSignup')
            ->once()
            ->withArgs(function (string $couponCode, string $tenantId, ?int $planId) use ($trialPlan): bool {
                return $couponCode === '' && $planId === $trialPlan->id && $tenantId === 'accounting-first';
            })
            ->andReturn([
                'ok' => true,
                'coupon' => null,
                'eligibility' => null,
            ]);
        $couponService->shouldNotReceive('attachCouponToSubscription');

        $this->app->instance(TrialSignupCouponService::class, $couponService);

        Artisan::shouldReceive('call')
            ->once()
            ->andReturn(0);

        $service = app(StartTrialService::class);

        $result = $service->start([
            'name' => 'Accounting User',
            'email' => 'accounting-trial@example.test',
            'password' => 'secret-pass',
            'company_name' => 'Accounting Company',
            'subdomain' => 'accounting-first',
            'coupon_code' => '',
            'base_host' => 'example.test',
            'product_id' => $product->id,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => 'accounting-first',
            'plan_id' => $trialPlan->id,
            'status' => 'trialing',
        ]);
        $this->assertDatabaseHas('tenant_product_subscriptions', [
            'tenant_id' => 'accounting-first',
            'product_id' => $product->id,
            'plan_id' => $trialPlan->id,
            'status' => 'trialing',
        ]);

        $subscription = DB::table('subscriptions')
            ->where('tenant_id', 'accounting-first')
            ->first();

        $this->assertNotNull($subscription);
        $this->assertSame(
            now()->addDays(21)->format('Y-m-d'),
            \Carbon\Carbon::parse($subscription->trial_ends_at)->format('Y-m-d')
        );
    }
}
