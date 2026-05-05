<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Maintenance\VehicleCheckIn;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VehicleCheckInService
{
    public function __construct(
        protected MaintenanceNumberService $numberService,
        protected MaintenanceTimelineService $timelineService,
        protected VehicleConditionMapService $conditionMapService
    ) {
    }

    public function dashboardData(): array
    {
        return [
            'active_branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::query()->orderBy('name')->limit(100)->get(),
            'vehicles' => Vehicle::query()->with('customer')->latest('id')->limit(100)->get(),
            'recent_check_ins' => $this->recentCheckIns(12),
            'open_check_ins_count' => VehicleCheckIn::query()->whereIn('status', ['checked_in', 'in_progress'])->count(),
            'today_check_ins_count' => VehicleCheckIn::query()->whereDate('checked_in_at', now()->toDateString())->count(),
        ];
    }

    public function recentCheckIns(int $limit = 25): Collection
    {
        return VehicleCheckIn::query()
            ->with(['branch', 'customer', 'vehicle', 'serviceAdvisor', 'workOrder'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function create(array $data): VehicleCheckIn
    {
        return DB::transaction(function () use ($data): VehicleCheckIn {
            $customer = $this->resolveCustomer($data);
            $vehicle = $this->resolveVehicle($customer, $data);

            $checkIn = VehicleCheckIn::query()->create([
                'check_in_number' => $this->numberService->next('vehicle_check_ins', 'check_in_number', 'CHK'),
                'appointment_id' => $data['appointment_id'] ?? null,
                'branch_id' => $data['branch_id'],
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'service_advisor_id' => $data['service_advisor_id'] ?? $data['created_by'] ?? null,
                'status' => 'checked_in',
                'odometer' => $data['odometer'] ?? null,
                'fuel_level' => $data['fuel_level'] ?? null,
                'warning_lights' => $this->csvToArray($data['warning_lights'] ?? null),
                'personal_belongings' => $this->csvToArray($data['personal_belongings'] ?? null),
                'customer_complaint' => $data['customer_complaint'] ?? null,
                'existing_damage_notes' => $data['existing_damage_notes'] ?? null,
                'customer_visible_notes' => $data['customer_visible_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'expected_delivery_at' => $data['expected_delivery_at'] ?? null,
                'vin_number' => $data['vin_number'] ?? $vehicle->vin,
                'created_by' => $data['created_by'] ?? null,
                'checked_in_at' => now(),
            ]);

            if (! empty($data['vin_confirmed']) && ! empty($checkIn->vin_number)) {
                $this->verifyVin($checkIn, [
                    'vin_number' => $checkIn->vin_number,
                    'vin_verification_method' => 'manual',
                    'vin_confidence_score' => null,
                    'verified_by' => $data['created_by'] ?? null,
                ]);
            }

            if (! empty($data['create_work_order'])) {
                $workOrder = WorkOrder::query()->create([
                    'branch_id' => $checkIn->branch_id,
                    'customer_id' => $checkIn->customer_id,
                    'vehicle_id' => $checkIn->vehicle_id,
                    'service_advisor_id' => $checkIn->service_advisor_id,
                    'work_order_number' => $this->numberService->next('work_orders', 'work_order_number', 'WO'),
                    'title' => $data['work_order_title'] ?? 'Vehicle check-in',
                    'status' => 'open',
                    'priority' => $data['priority'] ?? 'normal',
                    'vehicle_status' => 'in_workshop',
                    'payment_status' => 'unpaid',
                    'opened_at' => now(),
                    'expected_delivery_at' => $checkIn->expected_delivery_at,
                    'notes' => $checkIn->customer_complaint,
                    'customer_visible_notes' => $checkIn->customer_visible_notes,
                    'internal_notes' => $checkIn->internal_notes,
                    'created_by' => $data['created_by'] ?? null,
                ]);

                $checkIn->forceFill(['work_order_id' => $workOrder->id])->save();
            }

            $this->timelineService->recordForCheckIn($checkIn, 'vehicle.checked_in', 'Vehicle checked in', [
                'customer_visible_note' => $checkIn->customer_visible_notes,
                'internal_note' => $checkIn->internal_notes,
                'created_by' => $data['created_by'] ?? null,
            ]);

            if (! empty($data['condition_items']) && is_array($data['condition_items'])) {
                $this->conditionMapService->syncCheckInItems($checkIn, $data['condition_items'], (int) ($data['created_by'] ?? 0));
            }

            return $checkIn->load(['branch', 'customer', 'vehicle', 'workOrder']);
        });
    }

    public function verifyVin(VehicleCheckIn $checkIn, array $data): VehicleCheckIn
    {
        $vin = strtoupper(trim((string) $data['vin_number']));
        $verifiedBy = $data['verified_by'] ?? null;

        $checkIn->forceFill([
            'vin_number' => $vin,
            'vin_verified_at' => now(),
            'vin_verified_by' => $verifiedBy,
            'vin_verification_method' => $data['vin_verification_method'] ?? 'manual',
            'vin_confidence_score' => $data['vin_confidence_score'] ?? null,
            'vin_source_image_id' => $data['vin_source_image_id'] ?? $checkIn->vin_source_image_id,
        ])->save();

        $checkIn->vehicle->forceFill([
            'vin' => $vin,
            'vin_verified_at' => $checkIn->vin_verified_at,
            'vin_verified_by' => $verifiedBy,
            'vin_verification_method' => $checkIn->vin_verification_method,
            'vin_confidence_score' => $checkIn->vin_confidence_score,
            'vin_source_image_id' => $checkIn->vin_source_image_id,
            'odometer' => $checkIn->odometer ?: $checkIn->vehicle->odometer,
        ])->save();

        $this->timelineService->recordForCheckIn($checkIn, 'vehicle.vin_confirmed', 'VIN confirmed manually', [
            'payload' => ['vin' => $vin, 'method' => $checkIn->vin_verification_method],
            'created_by' => $verifiedBy,
        ]);

        return $checkIn->refresh();
    }

    public function saveSignatures(VehicleCheckIn $checkIn, array $data): VehicleCheckIn
    {
        $checkIn->forceFill([
            'customer_signature' => $data['customer_signature'] ?? $checkIn->customer_signature,
            'service_advisor_signature' => $data['service_advisor_signature'] ?? $checkIn->service_advisor_signature,
        ])->save();

        $this->timelineService->recordForCheckIn($checkIn, 'vehicle.check_in_signed', 'Check-in signatures captured', [
            'payload' => [
                'customer_signature' => ! empty($checkIn->customer_signature),
                'service_advisor_signature' => ! empty($checkIn->service_advisor_signature),
            ],
            'created_by' => $data['signed_by'] ?? null,
        ]);

        return $checkIn->refresh();
    }

    protected function resolveCustomer(array $data): Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::query()->findOrFail($data['customer_id']);
        }

        $customer = Customer::query()->create([
            'customer_number' => $this->numberService->next('customers', 'customer_number', 'CUS'),
            'name' => $data['customer_name'],
            'phone' => $data['customer_phone'] ?? null,
            'email' => $data['customer_email'] ?? null,
            'customer_type' => $data['customer_type'] ?? 'individual',
            'company_name' => $data['company_name'] ?? null,
            'internal_notes' => $data['customer_internal_notes'] ?? null,
        ]);

        return $customer;
    }

    protected function resolveVehicle(Customer $customer, array $data): Vehicle
    {
        if (! empty($data['vehicle_id'])) {
            return Vehicle::query()->findOrFail($data['vehicle_id']);
        }

        return Vehicle::query()->create([
            'vehicle_number' => $this->numberService->next('vehicles', 'vehicle_number', 'VEH'),
            'customer_id' => $customer->id,
            'make' => $data['make'],
            'model' => $data['model'],
            'year' => $data['year'] ?? null,
            'trim' => $data['trim'] ?? null,
            'color' => $data['color'] ?? null,
            'plate_number' => $data['plate_number'] ?? null,
            'plate_source' => $data['plate_source'] ?? null,
            'plate_country' => $data['plate_country'] ?? null,
            'vin' => $data['vin_number'] ?? null,
            'odometer' => $data['odometer'] ?? null,
            'fuel_type' => $data['fuel_type'] ?? null,
            'transmission' => $data['transmission'] ?? null,
            'notes' => $data['vehicle_notes'] ?? null,
        ]);
    }

    protected function csvToArray(?string $value): ?array
    {
        if (! filled($value)) {
            return null;
        }

        return collect(explode(',', $value))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
