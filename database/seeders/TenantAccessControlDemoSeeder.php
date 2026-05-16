<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ProductRole;
use App\Models\TenantProductBranch;
use App\Models\TenantUserProductAccess;
use App\Models\TenantUserProductBranch;
use App\Models\TenantUserProductRole;
use App\Models\User;
use App\Services\Tenancy\ProductBranchAccessService;
use App\Services\Tenancy\ProductPermissionCatalogService;
use App\Services\Tenancy\ProductPermissionService;
use App\Services\Tenancy\TenantUserProductAccessService;
use App\Services\Tenancy\UserRoleAssignmentService;
use App\Services\Tenancy\WorkspaceOwnerAccessService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Throwable;

class TenantAccessControlDemoSeeder extends Seeder
{
    protected string $productKey = ProductPermissionCatalogService::PRODUCT_AUTOMOTIVE;

    protected array $summary = [
        'users_created' => [],
        'users_found' => [],
        'branches_created' => [],
        'branches_found' => [],
        'product_branches_enabled' => [],
        'product_branches_found' => [],
        'product_branches_skipped' => [],
        'product_access_granted' => [],
        'product_access_found' => [],
        'product_access_skipped' => [],
        'branch_access_granted' => [],
        'branch_access_found' => [],
        'branch_access_skipped' => [],
        'roles_assigned' => [],
        'roles_found' => [],
        'roles_skipped' => [],
        'owner_sync' => [],
        'warnings' => [],
    ];

    protected array $demoUsers = [
        'owner' => ['name' => 'Demo Workspace Owner', 'email' => 'demo.owner@seven-scapital.test', 'role' => 'Tenant Owner'],
        'branch_manager' => ['name' => 'Demo Branch Manager', 'email' => 'demo.manager@seven-scapital.test', 'role' => 'Automotive Branch Manager'],
        'service_advisor' => ['name' => 'Demo Service Advisor', 'email' => 'demo.advisor@seven-scapital.test', 'role' => 'Automotive Service Advisor'],
        'technician' => ['name' => 'Demo Technician', 'email' => 'demo.technician@seven-scapital.test', 'role' => 'Automotive Technician'],
        'accountant' => ['name' => 'Demo Accountant', 'email' => 'demo.accountant@seven-scapital.test', 'role' => 'Automotive Accountant'],
        'viewer' => ['name' => 'Demo Viewer', 'email' => 'demo.viewer@seven-scapital.test', 'role' => 'Automotive Viewer'],
        'missing_branch' => ['name' => 'Demo Missing Branch User', 'email' => 'demo.missing-branch@seven-scapital.test', 'role' => null],
    ];

    public function run(): void
    {
        app(ProductPermissionCatalogService::class)->seedDefaultPermissionsIfMissing($this->productKey);

        $branches = $this->seedBranches();
        $users = $this->seedUsers();
        $enabledBranches = $this->enableProductBranches($branches);

        $this->syncOwnerAccess($users['owner']);
        $this->grantProductAccess($users);
        $this->grantBranchAccess($users, $enabledBranches);
        $this->assignRoles($users);
        $this->printSummary();
    }

    protected function seedBranches(): array
    {
        return [
            'dubai' => $this->firstOrUpdateBranch('DXB-DEMO', 'Dubai Branch', 'Dubai', 'Dubai'),
            'ajman' => $this->firstOrUpdateBranch('AJM-DEMO', 'Ajman Branch', 'Ajman', 'Ajman'),
            'abu_dhabi' => $this->firstOrUpdateBranch('AUH-DEMO', 'Abu Dhabi Branch', 'Abu Dhabi', 'Abu Dhabi'),
        ];
    }

    protected function firstOrUpdateBranch(string $code, string $name, string $emirate, string $city): Branch
    {
        $branch = Branch::query()->firstOrNew(['code' => $code]);
        $created = ! $branch->exists;

        $branch->fill([
            'name' => $name,
            'phone' => $branch->phone,
            'email' => $branch->email,
            'address' => $branch->address ?: $city,
            'emirate' => $emirate,
            'city' => $city,
            'country' => 'United Arab Emirates',
            'timezone' => 'Asia/Dubai',
            'is_active' => true,
        ])->save();

        $this->summary[$created ? 'branches_created' : 'branches_found'][] = "{$name} ({$code})";

        return $branch;
    }

