<?php

namespace App\Support\Billing;

class BillingActionResolver
{
    public static function resolve(array $billingState): array
    {
        $status = $billingState['status'] ?? 'unknown';

        return match ($status) {
        SubscriptionStatuses::TRIALING => [
        'primary_label' => 'Upgrade Before Trial Ends',
        'primary_action' => 'renew',
        'secondary_label' => 'View Plan Details',
        'secondary_action' => 'details',
    ],

            SubscriptionStatuses::ACTIVE => [
        'primary_label' => 'Renew Subscription',
        'primary_action' => 'renew',
        'secondary_label' => 'Update Payment Method',
        'secondary_action' => 'payment_method',
    ],

            SubscriptionStatuses::PAST_DUE => [
        'primary_label' => 'Pay Outstanding Balance',
        'primary_action' => 'renew',
        'secondary_label' => 'Update Payment Method',
        'secondary_action' => 'payment_method',
    ],

            SubscriptionStatuses::GRACE_PERIOD => [
        'primary_label' => 'Renew Now',
        'primary_action' => 'renew',
        'secondary_label' => 'Update Payment Method',
        'secondary_action' => 'payment_method',
    ],

            SubscriptionStatuses::SUSPENDED => [
        'primary_label' => 'Reactivate Subscription',
        'primary_action' => 'renew',
        'secondary_label' => 'Contact Support',
        'secondary_action' => 'support',
    ],

            SubscriptionStatuses::CANCELLED => [
        'primary_label' => 'Resume Subscription',
        'primary_action' => 'renew',
        'secondary_label' => 'View Plan Details',
        'secondary_action' => 'details',
    ],

            SubscriptionStatuses::EXPIRED => [
        'primary_label' => 'Start Renewal',
        'primary_action' => 'renew',
        'secondary_label' => 'Contact Support',
        'secondary_action' => 'support',
    ],

            default => [
        'primary_label' => 'Manage Billing',
        'primary_action' => 'renew',
        'secondary_label' => 'Contact Support',
        'secondary_action' => 'support',
    ],
        };
    }
}
