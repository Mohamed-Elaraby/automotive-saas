<?php

namespace Database\Seeders;

use App\Services\Tenancy\ProductPermissionCatalogService;
use Illuminate\Database\Seeder;

class TenantProductPermissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        app(ProductPermissionCatalogService::class)
            ->seedDefaultPermissionsIfMissing(ProductPermissionCatalogService::PRODUCT_AUTOMOTIVE);
    }
}
