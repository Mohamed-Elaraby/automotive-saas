<?php

namespace App\Support;

class SubscriptionStatus
{
    public const TRIALING = 'trialing';
    public const ACTIVE = 'active';
    public const EXPIRED = 'expired';
    public const SUSPENDED = 'suspended';
    public const CANCELLED = 'cancelled';

    public static function allowsAccess(string $status): bool
    {
        return in_array($status, [
            self::TRIALING,
            self::ACTIVE,
        ], true);
    }
}
