<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceApiRequestLog;
use App\Models\Maintenance\MaintenanceApiToken;
use App\Models\Maintenance\MaintenanceInvoice;
use App\Models\Maintenance\MaintenancePaymentRequest;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaintenanceApiIntegrationService
{
    public function __construct(
        protected MaintenanceNumberService $numbers,
        protected MaintenanceIntegrationService $integrations,
        protected MaintenanceTimelineService $timeline,
        protected MaintenanceNotificationService $notifications,
        protected MaintenanceAuditService $audit
    ) {
    }

    public function recentTokens(int $limit = 30): Collection
    {
        return MaintenanceApiToken::query()
            ->with('creator')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function recentPaymentRequests(int $limit = 50): Collection
    {
        return MaintenancePaymentRequest::query()
            ->with(['invoice', 'customer', 'vehicle', 'workOrder'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function createToken(array $data): array
    {
        $plainToken = 'mnt_' . Str::random(48);

        $record = MaintenanceApiToken::query()->create([
            'token_name' => $data['token_name'],
            'token_hash' => hash('sha256', $plainToken),
            'status' => 'active',
            'scopes' => $this->normalizeScopes($data['scopes'] ?? []),
            'created_by' => $data['created_by'] ?? null,
        ]);

        $this->audit->record('api_token.created', 'integrations', [
            'user_id' => $data['created_by'] ?? null,
            'auditable' => $record,
            'new_values' => [
                'token_name' => $record->token_name,
                'scopes' => $record->scopes,
                'status' => $record->status,
            ],
        ]);

        $this->notifications->create('api.token.created', 'Maintenance API token created', [
            'message' => $record->token_name,
            'notifiable_type' => MaintenanceApiToken::class,
            'notifiable_id' => $record->id,
            'payload' => ['scopes' => $record->scopes],
        ]);

        return ['token' => $plainToken, 'record' => $record];
    }

    public function revokeToken(MaintenanceApiToken $token, ?int $userId): MaintenanceApiToken
    {
        $token->forceFill([
            'status' => 'revoked',
            'revoked_at' => now(),
        ])->save();

        $this->audit->record('api_token.revoked', 'integrations', [
            'user_id' => $userId,
            'auditable' => $token,
            'new_values' => [
                'token_name' => $token->token_name,
                'status' => $token->status,
                'revoked_at' => optional($token->revoked_at)->toISOString(),
            ],
        ]);

        return $token->fresh();
    }

    public function createPaymentRequest(MaintenanceInvoice $invoice, array $data): MaintenancePaymentRequest
    {
        return DB::transaction(function () use ($invoice, $data): MaintenancePaymentRequest {
            $invoice->loadMissing(['customer', 'vehicle', 'workOrder']);

            if ($invoice->payment_status === 'paid' || $invoice->payment_status === 'cancelled') {
                throw ValidationException::withMessages([
                    'invoice_id' => __('maintenance.messages.payment_request_blocked'),
                ]);
            }

            $balance = max(0, round((float) $invoice->grand_total - (float) $invoice->paid_amount, 2));
            $amount = round((float) ($data['amount'] ?? $balance), 2);

            if ($amount <= 0 || $amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => __('maintenance.messages.payment_request_amount_invalid'),
                ]);
            }

            $token = Str::random(56);
            $request = MaintenancePaymentRequest::query()->create([
                'request_number' => $this->numbers->next('maintenance_payment_requests', 'request_number', 'PAY'),
                'branch_id' => $invoice->branch_id,
                'invoice_id' => $invoice->id,
                'work_order_id' => $invoice->work_order_id,
                'customer_id' => $invoice->customer_id,
                'vehicle_id' => $invoice->vehicle_id,
                'status' => 'pending',
                'amount' => $amount,
                'currency' => $data['currency'] ?? 'USD',
                'provider' => $data['provider'] ?? 'external',
                'payment_token' => $token,
                'expires_at' => $data['expires_at'] ?? now()->addDays(7),
                'payload' => [
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name' => $invoice->customer?->name,
                    'source' => 'maintenance',
                ],
                'created_by' => $data['created_by'] ?? null,
            ]);

            $request->forceFill([
                'payment_url' => route('automotive.customer.maintenance.payment-request', $token),
            ])->save();

            if ($invoice->workOrder) {
                $this->timeline->recordForWorkOrder($invoice->workOrder, 'payment.requested', $request->request_number, [
                    'created_by' => $data['created_by'] ?? null,
                    'customer_visible_note' => __('maintenance.messages.payment_request_created'),
                    'payload' => [
                        'payment_request_id' => $request->id,
                        'amount' => $request->amount,
                        'currency' => $request->currency,
                    ],
                ]);
            }

            $this->notifications->create('payment.requested', 'Payment request created', [
                'branch_id' => $request->branch_id,
                'message' => $request->request_number . ' - ' . number_format((float) $request->amount, 2),
                'notifiable_type' => MaintenancePaymentRequest::class,
                'notifiable_id' => $request->id,
                'payload' => [
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $request->status,
                ],
            ]);

            $this->audit->record('payment_request.created', 'integrations', [
                'branch_id' => $request->branch_id,
                'user_id' => $data['created_by'] ?? null,
                'auditable' => $request,
                'new_values' => [
                    'request_number' => $request->request_number,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'provider' => $request->provider,
                ],
            ]);

            return $request->fresh(['invoice', 'customer', 'vehicle', 'workOrder']);
        });
    }

    public function markPaymentRequestPaid(MaintenancePaymentRequest $request, array $data): MaintenancePaymentRequest
    {
        return DB::transaction(function () use ($request, $data): MaintenancePaymentRequest {
            $request->loadMissing('invoice');

            if ($request->status === 'paid') {
                return $request->fresh(['invoice', 'customer', 'vehicle', 'workOrder']);
            }

            $receipt = $this->integrations->recordReceipt($request->invoice, [
                'amount' => $request->amount,
                'payment_method' => 'online',
                'currency' => $request->currency,
                'reference_number' => $data['reference_number'] ?? $request->request_number,
                'received_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? 'Payment request marked paid.',
                'created_by' => $data['created_by'] ?? null,
            ]);

            $request->forceFill([
                'status' => 'paid',
                'paid_at' => $receipt->received_at ?: now(),
                'payload' => array_merge($request->payload ?: [], [
                    'receipt_id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                ]),
            ])->save();

            return $request->fresh(['invoice', 'customer', 'vehicle', 'workOrder']);
        });
    }

    public function publicPaymentPayload(string $token): MaintenancePaymentRequest
    {
        return MaintenancePaymentRequest::query()
            ->with(['invoice', 'customer', 'vehicle', 'workOrder'])
            ->where('payment_token', $token)
            ->firstOrFail();
    }

    public function authorizeApiRequest(Request $request, string $scope): MaintenanceApiToken
    {
        $plainToken = $request->bearerToken() ?: (string) $request->header('X-Maintenance-Token');
        $token = $plainToken !== ''
            ? MaintenanceApiToken::query()->where('token_hash', hash('sha256', $plainToken))->first()
            : null;

        if (! $token || $token->status !== 'active' || ! $token->allows($scope)) {
            $this->logApiRequest($request, $token, 403, ['error' => 'forbidden']);
            throw new HttpResponseException(response()->json(['message' => 'Forbidden.'], 403));
        }

        $token->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ])->save();

        return $token;
    }

    public function logApiRequest(Request $request, ?MaintenanceApiToken $token, int $statusCode, array $responseSummary = []): void
    {
        MaintenanceApiRequestLog::query()->create([
            'token_id' => $token?->id,
            'route_name' => $request->route()?->getName(),
            'method' => $request->method(),
            'path' => $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'idempotency_key' => $request->header('Idempotency-Key'),
            'status_code' => $statusCode,
            'request_summary' => [
                'query' => $request->query(),
            ],
            'response_summary' => $responseSummary,
            'created_at' => now(),
        ]);
    }

    public function workOrderPayload(WorkOrder $workOrder): array
    {
        $workOrder->loadMissing(['branch', 'customer', 'vehicle', 'maintenanceJobs', 'lines']);

        return [
            'work_order_number' => $workOrder->work_order_number,
            'status' => $workOrder->status,
            'vehicle_status' => $workOrder->vehicle_status,
            'payment_status' => $workOrder->payment_status,
            'branch' => $workOrder->branch?->name,
            'customer' => [
                'name' => $workOrder->customer?->name,
                'phone' => $workOrder->customer?->phone,
            ],
            'vehicle' => [
                'plate_number' => $workOrder->vehicle?->plate_number,
                'vin_number' => $workOrder->vehicle?->vin_number,
                'make' => $workOrder->vehicle?->make,
                'model' => $workOrder->vehicle?->model,
                'year' => $workOrder->vehicle?->year,
            ],
            'jobs' => $workOrder->maintenanceJobs->map(fn ($job): array => [
                'job_number' => $job->job_number,
                'title' => $job->title,
                'status' => $job->status,
                'assigned_technician_id' => $job->assigned_technician_id,
            ])->values(),
            'updated_at' => optional($workOrder->updated_at)->toISOString(),
        ];
    }

    public function invoicePayload(MaintenanceInvoice $invoice): array
    {
        $invoice->loadMissing(['branch', 'customer', 'vehicle', 'workOrder', 'receipts']);

        return [
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'payment_status' => $invoice->payment_status,
            'branch' => $invoice->branch?->name,
            'customer_name' => $invoice->customer?->name,
            'work_order_number' => $invoice->workOrder?->work_order_number,
            'vehicle_plate' => $invoice->vehicle?->plate_number,
            'subtotal' => (float) $invoice->subtotal,
            'discount_total' => (float) $invoice->discount_total,
            'tax_total' => (float) $invoice->tax_total,
            'grand_total' => (float) $invoice->grand_total,
            'paid_amount' => (float) $invoice->paid_amount,
            'balance_due' => max(0, round((float) $invoice->grand_total - (float) $invoice->paid_amount, 2)),
            'receipts' => $invoice->receipts->map(fn ($receipt): array => [
                'receipt_number' => $receipt->receipt_number,
                'amount' => (float) $receipt->amount,
                'currency' => $receipt->currency,
                'payment_method' => $receipt->payment_method,
                'received_at' => optional($receipt->received_at)->toISOString(),
            ])->values(),
            'updated_at' => optional($invoice->updated_at)->toISOString(),
        ];
    }

    protected function normalizeScopes(array|string $scopes): array
    {
        if (is_string($scopes)) {
            $scopes = array_filter(array_map('trim', explode(',', $scopes)));
        }

        $allowed = ['*', 'work_orders.read', 'invoices.read', 'payments.write'];

        return array_values(array_intersect($allowed, $scopes));
    }
}
