<?php

namespace App\Services\Automotive;

use App\Models\CustomerOnboardingProfile;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingPlanCatalogService;
use App\Services\Billing\PaymentGatewayManager;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Models\Domain;

class StartPaidCheckoutService
{
    public function __construct(
        protected BillingPlanCatalogService $billingPlanCatalogService,
        protected PaymentGatewayManager $paymentGatewayManager
    ) {
    }

    public function start(User $user, CustomerOnboardingProfile $profile, int $planId): array
    {
        $plan = $this->billingPlanCatalogService->findPaidPlanById($planId);

        if (! $plan) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'The selected paid plan was not found or is not active.',
                'errors' => [
                    'plan_id' => ['The selected paid plan was not found or is not active.'],
                ],
            ];
        }

        if (empty($plan->stripe_price_id)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'The selected paid plan is not linked to a Stripe price yet.',
                'errors' => [
                    'plan_id' => ['The selected paid plan is not linked to a Stripe price yet.'],
                ],
            ];
        }

        $workspace = $this->resolveWorkspaceForUser($user);
        $existingSubscription = $workspace['subscription'];

        if (
            $existingSubscription
            && (string) ($existingSubscription->status ?? '') === SubscriptionStatuses::ACTIVE
            && (string) ($existingSubscription->gateway ?? '') !== ''
        ) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'A live paid subscription already exists for this account. Manage billing from inside your system workspace.',
                'errors' => [
                    'portal' => ['A live paid subscription already exists for this account. Manage billing from inside your system workspace.'],
                ],
            ];
        }

        if (
            $existingSubscription
            && (string) ($existingSubscription->gateway ?? '') === 'stripe'
            && filled($existingSubscription->gateway_subscription_id)
        ) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'A live Stripe subscription already exists for this account. Manage billing from inside your system workspace.',
                'errors' => [
                    'portal' => ['A live Stripe subscription already exists for this account. Manage billing from inside your system workspace.'],
                ],
            ];
        }

        if (! empty($workspace['tenant_id'])) {
            $tenantId = (string) $workspace['tenant_id'];
            $subscription = $this->resolveSubscriptionModel(
                $workspace['subscription'],
                $tenantId,
                (int) $plan->id
            );
        } else {
            $created = $this->createWorkspaceWithPendingSubscription($user, $profile, (int) $plan->id);

            if (! ($created['ok'] ?? false)) {
                return $created;
            }

            $tenantId = (string) $created['tenant_id'];
            $subscription = $created['subscription'];
        }

        try {
            $session = $this->paymentGatewayManager
                ->driver('stripe')
                ->createRenewalSession([
                    'tenant_id' => $tenantId,
                    'subscription_row_id' => $subscription->id,
                    'plan_id' => $plan->id,
                    'stripe_price_id' => $plan->stripe_price_id,
                    'customer_email' => $user->email,
                    'success_url' => route('automotive.portal.checkout.success'),
                    'cancel_url' => route('automotive.portal.checkout.cancel'),
                    'plan_for_audit' => (array) $plan,
                ]);
        } catch (\Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Unable to start paid checkout right now.',
                'errors' => [
                    'portal' => ['Unable to start paid checkout right now.'],
                ],
            ];
        }

        if (! empty($session['success']) && ! empty($session['checkout_url']) && ! empty($session['session_id'])) {
            $subscription->fill([
                'gateway' => 'stripe',
                'gateway_checkout_session_id' => (string) $session['session_id'],
            ]);

            if ((string) $subscription->status !== SubscriptionStatuses::TRIALING) {
                $subscription->plan_id = (int) $plan->id;

                if (blank($subscription->status)) {
                    $subscription->status = SubscriptionStatuses::PAST_DUE;
                }
            }

            $subscription->save();

            return [
                'ok' => true,
                'status' => 201,
                'checkout_url' => (string) $session['checkout_url'],
                'tenant_id' => $tenantId,
                'subscription_id' => (int) $subscription->id,
            ];
        }

        return [
            'ok' => false,
            'status' => 422,
            'message' => $session['message'] ?? 'Unable to start the paid checkout session.',
            'errors' => [
                'portal' => [$session['message'] ?? 'Unable to start the paid checkout session.'],
            ],
        ];
    }

    protected function resolveWorkspaceForUser(User $user): array
    {
        $connection = $this->centralConnectionName();

        $tenantLink = DB::connection($connection)
            ->table('tenant_users')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        if (! $tenantLink) {
            return [
                'tenant_id' => null,
                'subscription' => null,
            ];
        }

        $subscription = DB::connection($connection)
            ->table('subscriptions')
            ->where('tenant_id', $tenantLink->tenant_id)
            ->orderByDesc('id')
            ->first();

        return [
            'tenant_id' => (string) $tenantLink->tenant_id,
            'subscription' => $subscription,
        ];
    }

    protected function resolveSubscriptionModel(?object $subscriptionRow, string $tenantId, int $planId): Subscription
    {
        if ($subscriptionRow && ! empty($subscriptionRow->id)) {
            $subscription = Subscription::query()->find($subscriptionRow->id);

            if ($subscription) {
                return $subscription;
            }
        }

        return Subscription::query()->create([
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'status' => SubscriptionStatuses::PAST_DUE,
            'trial_ends_at' => null,
            'ends_at' => null,
            'external_id' => null,
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
            'gateway_checkout_session_id' => null,
            'gateway_price_id' => null,
        ]);
    }

    protected function createWorkspaceWithPendingSubscription(
        User $user,
        CustomerOnboardingProfile $profile,
        int $planId
    ): array {
        $centralConnection = $this->centralConnectionName();
        $tenantId = strtolower(trim((string) $profile->subdomain));
        $baseHost = strtolower(trim((string) ($profile->base_host ?: request()->getHost())));
        $fullDomain = "{$tenantId}.{$baseHost}";

        if (Domain::query()->where('domain', $fullDomain)->exists()) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'This subdomain is already taken.',
                'errors' => [
                    'subdomain' => ['This subdomain is already taken.'],
                ],
            ];
        }

        if (Tenant::query()->where('id', $tenantId)->exists()) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'This subdomain is not available.',
                'errors' => [
                    'subdomain' => ['This subdomain is not available.'],
                ],
            ];
        }

        $tenant = Tenant::create([
            'id' => $tenantId,
            'data' => [
                'company_name' => $profile->company_name,
                'db_name' => 'tenant_' . $tenantId,
            ],
        ]);

        $subscriptionId = null;

        try {
            DB::connection($centralConnection)->transaction(function () use (
                $tenant,
                $user,
                $fullDomain,
                $centralConnection,
                $planId,
                &$subscriptionId
            ) {
                DB::connection($centralConnection)->table('domains')->insert([
                    'domain' => $fullDomain,
                    'tenant_id' => $tenant->id,
                ]);

                $subscriptionId = DB::connection($centralConnection)->table('subscriptions')->insertGetId([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $planId,
                    'status' => SubscriptionStatuses::PAST_DUE,
                    'trial_ends_at' => null,
                    'ends_at' => null,
                    'external_id' => null,
                    'gateway' => null,
                    'gateway_customer_id' => null,
                    'gateway_subscription_id' => null,
                    'gateway_checkout_session_id' => null,
                    'gateway_price_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::connection($centralConnection)->table('tenant_users')->insert([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->id],
                '--force' => true,
            ]);

            tenancy()->initialize($tenant);

            try {
                User::query()->firstOrCreate(
                    ['email' => $user->email],
                    [
                        'name' => $user->name,
                        'password' => $user->password,
                    ]
                );
            } finally {
                tenancy()->end();
                DB::purge('tenant');
            }
        } catch (\Throwable $e) {
            try {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            } catch (\Throwable) {
                //
            }

            DB::purge('tenant');

            DB::connection($centralConnection)->transaction(function () use ($tenant, $centralConnection) {
                DB::connection($centralConnection)->table('domains')
                    ->where('tenant_id', $tenant->id)
                    ->delete();

                DB::connection($centralConnection)->table('subscriptions')
                    ->where('tenant_id', $tenant->id)
                    ->delete();

                DB::connection($centralConnection)->table('tenant_users')
                    ->where('tenant_id', $tenant->id)
                    ->delete();

                DB::connection($centralConnection)->table('tenants')
                    ->where('id', $tenant->id)
                    ->delete();
            });

            report($e);

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Provisioning failed before checkout.',
                'errors' => [
                    'portal' => ['Provisioning failed before checkout.'],
                ],
            ];
        }

        $subscription = Subscription::query()->findOrFail($subscriptionId);

        return [
            'ok' => true,
            'tenant_id' => $tenant->id,
            'subscription' => $subscription,
        ];
    }

    protected function centralConnectionName(): string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }
}
