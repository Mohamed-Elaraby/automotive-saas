<?php

namespace App\Services\Tenancy;

use App\Models\Employee;
use App\Models\ProductEmployeeProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CentralEmployeeService
{
    public function findOrCreate(array $data, ?string $productKey = null, array $profile = []): Employee
    {
        return DB::transaction(function () use ($data, $productKey, $profile): Employee {
            $employee = $this->findExisting($data) ?: Employee::query()->create($this->employeePayload($data));

            $updates = $this->missingEmployeeUpdates($employee, $data);
            if ($updates !== []) {
                $employee->forceFill($updates)->save();
            }

            if ($productKey !== null) {
                $this->attachProductProfile($employee, $productKey, $profile);
            }

            return $employee->refresh();
        });
    }

    public function linkUser(Employee $employee, User|int|null $user): Employee
    {
        $employee->forceFill([
            'user_id' => $user instanceof User ? $user->id : $user,
        ])->save();

        return $employee->refresh();
    }

    public function attachProductProfile(Employee $employee, string $productKey, array $profile = []): ProductEmployeeProfile
    {
        $tenantId = $this->tenantId();
        $profileType = (string) ($profile['profile_type'] ?? $employee->employee_type ?: 'default');

        return ProductEmployeeProfile::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'product_key' => $this->normalizeProductKey($productKey),
                'employee_id' => $employee->id,
                'profile_type' => $profileType,
            ],
            [
                'external_reference' => $profile['external_reference'] ?? null,
                'status' => $profile['status'] ?? 'active',
                'metadata' => $profile['metadata'] ?? null,
            ]
        );
    }

    protected function findExisting(array $data): ?Employee
    {
        $userId = $data['user_id'] ?? null;
        $phone = $this->cleanNullable($data['phone'] ?? null);
        $email = $this->cleanNullable($data['email'] ?? null);

        if ($userId === null && $phone === null && $email === null) {
            return null;
        }

        return Employee::query()
            ->where(function ($query) use ($userId, $phone, $email): void {
                if ($userId !== null) {
                    $query->orWhere('user_id', (int) $userId);
                }

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

    protected function employeePayload(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            $name = 'Employee ' . Str::upper(Str::random(6));
        }

        return [
            'tenant_id' => $data['tenant_id'] ?? $this->tenantId(),
            'user_id' => $data['user_id'] ?? null,
            'name' => $name,
            'phone' => $this->cleanNullable($data['phone'] ?? null),
            'email' => $this->cleanNullable($data['email'] ?? null),
            'job_title' => $this->cleanNullable($data['job_title'] ?? null),
            'employee_type' => $data['employee_type'] ?? Employee::TYPE_WORKER,
            'status' => $data['status'] ?? 'active',
            'metadata' => $data['metadata'] ?? null,
        ];
    }

    protected function missingEmployeeUpdates(Employee $employee, array $data): array
    {
        $payload = $this->employeePayload($data);
        $updates = [];

        foreach (['tenant_id', 'user_id', 'phone', 'email', 'job_title', 'employee_type', 'status', 'metadata'] as $field) {
            if (($employee->{$field} === null || $employee->{$field} === '') && ($payload[$field] ?? null) !== null) {
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
