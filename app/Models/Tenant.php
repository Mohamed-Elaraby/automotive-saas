<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\DatabaseConfig;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    public function database(): DatabaseConfig
    {
        $dbName = $this->getInternal('db_name');

        if (! $dbName) {
            $dbName = 'tenant_' . $this->id;

            $this->setInternal('db_name', $dbName);
            $this->save();
        }

        return new DatabaseConfig($this, [
            'database' => $dbName,
        ]);
    }
}
