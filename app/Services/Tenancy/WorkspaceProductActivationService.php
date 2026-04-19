<?php

namespace App\Services\Tenancy;

use App\Models\TenantProductSubscription;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WorkspaceProductActivationService
{
    public const ACTIVATION_PENDING = 'pending';
    public const ACTIVATION_ACTIVE = 'active';
    public const ACTIVATION_FAILED = 'failed';

    public const PROVISIONING_PENDING = 'pending';
    public const PROVISIONING_IN_PROGRESS = 'provisioning';
    public const PROVISIONING_ACTIVE = 'active';
    public const PROVISIONING_FAILED = 'failed';

    public function markProvisioning(TenantProductSubscription $subscription, string $source): TenantProductSubscription
    {
        if (! $this->hasActivationColumns()) {
            return $subscription;
        }

        $subscription->fill([
            'activation_status' => self::ACTIVATION_PENDING,
            'provisioning_status' => self::PROVISIONING_IN_PROGRESS,
            'provisioning_started_at' => now(),
            'provisioning_failed_at' => null,
            'activation_error' => null,
            'activation_source' => $source,
        ])->save();

        return $subscription->fresh();
    }

    public function markActive(TenantProductSubscription $subscription, string $source): TenantProductSubscription
    {
        if (! $this->hasActivationColumns()) {
            return $subscription;
        }

        $subscription->fill([
            'activation_status' => self::ACTIVATION_ACTIVE,
            'provisioning_status' => self::PROVISIONING_ACTIVE,
            'provisioning_completed_at' => now(),
            'provisioning_failed_at' => null,
            'activated_at' => now(),
            'activation_error' => null,
            'activation_source' => $source,
        ])->save();

        return $subscription->fresh();
    }

    public function markFailed(TenantProductSubscription $subscription, Throwable|string $error, string $source): TenantProductSubscription
    {
        if (! $this->hasActivationColumns()) {
            return $subscription;
        }

        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        $subscription->fill([
            'activation_status' => self::ACTIVATION_FAILED,
            'provisioning_status' => self::PROVISIONING_FAILED,
            'provisioning_failed_at' => now(),
            'activation_error' => mb_substr($message, 0, 2000),
            'activation_source' => $source,
        ])->save();

        return $subscription->fresh();
    }

    public function allowsRuntimeAccess(object|array $subscription): bool
    {
        $status = (string) data_get($subscription, 'status', '');

        if (! in_array($status, SubscriptionStatuses::accessAllowedStatuses(), true)) {
            return false;
        }

        if (! $this->hasActivationColumns()) {
            return true;
        }

        return (string) data_get($subscription, 'activation_status', '') === self::ACTIVATION_ACTIVE
            && (string) data_get($subscription, 'provisioning_status', '') === self::PROVISIONING_ACTIVE;
    }

    public function portalStatusFor(?object $subscription): array
    {
        if (! $subscription) {
            return [
                'state' => 'not_started',
                'label' => 'Not Started',
                'message' => 'Choose a product and subscription option to start workspace access.',
                'severity' => 'secondary',
            ];
        }

        $hasPendingCheckout = filled($subscription->gateway_checkout_session_id ?? null)
            && blank($subscription->gateway_subscription_id ?? null);

        if ($hasPendingCheckout) {
            return [
                'state' => 'payment_pending_webhook',
                'label' => 'Payment Pending Webhook',
                'message' => 'Stripe checkout has started. Workspace access will update after Stripe confirms the subscription.',
                'severity' => 'warning',
            ];
        }

        $provisioningStatus = (string) ($subscription->provisioning_status ?? '');
        $activationStatus = (string) ($subscription->activation_status ?? '');

        if ($this->hasActivationColumns()) {
            if ($provisioningStatus === self::PROVISIONING_FAILED || $activationStatus === self::ACTIVATION_FAILED) {
                return [
                    'state' => 'provisioning_failed',
                    'label' => 'Provisioning Failed',
                    'message' => 'Provisioning failed. Admin diagnostics are available on the product subscription record.',
                    'severity' => 'danger',
                    'error' => (string) ($subscription->activation_error ?? ''),
                ];
            }

            if ($provisioningStatus === self::PROVISIONING_IN_PROGRESS) {
                return [
                    'state' => 'provisioning_in_progress',
                    'label' => 'Provisioning In Progress',
                    'message' => 'Payment or approval is confirmed. Workspace activation is running now.',
                    'severity' => 'info',
                ];
            }

            if ($this->allowsRuntimeAccess($subscription)) {
                return [
                    'state' => 'active_ready',
                    'label' => 'Active And Ready',
                    'message' => 'This product is active and ready in your workspace.',
                    'severity' => 'success',
                ];
            }

            if (in_array((string) ($subscription->status ?? ''), SubscriptionStatuses::accessAllowedStatuses(), true)) {
                return [
                    'state' => 'provisioning_in_progress',
                    'label' => 'Provisioning In Progress',
                    'message' => 'Payment or approval is confirmed. Workspace activation is waiting for provisioning completion.',
                    'severity' => 'info',
                ];
            }
        }

        return [
            'state' => 'not_active',
            'label' => 'Not Active Yet',
            'message' => 'This product is not active in the workspace yet.',
            'severity' => 'secondary',
        ];
    }

    public function hasActivationColumns(): bool
    {
        $connection = config('tenancy.database.central_connection') ?? config('database.default');

        return Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'activation_status')
            && Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'provisioning_status');
    }
}
