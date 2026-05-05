<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Customer;
use App\Models\Vehicle;

class MaintenanceProfileService
{
    public function customerProfile(Customer $customer): array
    {
        $customer->load([
            'vehicles' => fn ($query) => $query->latest('id'),
            'checkIns.branch',
            'workOrders.branch',
            'workOrders.vehicle',
            'estimates.vehicle',
            'invoices.vehicle',
            'warranties.vehicle',
            'complaints.vehicle',
        ]);

        $invoices = $customer->invoices;
        $workOrders = $customer->workOrders;

        return [
            'customer' => $customer,
            'metrics' => [
                'vehicles_count' => $customer->vehicles->count(),
                'visits_count' => $customer->checkIns->count(),
                'open_work_orders_count' => $workOrders->whereNotIn('status', ['delivered', 'closed', 'cancelled'])->count(),
                'total_spend' => (float) $invoices->where('payment_status', 'paid')->sum('grand_total'),
                'pending_payments' => (float) $invoices->whereIn('payment_status', ['unpaid', 'partially_paid'])->sum('grand_total'),
                'active_warranties_count' => $customer->warranties->where('status', 'active')->count(),
                'open_complaints_count' => $customer->complaints->where('status', 'open')->count(),
                'last_visit_at' => $customer->checkIns->sortByDesc('checked_in_at')->first()?->checked_in_at,
            ],
            'vehicles' => $customer->vehicles,
            'recent_visits' => $customer->checkIns->sortByDesc('checked_in_at')->take(10)->values(),
            'open_work_orders' => $workOrders->whereNotIn('status', ['delivered', 'closed', 'cancelled'])->sortByDesc('id')->values(),
            'recent_work_orders' => $workOrders->sortByDesc('id')->take(10)->values(),
            'recent_estimates' => $customer->estimates->sortByDesc('id')->take(8)->values(),
            'recent_invoices' => $invoices->sortByDesc('id')->take(8)->values(),
            'active_warranties' => $customer->warranties->where('status', 'active')->sortByDesc('id')->values(),
            'complaints' => $customer->complaints->sortByDesc('id')->take(8)->values(),
        ];
    }

    public function vehicleProfile(Vehicle $vehicle): array
    {
        $vehicle->load([
            'customer',
            'checkIns.branch',
            'workOrders.branch',
            'workOrders.customer',
            'inspections.branch',
            'diagnosisRecords.branch',
            'estimates',
            'invoices',
            'warranties',
            'complaints',
            'conditionMaps.items',
            'attachments.uploader',
            'healthScores',
            'serviceRecommendations',
            'preventiveReminders',
        ]);

        return [
            'vehicle' => $vehicle,
            'metrics' => [
                'visits_count' => $vehicle->checkIns->count(),
                'open_work_orders_count' => $vehicle->workOrders->whereNotIn('status', ['delivered', 'closed', 'cancelled'])->count(),
                'total_spend' => (float) $vehicle->invoices->where('payment_status', 'paid')->sum('grand_total'),
                'active_warranties_count' => $vehicle->warranties->where('status', 'active')->count(),
                'open_complaints_count' => $vehicle->complaints->where('status', 'open')->count(),
                'last_service_at' => $vehicle->workOrders->sortByDesc('closed_at')->first()?->closed_at,
                'latest_health_score' => $vehicle->healthScores->first()?->overall_score,
            ],
            'recent_visits' => $vehicle->checkIns->sortByDesc('checked_in_at')->take(10)->values(),
            'open_work_orders' => $vehicle->workOrders->whereNotIn('status', ['delivered', 'closed', 'cancelled'])->sortByDesc('id')->values(),
            'work_orders' => $vehicle->workOrders->sortByDesc('id')->take(12)->values(),
            'inspections' => $vehicle->inspections->sortByDesc('id')->take(10)->values(),
            'diagnosis_records' => $vehicle->diagnosisRecords->sortByDesc('id')->take(10)->values(),
            'estimates' => $vehicle->estimates->sortByDesc('id')->take(8)->values(),
            'invoices' => $vehicle->invoices->sortByDesc('id')->take(8)->values(),
            'warranties' => $vehicle->warranties->sortByDesc('id')->take(8)->values(),
            'complaints' => $vehicle->complaints->sortByDesc('id')->take(8)->values(),
            'condition_maps' => $vehicle->conditionMaps->sortByDesc('id')->take(5)->values(),
            'attachments' => $vehicle->attachments->take(12),
            'recommendations' => $vehicle->serviceRecommendations->take(8),
            'preventive_reminders' => $vehicle->preventiveReminders->take(8),
        ];
    }
}
