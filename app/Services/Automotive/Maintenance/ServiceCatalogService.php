<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceServiceCatalogItem;
use Illuminate\Support\Collection;

class ServiceCatalogService
{
    public function __construct(protected MaintenanceNumberService $numberService)
    {
    }

    public function list(int $limit = 100): Collection
    {
        return MaintenanceServiceCatalogItem::query()
            ->orderByDesc('is_active')
            ->orderBy('category')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function create(array $data): MaintenanceServiceCatalogItem
    {
        return MaintenanceServiceCatalogItem::query()->create([
            'service_number' => $this->numberService->next('maintenance_service_catalog_items', 'service_number', 'SVC'),
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'estimated_minutes' => $data['estimated_minutes'] ?? 0,
            'default_labor_price' => $data['default_labor_price'] ?? 0,
            'is_taxable' => (bool) ($data['is_taxable'] ?? true),
            'warranty_days' => $data['warranty_days'] ?? 0,
            'required_skill' => $data['required_skill'] ?? null,
            'required_bay_type' => $data['required_bay_type'] ?? null,
            'is_package' => (bool) ($data['is_package'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'description' => $data['description'] ?? null,
        ]);
    }
}
