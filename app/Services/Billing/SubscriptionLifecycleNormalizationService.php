<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Support\Facades\DB;

class SubscriptionLifecycleNormalizationService
{
    public function normalizeOne(int|string $subscriptionId, bool $apply = false): array
    {
        $subscription = $this->findSubscription($subscriptionId);

        if (! $subscription) {
            return [
                'ok' => false,
                'message' => 'Subscription not found.',
            ];
        }

$changes = $this->buildNormalizedChanges($subscription);

if (empty($changes)) {
    return [
        'ok' => true,
        'applied' => false,
        'subscription_id' => $subscription->id,
        'status' => (string) ($subscription->status ?? ''),
        'changes' => [],
        'message' => 'No lifecycle normalization changes were needed.',
    ];
}

if ($apply) {
    DB::connection($this->centralConnection())
        ->table('subscriptions')
        ->where('id', $subscription->id)
        ->update(array_merge(
            $changes,
            ['updated_at' => now()]
        ));
}

return [
    'ok' => true,
    'applied' => $apply,
    'subscription_id' => $subscription->id,
    'status' => (string) ($subscription->status ?? ''),
    'changes' => $changes,
    'message' => $apply
        ? 'Lifecycle fields were normalized successfully.'
        : 'Lifecycle normalization preview generated successfully.',
];
}

public function normalizeAll(bool $apply = false): array
{
    $rows = DB::connection($this->centralConnection())
        ->table('subscriptions')
        ->orderBy('id')
        ->get();

    $processed = 0;
    $changed = 0;
    $results = [];

    foreach ($rows as $row) {
        $processed++;

        $result = $this->normalizeOne($row->id, $apply);

        if (! empty($result['changes'])) {
            $changed++;
            $results[] = $result;
        }
    }

    return [
        'ok' => true,
        'applied' => $apply,
        'processed' => $processed,
        'changed' => $changed,
        'results' => $results,
        'message' => $apply
            ? "Lifecycle normalization applied to {$changed} subscription(s)."
            : "Lifecycle normalization preview found {$changed} subscription(s) needing updates.",
    ];
}

protected function buildNormalizedChanges(object $subscription): array
{
    $status = (string) ($subscription->status ?? '');
    $changes = [];

    switch ($status) {
        case SubscriptionStatuses::ACTIVE:
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'trial_ends_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'grace_ends_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'last_payment_failed_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'past_due_started_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'suspended_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'cancelled_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'ends_at', null));
            $changes = array_merge($changes, $this->setIfDifferent($subscription, 'payment_failures_count', 0));
            break;

        case SubscriptionStatuses::TRIALING:
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'grace_ends_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'last_payment_failed_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'past_due_started_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'suspended_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'cancelled_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'ends_at', null));
            $changes = array_merge($changes, $this->setIfDifferent($subscription, 'payment_failures_count', 0));
            break;

        case SubscriptionStatuses::PAST_DUE:
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'trial_ends_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'suspended_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'cancelled_at', null));
            break;

        case SubscriptionStatuses::SUSPENDED:
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'trial_ends_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'cancelled_at', null));
            break;

        case SubscriptionStatuses::CANCELLED:
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'trial_ends_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'grace_ends_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'last_payment_failed_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'past_due_started_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'suspended_at', null));
            $changes = array_merge($changes, $this->setIfDifferent($subscription, 'payment_failures_count', 0));
            break;

        case SubscriptionStatuses::EXPIRED:
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'trial_ends_at', null));
            $changes = array_merge($changes, $this->setIfNotNull($subscription, 'grace_ends_at', null));
            break;
    }

    return $changes;
}

protected function setIfNotNull(object $subscription, string $field, mixed $value): array
{
    return $subscription->{$field} !== null ? [$field => $value] : [];
}

protected function setIfDifferent(object $subscription, string $field, mixed $value): array
{
    return $subscription->{$field} != $value ? [$field => $value] : [];
}

protected function findSubscription(int|string $subscriptionId): ?object
    {
        return DB::connection($this->centralConnection())
            ->table('subscriptions')
            ->where('id', $subscriptionId)
            ->first();
    }

    protected function centralConnection(): string
{
    return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
}
}
