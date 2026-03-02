<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    /**
     * Return the tenant database name.
     * You can customize the prefix to avoid collisions.
     */
    public function database(): string
    {
        return 'tenant_' . $this->id;
    }
}
