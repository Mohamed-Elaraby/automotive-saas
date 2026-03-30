<?php

namespace App\Services\Automotive;

use App\Models\CustomerOnboardingProfile;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\BillingPlanCatalogService;
use App\Services\Billing\PaymentGatewayManager;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Support\Facades\DB;

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
        $reservedTenantId = strtolower(trim((string) $profile->subdomain));

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
            $created = $this->createPendingSubscriptionWithoutProvisioning($reservedTenantId);

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
                'gateway_price_id' => (string) $plan->stripe_price_id,
            ]);

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
            $profile = CustomerOnboardingProfile::query()
                ->where('user_id', $user->id)
                ->first();

            if (! $profile || blank($profile->subdomain)) {
                return [
                    'tenant_id' => null,
                    'subscription' => null,
                ];
            }

            $reservedTenantId = strtolower(trim((string) $profile->subdomain));
            $subscription = DB::connection($connection)
                ->table('subscriptions')
                ->where('tenant_id', $reservedTenantId)
                ->orderByDesc('id')
                ->first();

            return [
                'tenant_id' => $subscription ? $reservedTenantId : null,
                'subscription' => $subscription,
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
            'plan_id' => null,
            'status' => null,
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

    protected function createPendingSubscriptionWithoutProvisioning(
        string $tenantId
    ): array {
        $centralConnection = $this->centralConnectionName();

        $subscriptionId = null;

        try {
            DB::connection($centralConnection)->transaction(function () use (
                $tenantId,
                $centralConnection,
                &$subscriptionId
            ) {
                $subscriptionId = DB::connection($centralConnection)->table('subscriptions')->insertGetId([
                    'tenant_id' => $tenantId,
                    'plan_id' => null,
                    'status' => null,
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
            });
        } catch (\Throwable $e) {
            DB::connection($centralConnection)->transaction(function () use ($tenantId, $centralConnection) {
                DB::connection($centralConnection)->table('subscriptions')
                    ->where('tenant_id', $tenantId)
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
            'tenant_id' => $tenantId,
            'subscription' => $subscription,
        ];
    }

    protected function centralConnectionName(): string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }
}
