<?php

namespace App\Contracts\Billing;

interface PaymentGatewayInterface
{
    public function createRenewalSession(array $payload): array;
}
