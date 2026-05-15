<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ProductRole;
use App\Models\TenantUserProductRole;
use App\Models\User;
use App\Services\Tenancy\ProductBranchAccessService;
use App\Services\Tenancy\ProductPermissionCatalogService;
use App\Services\Tenancy\ProductPermissionService;
use App\Services\Tenancy\TenantUserProductAccessService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class TenantAccessControlDemoSeeder extends Seeder
{
    protected string $productKey = ProductPermissionCatalogService::PRODUCT_AUTOMOTIVE;

    public function run(): void
    {
        app(ProductPermissionCatalogService::class)->seedDefaultPermissionsIfMissing($this->productKey);

        $branches = $this->seedBranches();
        $users = $this->seedUsers();
        $this->enableProductBranches($branches);
        $this->grantProductAccess($users);
        $this->grantBranchAccess($users, $branches);
        $this->assignRoles($users);
    }

    protected function seedBranches(): array
    {
        return [
            'dubai' => Branch::query()->updateOrCreate(
                ['code' => 'DXB-DEMO'],
                $this->branchAttributes('Dubai Branch', 'Dubai', 'Dubai')
            ),
            'ajman' => Branch::query()->updateOrCreate(
                ['code' => 'AJM-DEMO'],
                $this->branchAttributes('Ajman Branch', 'Ajman', 'Ajman')
            ),
            'abu_dhabi' => Branch::query()->updateOrCreate(
                ['code' => 'AUH-DEMO'],
                $this->branchAttributes('Abu Dhabi Branch', 'Abu Dhabi', 'Abu Dhabi')
            ),
        ];
    }

    protected function branchAttributes(string $name, string $emirate, string $city): array
    {
        return [
            'name' => $name,
            'phone' => null,
            'email' => null,
            'address' => $city,
            'emirate' => $emirate,
            'city' => $city,
            'country' => 'United Arab Emirates',
            'timezone' => 'Asia/Dubai',
            'is_active' => true,
        ];
    }

    protected function seedUsers(): array
    {
        $owner = User::query()->orderBy('id')->first()
            ?? User::query()->create($this->userAttributes('Workspace Owner', 'workspace.owner@example.test'));

        return [
            'owner' => $owner,
            'branch_manager' => User::query()->firstOrCreate(
                ['email' => 'branch.manager@example.test'],
                $this->userAttributes('Branch Manager', 'branch.manager@example.test')
            ),
            'service_advisor' => User::query()->firstOrCreate(
                ['email' => 'service.advisor@example.test'],
                $this->userAttributes('Service Advisor', 'service.advisor@example.test')
            ),
            'technician' => User::query()->firstOrCreate(
                ['email' => 'technician@example.test'],
                $this->userAttributes('Technician', 'technician@example.test')
            ),
            'accountant' => User::query()->firstOrCreate(
                ['email' => 'accountant@example.test'],
                $this->userAttributes('Accountant', 'accountant@example.test')
            ),
            'viewer' => User::query()->firstOrCreate(
                ['email' => 'viewer@example.test'],
                $this->userAttributes('Viewer', 'viewer@example.test')
            ),
        ];
    }

    protected function userAttributes(string $name, string $email): array
    {
        return [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
        ];
    }

    protected function enableProductBranches(array $branches): void
    {
        $branchAccess = app(ProductBranchAccessService::class);

        foreach ($branches as $branch) {
            try {
                $branchAccess->enableBranch($branch, $this->productKey, [
                    'source' => 'access_control_demo_seeder',
                ]);
            } catch (RuntimeException) {
                continue;
            }
        }
    }

    protected function grantProductAccess(array $users): void
    {
        $productAccess = app(TenantUserProductAccessService::class);
        $owner = $users['owner'];

        foreach ($users as $user) {
            try {
                $productAccess->grantAccess($user, $this->productKey, $owner, [
                    'source' => 'access_control_demo_seeder',
                ]);
            } catch (RuntimeException) {
                continue;
            }
        }
    }

    protected function grantBranchAccess(array $users, array $branches): void
    {
        $branchAccess = app(ProductBranchAccessService::class);
        $assignments = [
            'branch_manager' => ['dubai', 'ajman'],
            'service_advisor' => ['dubai'],
            'technician' => ['dubai'],
            'accountant' => ['dubai', 'abu_dhabi'],
            'viewer' => ['ajman'],
        ];

        foreach ($assignments as $userKey => $branchKeys) {
            foreach ($branchKeys as $branchKey) {
                if (! isset($users[$userKey], $branches[$branchKey])) {
                    continue;
                }

                try {
                    $branchAccess->grantUserBranchAccess($users[$userKey], $branches[$branchKey], $this->productKey, 'member', [
                        'source' => 'access_control_demo_seeder',
                    ]);
                } catch (RuntimeException) {
                    continue;
                }
            }
        }
    }

    protected function assignRoles(array $users): void
    {
        $roles = ProductRole::query()
            ->where('tenant_id', (string) tenant()->id)
            ->where('product_key', $this->productKey)
            ->get()
            ->keyBy('name');

        $assignments = [
            'owner' => 'Tenant Owner',
            'branch_manager' => 'Automotive Branch Manager',
            'service_advisor' => 'Automotive Service Advisor',
            'technician' => 'Automotive Technician',
            'accountant' => 'Automotive Accountant',
            'viewer' => 'Automotive Viewer',
        ];

        foreach ($assignments as $userKey => $roleName) {
            $user = $users[$userKey] ?? null;
            $role = $roles[$roleName] ?? null;

            if (! $user || ! $role) {
                continue;
            }

            try {
                app(ProductPermissionService::class)->assignRole($user, $role, [
                    'assignment_source' => 'access_control_demo_seeder',
                    'single_role_per_product' => true,
                ]);

                TenantUserProductRole::query()
                    ->where('tenant_id', (string) tenant()->id)
                    ->where('user_id', $user->id)
                    ->where('product_key', $this->productKey)
                    ->where('product_role_id', '!=', $role->id)
                    ->active()
                    ->update([
                        'is_active' => false,
                        'revoked_at' => now(),
                    ]);
            } catch (RuntimeException) {
                continue;
            }
        }
    }
}
