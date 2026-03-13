<?php

namespace App\Services\Billing\Gateways;

use App\Contracts\Billing\PaymentGatewayInterface;

class NullPaymentGateway implements PaymentGatewayInterface
{
    public function createRenewalSession(array $payload): array
    {
        return [
            'success' => false,
            'gateway' => 'null',
            'checkout_url' => null,
            'message' => 'No payment gateway is configured yet.',
            'payload' => $payload,
        ];
    }
}
