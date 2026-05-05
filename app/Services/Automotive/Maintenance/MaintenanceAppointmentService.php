<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Maintenance\MaintenanceAppointment;
use App\Models\Maintenance\VehicleCheckIn;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MaintenanceAppointmentService
{
    public function __construct(
        protected MaintenanceNumberService $numbers,
        protected VehicleCheckInService $checkIns,
        protected MaintenanceTimelineService $timeline
    ) {
    }

    public function dashboard(): array
    {
        return [
            'today_count' => MaintenanceAppointment::query()->whereDate('scheduled_at', today())->count(),
            'scheduled_count' => MaintenanceAppointment::query()->where('status', 'scheduled')->count(),
            'arrived_count' => MaintenanceAppointment::query()->where('status', 'arrived')->count(),
            'converted_count' => MaintenanceAppointment::query()->where('status', 'converted')->count(),
            'today' => $this->forDate(today()->toDateString()),
            'upcoming' => MaintenanceAppointment::query()
                ->with(['branch', 'customer', 'vehicle', 'serviceAdvisor', 'checkIn'])
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->whereDate('scheduled_at', '>=', today())
                ->orderBy('scheduled_at')
                ->limit(25)
                ->get(),
        ];
    }

    public function formContext(): array
    {
        return [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::query()->orderBy('name')->limit(200)->get(),
            'vehicles' => Vehicle::query()->with('customer')->latest('id')->limit(200)->get(),
            'users' => User::query()->orderBy('name')->get(),
        ];
    }

    public function recent(int $limit = 80): Collection
    {
        return MaintenanceAppointment::query()
            ->with(['branch', 'customer', 'vehicle', 'serviceAdvisor', 'checkIn'])
            ->latest('scheduled_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function forDate(string $date): Collection
    {
        return MaintenanceAppointment::query()
            ->with(['branch', 'customer', 'vehicle', 'serviceAdvisor', 'checkIn'])
            ->whereDate('scheduled_at', $date)
            ->orderBy('scheduled_at')
            ->get();
    }

    public function create(array $data): MaintenanceAppointment
    {
        return DB::transaction(function () use ($data): MaintenanceAppointment {
            $appointment = MaintenanceAppointment::query()->create([
                'appointment_number' => $this->numbers->next('maintenance_appointments', 'appointment_number', 'APP'),
                'branch_id' => $data['branch_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'service_advisor_id' => $data['service_advisor_id'] ?? null,
                'type' => $data['type'] ?? 'appointment',
                'status' => ($data['type'] ?? 'appointment') === 'walk_in' ? 'arrived' : 'scheduled',
                'source' => $data['source'] ?? 'in_branch',
                'scheduled_at' => $data['scheduled_at'] ?? now(),
                'expected_arrival_at' => $data['expected_arrival_at'] ?? $data['scheduled_at'] ?? now(),
                'arrived_at' => ($data['type'] ?? 'appointment') === 'walk_in' ? now() : null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'vehicle_make' => $data['vehicle_make'] ?? null,
                'vehicle_model' => $data['vehicle_model'] ?? null,
                'vehicle_year' => $data['vehicle_year'] ?? null,
                'plate_number' => $data['plate_number'] ?? null,
                'vin_number' => $data['vin_number'] ?? null,
                'service_type' => $data['service_type'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'customer_complaint' => $data['customer_complaint'] ?? null,
                'customer_visible_notes' => $data['customer_visible_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            return $appointment->load(['branch', 'customer', 'vehicle', 'serviceAdvisor']);
        });
    }

    public function markArrived(MaintenanceAppointment $appointment, ?int $userId = null): MaintenanceAppointment
    {
        $appointment->forceFill([
            'status' => 'arrived',
            'arrived_at' => now(),
        ])->save();

        return $appointment->refresh();
    }

    public function cancel(MaintenanceAppointment $appointment): MaintenanceAppointment
    {
        $appointment->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ])->save();

        return $appointment->refresh();
    }

    public function convertToCheckIn(MaintenanceAppointment $appointment, array $data): VehicleCheckIn
    {
        return DB::transaction(function () use ($appointment, $data): VehicleCheckIn {
            if ($appointment->check_in_id) {
                return VehicleCheckIn::query()->findOrFail($appointment->check_in_id);
            }

            $checkIn = $this->checkIns->create([
                'appointment_id' => $appointment->id,
                'branch_id' => $appointment->branch_id,
                'customer_id' => $appointment->customer_id,
                'customer_name' => $appointment->customer_name,
                'customer_phone' => $appointment->customer_phone,
                'customer_email' => $appointment->customer_email,
                'vehicle_id' => $appointment->vehicle_id,
                'make' => $appointment->vehicle_make,
                'model' => $appointment->vehicle_model,
                'year' => $appointment->vehicle_year,
                'plate_number' => $appointment->plate_number,
                'vin_number' => $appointment->vin_number,
                'odometer' => $data['odometer'] ?? null,
                'fuel_level' => $data['fuel_level'] ?? null,
                'customer_complaint' => $data['customer_complaint'] ?? $appointment->customer_complaint,
                'customer_visible_notes' => $data['customer_visible_notes'] ?? $appointment->customer_visible_notes,
                'internal_notes' => $data['internal_notes'] ?? $appointment->internal_notes,
                'expected_delivery_at' => $data['expected_delivery_at'] ?? null,
                'create_work_order' => $data['create_work_order'] ?? true,
                'work_order_title' => $data['work_order_title'] ?? $appointment->service_type ?? 'Appointment check-in',
                'priority' => $data['priority'] ?? $appointment->priority,
                'created_by' => $data['created_by'] ?? null,
                'service_advisor_id' => $appointment->service_advisor_id ?: ($data['created_by'] ?? null),
            ]);

            $appointment->forceFill([
                'status' => 'converted',
                'arrived_at' => $appointment->arrived_at ?: now(),
                'check_in_id' => $checkIn->id,
            ])->save();

            return $checkIn;
        });
    }
}
