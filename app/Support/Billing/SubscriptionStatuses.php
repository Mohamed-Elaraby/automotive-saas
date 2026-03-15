<?php

namespace App\Support\Billing;

class SubscriptionStatuses
{
    public const TRIALING = 'trialing';
    public const ACTIVE = 'active';
    public const PAST_DUE = 'past_due';
    public const GRACE_PERIOD = 'grace_period';
    public const SUSPENDED = 'suspended';
    public const CANCELLED = 'canceled';
    public const EXPIRED = 'expired';

    public static function all(): array
    {
        return [
            self::TRIALING,
            self::ACTIVE,
            self::PAST_DUE,
            self::GRACE_PERIOD,
            self::SUSPENDED,
            self::CANCELLED,
            self::EXPIRED,
        ];
    }

    public static function accessAllowedStatuses(): array
    {
        return [
            self::TRIALING,
            self::ACTIVE,
        ];
    }
}
