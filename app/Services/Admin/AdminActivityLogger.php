<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

class AdminActivityLogger
{
    public function log(
        string $action,
        ?string $subjectType = null,
        string|int|null $subjectId = null,
        ?string $tenantId = null,
        array $contextPayload = []
    ): AdminActivityLog {
        $admin = $this->resolveAdminUser();

        return AdminActivityLog::query()->create([
            'admin_user_id' => $this->adminUserId($admin),
            'admin_email' => $this->adminEmail($admin),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId !== null ? (string) $subjectId : null,
            'tenant_id' => $tenantId,
            'context_payload' => $contextPayload,
        ]);
    }

    protected function resolveAdminUser(): mixed
    {
        $admin = Auth::guard('admin')->user();

        if ($admin instanceof Admin) {
            return $admin;
        }

        $requestAdmin = request()?->user('admin');
        if ($requestAdmin instanceof Admin) {
            return $requestAdmin;
        }

        return null;
    }

    protected function adminUserId(mixed $admin): ?int
    {
        if ($admin instanceof Authenticatable && method_exists($admin, 'getAuthIdentifier')) {
            $id = $admin->getAuthIdentifier();

            return is_numeric($id) ? (int) $id : null;
        }

        return null;
    }

    protected function adminEmail(mixed $admin): ?string
    {
        if (is_object($admin) && isset($admin->email) && filled($admin->email)) {
            return (string) $admin->email;
        }

        return null;
    }
}
