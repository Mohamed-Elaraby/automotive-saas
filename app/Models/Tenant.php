<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    // optional: override database name pattern if you want
    // public function database(): string
    // {
    //     return 'tenant_' . $this->id;
    // }
}
