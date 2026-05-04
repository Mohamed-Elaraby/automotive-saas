<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceTimelineEntry;
use App\Models\Maintenance\VehicleCheckIn;
use App\Models\WorkOrder;

class MaintenanceTimelineService
{
    public function recordForCheckIn(VehicleCheckIn $checkIn, string $eventType, string $title, array $data = []): MaintenanceTimelineEntry
    {
        return MaintenanceTimelineEntry::query()->create([
            'work_order_id' => $checkIn->work_order_id,
            'check_in_id' => $checkIn->id,
            'vehicle_id' => $checkIn->vehicle_id,
            'event_type' => $eventType,
            'title' => $title,
            'customer_visible_note' => $data['customer_visible_note'] ?? null,
            'internal_note' => $data['internal_note'] ?? null,
            'payload' => $data['payload'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    public function recordForWorkOrder(WorkOrder $workOrder, string $eventType, string $title, array $data = []): MaintenanceTimelineEntry
    {
        return MaintenanceTimelineEntry::query()->create([
            'work_order_id' => $workOrder->id,
            'vehicle_id' => $workOrder->vehicle_id,
            'event_type' => $eventType,
            'title' => $title,
            'customer_visible_note' => $data['customer_visible_note'] ?? null,
            'internal_note' => $data['internal_note'] ?? null,
            'payload' => $data['payload'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }
}
