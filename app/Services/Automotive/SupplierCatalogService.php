<?php

namespace App\Services\Automotive;

use App\Models\Supplier;
use App\Services\Tenancy\CentralSupplierService;
use Illuminate\Support\Collection;

class SupplierCatalogService
{
    public function __construct(
        protected CentralSupplierService $centralSuppliers
    ) {
    }

    public function getSuppliers(int $limit = 25): Collection
    {
        return Supplier::query()
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function getActiveSuppliersCount(): int
    {
        return Supplier::query()
            ->where('is_active', true)
            ->count();
    }

    public function createSupplier(array $data): Supplier
    {
        return $this->centralSuppliers->findOrCreate($data, 'automotive_service', [
            'profile_type' => 'supplier_catalog',
            'metadata' => [
                'source' => 'supplier_catalog_service',
            ],
        ]);
    }
}
