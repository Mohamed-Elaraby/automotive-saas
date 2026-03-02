<?php

<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\DatabaseConfig;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    /**
     * Tell stancl where the tenant database name is stored.
     * We'll store it in the "data" JSON column.
     */
    public function database(): DatabaseConfig
    {
        // Pick a db name, store it once, then reuse it forever.
        $dbName = $this->getInternal('db_name');

        if (! $dbName) {
            $dbName = 'tenant_' . $this->id;
            $this->setInternal('db_name', $dbName);

            // Important: persist on central DB
            $this->save();
        }

        return new DatabaseConfig($this, [
            'database' => $dbName,
        ]);
    }
}
