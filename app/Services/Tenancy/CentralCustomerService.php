<?php

namespace App\Services\Tenancy;

use App\Models\Customer;
use App\Models\ProductCustomerProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CentralCustomerService
{
    public function findOrCreate(array $data, ?string $productKey = null, array $profile = []): Customer
    {
        return DB::transaction(function () use ($data, $productKey, $profile): Customer {
            $customer = $this->findExisting($data) ?: Customer::query()->create($this->customerPayload($data));

            $updates = $this->missingCustomerUpdates($customer, $data);
            if ($updates !== []) {
                $customer->forceFill($updates)->save();
            }

            if ($productKey !== null) {
                $this->attachProductProfile($customer, $productKey, $profile);
            }

            return $customer->refresh();
        });
    }

    public function attachProductProfile(Customer $customer, string $productKey, array $profile = []): ProductCustomerProfile
    {
        $tenantId = $this->tenantId();
        $profileType = (string) ($profile['profile_type'] ?? 'default');

        return ProductCustomerProfile::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'product_key' => $this->normalizeProductKey($productKey),
                'customer_id' => $customer->id,
                'profile_type' => $profileType,
            ],
            [
                'external_reference' => $profile['external_reference'] ?? null,
                'status' => $profile['status'] ?? 'active',
                'metadata' => $profile['metadata'] ?? null,
            ]
        );
    }

    protected function findExisting(array $data): ?Customer
    {
        $phone = $this->cleanNullable($data['phone'] ?? null);
        $email = $this->cleanNullable($data['email'] ?? null);

        if ($phone === null && $email === null) {
            return null;
        }

        return Customer::query()
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

    protected function customerPayload(array $data): array
    {
        $name = trim((string) ($data['name'] ?? $data['display_name'] ?? ''));

        if ($name === '') {
            $name = 'Customer ' . Str::upper(Str::random(6));
        }

        return [
            'tenant_id' => $data['tenant_id'] ?? $this->tenantId(),
            'customer_number' => $data['customer_number'] ?? null,
            'name' => $name,
            'display_name' => $this->cleanNullable($data['display_name'] ?? null),
            'phone' => $this->cleanNullable($data['phone'] ?? null),
            'email' => $this->cleanNullable($data['email'] ?? null),
            'tax_number' => $this->cleanNullable($data['tax_number'] ?? null),
            'address' => $this->cleanNullable($data['address'] ?? null),
            'customer_type' => $data['customer_type'] ?? 'individual',
            'company_name' => $this->cleanNullable($data['company_name'] ?? null),
            'status' => $data['status'] ?? 'active',
            'internal_notes' => $this->cleanNullable($data['internal_notes'] ?? null),
            'metadata' => $data['metadata'] ?? null,
        ];
    }

    protected function missingCustomerUpdates(Customer $customer, array $data): array
    {
        $payload = $this->customerPayload($data);
        $updates = [];

        foreach (['tenant_id', 'display_name', 'phone', 'email', 'tax_number', 'address', 'customer_type', 'company_name', 'status', 'internal_notes', 'metadata'] as $field) {
            if (($customer->{$field} === null || $customer->{$field} === '') && ($payload[$field] ?? null) !== null) {
                $updates[$field] = $payload[$field];
            }
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
