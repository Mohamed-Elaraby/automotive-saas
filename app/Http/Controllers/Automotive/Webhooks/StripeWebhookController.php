<?php

namespace App\Http\Controllers\Automotive\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeWebhookSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected StripeWebhookSyncService $stripeWebhookSyncService
    ) {
    }

public function handle(Request $request): Response
{
    $payload = $request->getContent();
    $signature = (string) $request->header('Stripe-Signature');
    $secret = (string) config('billing.gateways.stripe.webhook_secret');

    try {
        $event = Webhook::constructEvent($payload, $signature, $secret);
    } catch (UnexpectedValueException|SignatureVerificationException $e) {
        return response('Invalid webhook payload or signature.', 400);
    }

    $this->stripeWebhookSyncService->handleEvent($event);

    return response('Webhook handled.', 200);
}
}
