<?php

namespace App\Http\Controllers\Automotive\Customer;

use App\Http\Controllers\Controller;
use App\Services\Automotive\Maintenance\MaintenanceApiIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MaintenancePaymentRequestController extends Controller
{
    public function __construct(protected MaintenanceApiIntegrationService $service)
    {
    }

    public function show(string $token): View
    {
        return view('automotive.customer.maintenance.payment-request', [
            'paymentRequest' => $this->service->publicPaymentPayload($token),
        ]);
    }

    public function json(string $token): JsonResponse
    {
        $paymentRequest = $this->service->publicPaymentPayload($token);

        return response()->json([
            'request_number' => $paymentRequest->request_number,
            'status' => $paymentRequest->status,
            'amount' => (float) $paymentRequest->amount,
            'currency' => $paymentRequest->currency,
            'invoice_number' => $paymentRequest->invoice?->invoice_number,
            'payment_status' => $paymentRequest->invoice?->payment_status,
            'expires_at' => optional($paymentRequest->expires_at)->toISOString(),
            'paid_at' => optional($paymentRequest->paid_at)->toISOString(),
        ]);
    }
}
