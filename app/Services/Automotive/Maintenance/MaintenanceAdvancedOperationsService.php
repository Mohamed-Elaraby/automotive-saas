<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceDelayAlert;
use App\Models\Maintenance\MaintenanceInspectionItem;
use App\Models\Maintenance\MaintenancePreventiveReminder;
use App\Models\Maintenance\MaintenancePreventiveRule;
use App\Models\Maintenance\MaintenanceServiceRecommendation;
use App\Models\Maintenance\MaintenanceSlaPolicy;
use App\Models\Maintenance\MaintenanceVehicleHealthScore;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MaintenanceAdvancedOperationsService
{
    public function seedDefaultSlaPolicies(): void
    {
        foreach ($this->defaultSlaPolicies() as $stage => $minutes) {
            MaintenanceSlaPolicy::query()->firstOrCreate(
                ['branch_id' => null, 'stage_code' => $stage],
                ['name' => str($stage)->replace('_', ' ')->title()->toString(), 'target_minutes' => $minutes, 'is_active' => true]
            );
        }
    }

    public function evaluateDelays(): Collection
    {
        $this->seedDefaultSlaPolicies();

        $policies = MaintenanceSlaPolicy::query()->where('is_active', true)->get()->keyBy('stage_code');
        $openOrders = WorkOrder::query()
            ->with(['branch', 'customer', 'vehicle'])
            ->whereNotIn('status', ['delivered', 'closed', 'cancelled'])
            ->get();

        foreach ($openOrders as $order) {
            $policy = $policies->get($order->status);
            if (! $policy || $policy->target_minutes <= 0) {
                continue;
            }

            $baseTime = $order->opened_at ?: $order->created_at;
            $elapsed = max(0, $baseTime?->diffInMinutes(now()) ?? 0);
            if ($elapsed <= $policy->target_minutes) {
                continue;
            }

            MaintenanceDelayAlert::query()->updateOrCreate(
                ['work_order_id' => $order->id, 'stage_code' => $order->status, 'status' => 'open'],
                [
                    'branch_id' => $order->branch_id,
                    'target_minutes' => $policy->target_minutes,
                    'elapsed_minutes' => $elapsed,
                    'message' => $order->work_order_number . ' delayed in ' . str_replace('_', ' ', $order->status),
                    'detected_at' => now(),
                ]
            );
        }

        return $this->delayAlerts();
    }

    public function delayAlerts(int $limit = 50): Collection
    {
        return MaintenanceDelayAlert::query()
            ->with(['branch', 'workOrder.customer', 'workOrder.vehicle'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function preventiveRules(): Collection
    {
        return MaintenancePreventiveRule::query()
            ->with(['branch', 'serviceCatalogItem'])
            ->latest('id')
            ->get();
    }

    public function createPreventiveRule(array $data): MaintenancePreventiveRule
    {
        return MaintenancePreventiveRule::query()->create([
            'branch_id' => $data['branch_id'] ?? null,
            'service_catalog_item_id' => $data['service_catalog_item_id'] ?? null,
            'name' => $data['name'],
            'vehicle_make' => $data['vehicle_make'] ?? null,
            'vehicle_model' => $data['vehicle_model'] ?? null,
            'mileage_interval' => $data['mileage_interval'] ?? null,
            'months_interval' => $data['months_interval'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'description' => $data['description'] ?? null,
        ]);
    }

    public function generatePreventiveReminders(): Collection
    {
        $rules = MaintenancePreventiveRule::query()->where('is_active', true)->get();
        $vehicles = Vehicle::query()->with('customer')->get();

        foreach ($rules as $rule) {
            foreach ($vehicles as $vehicle) {
                if ($rule->vehicle_make && strcasecmp($rule->vehicle_make, (string) $vehicle->make) !== 0) {
                    continue;
                }

                if ($rule->vehicle_model && strcasecmp($rule->vehicle_model, (string) $vehicle->model) !== 0) {
                    continue;
                }

                $dueMileage = $rule->mileage_interval ? ((int) $vehicle->odometer + (int) $rule->mileage_interval) : null;
                $dueDate = $rule->months_interval ? now()->addMonths((int) $rule->months_interval)->toDateString() : null;

                MaintenancePreventiveReminder::query()->updateOrCreate(
                    ['rule_id' => $rule->id, 'vehicle_id' => $vehicle->id, 'status' => 'upcoming'],
                    [
                        'branch_id' => $rule->branch_id,
                        'customer_id' => $vehicle->customer_id,
                        'service_catalog_item_id' => $rule->service_catalog_item_id,
                        'due_date' => $dueDate,
                        'due_mileage' => $dueMileage,
                        'notes' => $rule->description,
                    ]
                );
            }
        }

        return $this->preventiveReminders();
    }

    public function preventiveReminders(int $limit = 50): Collection
    {
        return MaintenancePreventiveReminder::query()
            ->with(['vehicle.customer', 'serviceCatalogItem', 'rule'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function calculateVehicleHealthScores(): Collection
    {
        $vehicles = Vehicle::query()->with(['customer', 'workOrders', 'checkIns'])->get();

        foreach ($vehicles as $vehicle) {
            $urgentItems = MaintenanceInspectionItem::query()
                ->whereHas('inspection', fn ($query) => $query->where('vehicle_id', $vehicle->id))
                ->where('result', 'urgent')
                ->count();

            $attentionItems = MaintenanceInspectionItem::query()
                ->whereHas('inspection', fn ($query) => $query->where('vehicle_id', $vehicle->id))
                ->where('result', 'needs_attention')
                ->count();

            $openOrders = $vehicle->workOrders->whereNotIn('status', ['delivered', 'closed', 'cancelled'])->count();
            $mileagePenalty = min(20, (int) floor(((int) $vehicle->odometer) / 50000) * 4);
            $score = max(0, 100 - ($urgentItems * 12) - ($attentionItems * 5) - ($openOrders * 4) - $mileagePenalty);

            MaintenanceVehicleHealthScore::query()->create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $vehicle->customer_id,
                'overall_score' => $score,
                'engine_score' => max(0, $score - ($urgentItems > 0 ? 5 : 0)),
                'brakes_score' => max(0, $score - ($attentionItems > 0 ? 8 : 0)),
                'suspension_score' => $score,
                'ac_score' => min(100, $score + 5),
                'electrical_score' => $score,
                'tires_score' => max(0, $score - $mileagePenalty),
                'signals' => [
                    'urgent_items' => $urgentItems,
                    'needs_attention_items' => $attentionItems,
                    'open_work_orders' => $openOrders,
                    'mileage_penalty' => $mileagePenalty,
                ],
                'calculated_at' => now(),
            ]);
        }

        return $this->healthScores();
    }

    public function healthScores(int $limit = 50): Collection
    {
        return MaintenanceVehicleHealthScore::query()
            ->with(['vehicle.customer'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function generateServiceRecommendations(): Collection
    {
        foreach ($this->healthScores(500) as $score) {
            if ($score->overall_score >= 75) {
                continue;
            }

            MaintenanceServiceRecommendation::query()->updateOrCreate(
                ['vehicle_id' => $score->vehicle_id, 'title' => 'Vehicle health review', 'status' => 'open'],
                [
                    'customer_id' => $score->customer_id,
                    'source' => 'health_score',
                    'priority' => $score->overall_score < 50 ? 'urgent' : 'high',
                    'description' => 'Health score is ' . $score->overall_score . '/100. Review urgent and needs-attention inspection items.',
                    'due_date' => now()->addDays(7)->toDateString(),
                    'signals' => $score->signals,
                ]
            );
        }

        foreach ($this->preventiveReminders(500) as $reminder) {
            MaintenanceServiceRecommendation::query()->updateOrCreate(
                ['vehicle_id' => $reminder->vehicle_id, 'service_catalog_item_id' => $reminder->service_catalog_item_id, 'status' => 'open'],
                [
                    'branch_id' => $reminder->branch_id,
                    'customer_id' => $reminder->customer_id,
                    'source' => 'preventive_maintenance',
                    'priority' => 'normal',
                    'title' => $reminder->serviceCatalogItem?->name ?: $reminder->rule?->name ?: 'Preventive maintenance due',
                    'description' => $reminder->notes,
                    'due_date' => $reminder->due_date,
                    'due_mileage' => $reminder->due_mileage,
                    'signals' => ['reminder_id' => $reminder->id],
                ]
            );
        }

        return $this->recommendations();
    }

    public function recommendations(int $limit = 50): Collection
    {
        return MaintenanceServiceRecommendation::query()
            ->with(['vehicle.customer', 'serviceCatalogItem'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    protected function defaultSlaPolicies(): array
    {
        return [
            'waiting_inspection' => 30,
            'under_inspection' => 60,
            'waiting_customer_approval' => 240,
            'in_progress' => 480,
            'waiting_parts' => 1440,
            'ready_for_qc' => 30,
            'ready_for_delivery' => 120,
        ];
    }
}