    protected function seedUsers(): array
    {
        $users = [];

        foreach ($this->demoUsers as $key => $definition) {
            $users[$key] = $this->firstOrCreateUser($key, $definition['name'], $definition['email']);
        }

        if (! app(WorkspaceOwnerAccessService::class)->isWorkspaceOwner($users['owner'])) {
            $this->summary['warnings'][] = 'Demo Workspace Owner was created/found but is not tenant user id 1, so implicit owner access cannot be reassigned safely by this seeder.';
        }

        return $users;
    }

    protected function firstOrCreateUser(string $key, string $name, string $email): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $this->summary['users_found'][] = "{$name} <{$email}>";
            $this->markUserActiveIfSupported($user);

            return $user;
        }

        $attributes = [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
        ];

        $user = User::query()->create($this->withActiveAttributes($attributes));
        $this->summary['users_created'][] = "{$name} <{$email}>";

        return $user;
    }

    protected function withActiveAttributes(array $attributes): array
    {
        $user = new User();

        foreach (['is_active' => true, 'status' => 'active'] as $column => $value) {
            if (in_array($column, $user->getFillable(), true)) {
                $attributes[$column] = $value;
            }
        }

        return $attributes;
    }

    protected function markUserActiveIfSupported(User $user): void
    {
        $updates = [];

        foreach (['is_active' => true, 'status' => 'active'] as $column => $value) {
            if (in_array($column, $user->getFillable(), true) && $user->{$column} !== $value) {
                $updates[$column] = $value;
            }
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }

    protected function enableProductBranches(array $branches): array
    {
        $enabled = [];
        $branchAccess = app(ProductBranchAccessService::class);

        foreach ($branches as $key => $branch) {
            if ($branchAccess->isBranchEnabled($branch, $this->productKey)) {
                $enabled[$key] = $branch;
                $this->summary['product_branches_found'][] = $branch->name;
                continue;
            }

            try {
                $branchAccess->enableBranch($branch, $this->productKey, [
                    'source' => 'access_control_demo_seeder',
                ]);
                $enabled[$key] = $branch;
                $this->summary['product_branches_enabled'][] = $branch->name;
            } catch (RuntimeException $exception) {
                $this->summary['product_branches_skipped'][] = "{$branch->name}: {$exception->getMessage()}";
            }
        }

        return $enabled;
    }

    protected function syncOwnerAccess(User $owner): void
    {
        if (! app(WorkspaceOwnerAccessService::class)->isWorkspaceOwner($owner)) {
            $this->summary['owner_sync'][] = 'Skipped: Demo Workspace Owner is not tenant user id 1 in this tenant.';
            return;
        }

        $summary = app(WorkspaceOwnerAccessService::class)->syncOwnerAccess($owner);
        $this->summary['owner_sync'][] = 'Synced owner access: ' . json_encode($summary, JSON_UNESCAPED_SLASHES);
    }

    protected function grantProductAccess(array $users): void
    {
        $productAccess = app(TenantUserProductAccessService::class);
        $owner = $users['owner'];

        foreach ($users as $key => $user) {
            if ($key === 'owner') {
                continue;
            }

            if ($this->hasActiveProductAccess($user)) {
                $this->summary['product_access_found'][] = $user->email;
                continue;
            }

            try {
                $productAccess->grantAccess($user, $this->productKey, $owner, [
                    'source' => 'access_control_demo_seeder',
                ]);
                $this->summary['product_access_granted'][] = $user->email;
            } catch (RuntimeException $exception) {
                $this->summary['product_access_skipped'][] = "{$user->email}: {$exception->getMessage()}";
            }
        }
    }

    protected function grantBranchAccess(array $users, array $enabledBranches): void
    {
        $assignments = [
            'branch_manager' => ['dubai', 'ajman'],
            'service_advisor' => ['dubai'],
            'technician' => ['dubai'],
            'accountant' => ['dubai', 'ajman'],
            'viewer' => ['first'],
        ];

        foreach ($assignments as $userKey => $branchKeys) {
            $user = $users[$userKey] ?? null;

            if (! $user || ! $this->hasActiveProductAccess($user)) {
                $this->summary['branch_access_skipped'][] = "{$userKey}: user has no active {$this->productKey} access.";
                continue;
            }

            foreach ($this->resolveBranchKeys($branchKeys, $enabledBranches) as $branchKey) {
                $branch = $enabledBranches[$branchKey] ?? null;

                if (! $branch) {
                    $this->summary['branch_access_skipped'][] = "{$user->email}: branch [{$branchKey}] is not enabled for {$this->productKey}.";
                    continue;
                }

                $this->grantSingleBranchAccess($user, $branch);
            }
        }
    }

    protected function resolveBranchKeys(array $branchKeys, array $enabledBranches): array
    {
        $keys = [];

        foreach ($branchKeys as $branchKey) {
            if ($branchKey === 'first') {
                $firstKey = array_key_first($enabledBranches);
                if ($firstKey !== null) {
                    $keys[] = $firstKey;
                }
                continue;
            }

            if (isset($enabledBranches[$branchKey])) {
                $keys[] = $branchKey;
                continue;
            }

            $firstKey = array_key_first($enabledBranches);
            if ($firstKey !== null) {
                $keys[] = $firstKey;
            }
        }

        return array_values(array_unique($keys));
    }

    protected function grantSingleBranchAccess(User $user, Branch $branch): void
    {
        if ($this->hasActiveBranchAccess($user, $branch)) {
            $this->summary['branch_access_found'][] = "{$user->email} -> {$branch->name}";
            return;
        }

        try {
            app(ProductBranchAccessService::class)->grantUserBranchAccess($user, $branch, $this->productKey, 'member', [
                'source' => 'access_control_demo_seeder',
            ]);
            $this->summary['branch_access_granted'][] = "{$user->email} -> {$branch->name}";
        } catch (RuntimeException $exception) {
            $this->summary['branch_access_skipped'][] = "{$user->email} -> {$branch->name}: {$exception->getMessage()}";
        }
    }

    protected function assignRoles(array $users): void
    {
        $roles = ProductRole::query()
            ->where('tenant_id', (string) tenant()->id)
            ->where('product_key', $this->productKey)
            ->get()
            ->keyBy('name');

        foreach ($this->demoUsers as $userKey => $definition) {
            $roleName = $definition['role'] ?? null;
            $user = $users[$userKey] ?? null;
            $role = $roleName ? ($roles[$roleName] ?? null) : null;

            if (! $roleName) {
                $this->summary['roles_skipped'][] = "{$definition['email']}: no role assigned by design.";
                continue;
            }

            if (! $user || ! $role) {
                $this->summary['roles_skipped'][] = "{$definition['email']}: role [{$roleName}] was not found.";
                continue;
            }

            if (! $this->hasActiveProductAccess($user)) {
                $this->summary['roles_skipped'][] = "{$user->email}: no active {$this->productKey} access.";
                continue;
            }

            if ($this->hasActiveRole($user, $role)) {
                $this->summary['roles_found'][] = "{$user->email} -> {$role->name}";
                continue;
            }

            try {
                app(UserRoleAssignmentService::class)->syncUserProductRoles($user, [
                    $this->productKey => $role->id,
                ]);
                $this->summary['roles_assigned'][] = "{$user->email} -> {$role->name}";
            } catch (Throwable $exception) {
                $this->summary['roles_skipped'][] = "{$user->email} -> {$role->name}: {$exception->getMessage()}";
            }
        }
    }

    protected function hasActiveProductAccess(User $user): bool
    {
        return TenantUserProductAccess::query()
            ->where('tenant_id', (string) tenant()->id)
            ->where('user_id', $user->id)
            ->where('product_key', $this->productKey)
            ->active()
            ->exists();
    }

    protected function hasActiveBranchAccess(User $user, Branch $branch): bool
    {
        return TenantUserProductBranch::query()
            ->where('tenant_id', (string) tenant()->id)
            ->where('user_id', $user->id)
            ->where('product_key', $this->productKey)
            ->where('branch_id', $branch->id)
            ->enabled()
            ->exists();
    }

    protected function hasActiveRole(User $user, ProductRole $role): bool
    {
        return TenantUserProductRole::query()
            ->where('tenant_id', (string) tenant()->id)
            ->where('user_id', $user->id)
            ->where('product_key', $this->productKey)
            ->where('product_role_id', $role->id)
            ->active()
            ->exists();
    }

    protected function printSummary(): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->info('Access Control demo seeder completed.');

        foreach ($this->summary as $label => $items) {
            if ($items === []) {
                continue;
            }

            $this->command->line(str_replace('_', ' ', ucfirst($label)) . ':');
            foreach ($items as $item) {
                $this->command->line("  - {$item}");
            }
        }

        $this->command->line('Demo login credentials:');
        foreach ($this->demoUsers as $definition) {
            $this->command->line("  - {$definition['name']}: {$definition['email']} / password");
        }

        $enabledBranchCount = TenantProductBranch::query()
            ->where('tenant_id', (string) tenant()->id)
            ->where('product_key', $this->productKey)
            ->enabled()
            ->count();

        $this->command->line("Enabled {$this->productKey} demo/product branches available for QA: {$enabledBranchCount}");
    }
}
