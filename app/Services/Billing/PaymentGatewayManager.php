<?php

namespace App\Services\Billing;

use App\Contracts\Billing\PaymentGatewayInterface;
use App\Services\Billing\Gateways\NullPaymentGateway;
use App\Services\Billing\Gateways\StripePaymentGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function driver(?string $gateway = null): PaymentGatewayInterface
    {
        $gateway = $gateway ?: config('billing.default_gateway', 'null');

        return match ($gateway) {
        'null' => app(NullPaymentGateway::class),
            'stripe' => app(StripePaymentGateway::class),
            default => throw new InvalidArgumentException("Unsupported billing gateway [{$gateway}]"),
        };
    }
}
