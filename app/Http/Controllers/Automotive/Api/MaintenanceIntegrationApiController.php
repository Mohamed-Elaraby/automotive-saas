<?php

namespace App\Http\Controllers\Automotive\Api;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceInvoice;
use App\Models\WorkOrder;
use App\Services\Automotive\Maintenance\MaintenanceApiIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceIntegrationApiController extends Controller
{
    public function __construct(protected MaintenanceApiIntegrationService $service)
    {
    }

    public function workOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $token = $this->service->authorizeApiRequest($request, 'work_orders.read');
        $payload = $this->service->workOrderPayload($workOrder);
        $this->service->logApiRequest($request, $token, 200, ['work_order_number' => $workOrder->work_order_number]);

        return response()->json(['data' => $payload]);
    }

    public function invoice(Request $request, MaintenanceInvoice $invoice): JsonResponse
    {
        $token = $this->service->authorizeApiRequest($request, 'invoices.read');
        $payload = $this->service->invoicePayload($invoice);
        $this->service->logApiRequest($request, $token, 200, ['invoice_number' => $invoice->invoice_number]);

        return response()->json(['data' => $payload]);
    }
}
