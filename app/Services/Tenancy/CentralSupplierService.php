<?php

namespace App\Services\Tenancy;

use App\Models\ProductSupplierProfile;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CentralSupplierService
{
    public function findOrCreate(array $data, ?string $productKey = null, array $profile = []): Supplier
    {
        return DB::transaction(function () use ($data, $productKey, $profile): Supplier {
            $supplier = $this->findExisting($data) ?: Supplier::query()->create($this->supplierPayload($data));

            $updates = $this->missingSupplierUpdates($supplier, $data);
            if ($updates !== []) {
                $supplier->forceFill($updates)->save();
            }

            if ($productKey !== null) {
                $this->attachProductProfile($supplier, $productKey, $profile);
            }

            return $supplier->refresh();
        });
    }

    public function attachProductProfile(Supplier $supplier, string $productKey, array $profile = []): ProductSupplierProfile
    {
        $tenantId = $this->tenantId();
        $profileType = (string) ($profile['profile_type'] ?? 'default');

        return ProductSupplierProfile::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'product_key' => $this->normalizeProductKey($productKey),
                'supplier_id' => $supplier->id,
                'profile_type' => $profileType,
            ],
            [
                'external_reference' => $profile['external_reference'] ?? null,
                'status' => $profile['status'] ?? 'active',
                'metadata' => $profile['metadata'] ?? null,
            ]
        );
    }

    protected function findExisting(array $data): ?Supplier
    {
        $phone = $this->cleanNullable($data['phone'] ?? null);
        $email = $this->cleanNullable($data['email'] ?? null);

        if ($phone === null && $email === null) {
            return null;
        }

        return Supplier::query()
            ->where(function ($query) use ($phone, $email): void {
                if ($phone !== null) {
                    $query->orWhere('phone', $phone);
                }

                if ($email !== null) {
                    $query->orWhere('email', $email);
                }
            })
            ->orderBy('id')
            ->first();
    }

    protected function supplierPayload(array $data): array
    {
        $name = trim((string) ($data['name'] ?? $data['display_name'] ?? ''));

        if ($name === '') {
            $name = 'Supplier ' . Str::upper(Str::random(6));
        }

        $status = $data['status'] ?? ((bool) ($data['is_active'] ?? true) ? 'active' : 'inactive');

        return [
            'tenant_id' => $data['tenant_id'] ?? $this->tenantId(),
            'name' => $name,
            'display_name' => $this->cleanNullable($data['display_name'] ?? null),
            'contact_name' => $this->cleanNullable($data['contact_name'] ?? null),
            'phone' => $this->cleanNullable($data['phone'] ?? null),
            'email' => $this->cleanNullable($data['email'] ?? null),
            'tax_number' => $this->cleanNullable($data['tax_number'] ?? null),
            'address' => $this->cleanNullable($data['address'] ?? null),
            'notes' => $this->cleanNullable($data['notes'] ?? null),
            'is_active' => $status === 'active',
            'status' => $status,
            'metadata' => $data['metadata'] ?? null,
        ];
    }

    protected function missingSupplierUpdates(Supplier $supplier, array $data): array
    {
        $payload = $this->supplierPayload($data);
        $updates = [];

        foreach (['tenant_id', 'display_name', 'contact_name', 'phone', 'email', 'tax_number', 'address', 'notes', 'status', 'metadata'] as $field) {
            if (($supplier->{$field} === null || $supplier->{$field} === '') && ($payload[$field] ?? null) !== null) {
                $updates[$field] = $payload[$field];
            }
        }

        if (! $supplier->is_active && $payload['is_active']) {
            $updates['is_active'] = true;
        }

        return $updates;
    }

    protected function tenantId(): ?string
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        return $tenant?->id ? (string) $tenant->id : null;
    }

    protected function normalizeProductKey(string $productKey): string
    {
        return trim($productKey);
    }

    protected function cleanNullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
