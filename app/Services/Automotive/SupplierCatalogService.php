<?php

namespace App\Services\Automotive;

use App\Models\Supplier;
use Illuminate\Support\Collection;

class SupplierCatalogService
{
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
        return Supplier::query()->create([
            'name' => $data['name'],
            'contact_name' => $data['contact_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }
}
