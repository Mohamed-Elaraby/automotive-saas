<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Services\Tenancy\ProductBranchAccessService;
use Illuminate\Database\Seeder;

class TenantBranchDemoSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['code' => 'AUH-MAIN', 'name' => 'Abu Dhabi Main Branch', 'emirate' => 'Abu Dhabi', 'city' => 'Abu Dhabi'],
            ['code' => 'DXB-BR', 'name' => 'Dubai Branch', 'emirate' => 'Dubai', 'city' => 'Dubai'],
            ['code' => 'SHJ-BR', 'name' => 'Sharjah Branch', 'emirate' => 'Sharjah', 'city' => 'Sharjah'],
            ['code' => 'AAN-BR', 'name' => 'Al Ain Branch', 'emirate' => 'Abu Dhabi', 'city' => 'Al Ain'],
            ['code' => 'MUS-BR', 'name' => 'Musaffah Branch', 'emirate' => 'Abu Dhabi', 'city' => 'Musaffah'],
        ];

        foreach ($branches as $branch) {
            Branch::query()->updateOrCreate(
                ['code' => $branch['code']],
                [
                    'name' => $branch['name'],
                    'phone' => null,
                    'email' => null,
                    'address' => $branch['city'],
                    'emirate' => $branch['emirate'],
                    'city' => $branch['city'],
                    'country' => 'United Arab Emirates',
                    'timezone' => 'Asia/Dubai',
                    'is_active' => true,
                ]
            );
        }

        $this->activateDemoBranchesForAutomotive();
    }

    protected function activateDemoBranchesForAutomotive(): void
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        if (! $tenant) {
            return;
        }

        $service = app(ProductBranchAccessService::class);

        foreach (Branch::query()->whereIn('code', ['AUH-MAIN', 'DXB-BR', 'SHJ-BR'])->orderBy('id')->get() as $branch) {
            try {
                $service->enableBranch($branch, 'automotive_service', ['source' => 'tenant_branch_demo_seeder']);
            } catch (\RuntimeException) {
                break;
            }
        }
    }
}
