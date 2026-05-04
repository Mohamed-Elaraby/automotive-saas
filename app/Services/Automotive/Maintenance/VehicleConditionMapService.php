<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\VehicleCheckIn;
use App\Models\Maintenance\VehicleConditionMap;

class VehicleConditionMapService
{
    public function syncCheckInItems(VehicleCheckIn $checkIn, array $items, int $userId): VehicleConditionMap
    {
        $map = VehicleConditionMap::query()->firstOrCreate(
            [
                'check_in_id' => $checkIn->id,
                'type' => 'check_in',
            ],
            [
                'tenant_id' => (string) tenant('id'),
                'branch_id' => $checkIn->branch_id,
                'vehicle_id' => $checkIn->vehicle_id,
                'work_order_id' => $checkIn->work_order_id,
                'status' => 'open',
                'created_by' => $userId,
            ]
        );

        foreach ($items as $item) {
            if (empty($item['vehicle_area_code']) || empty($item['description'])) {
                continue;
            }

            $map->items()->create([
                'vehicle_area_code' => $item['vehicle_area_code'],
                'label' => $item['label'] ?? str_replace('_', ' ', $item['vehicle_area_code']),
                'note_type' => $item['note_type'] ?? 'existing_damage',
                'severity' => $item['severity'] ?? 'low',
                'description' => $item['description'],
                'customer_visible_note' => $item['customer_visible_note'] ?? null,
                'internal_note' => $item['internal_note'] ?? null,
                'photo_id' => $item['photo_id'] ?? null,
            ]);
        }

        return $map->load('items');
    }
}
