<?php

namespace App\Services\Admin;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class TenantImpersonationService
{
    public function start(string $tenantId): string
    {
        $tenant = $this->findTenantOrFail($tenantId);
        $primaryDomain = $this->primaryDomainForTenant($tenantId);

        if (! $primaryDomain) {
            throw new RuntimeException('This tenant does not have a primary domain to impersonate into.');
        }

        $targetUser = $this->impersonatableUserForTenant($tenantId);

        if (! $targetUser) {
            throw new RuntimeException('No tenant owner/admin user could be resolved for impersonation.');
        }

        $admin = Auth::guard('admin')->user();
        $token = $this->encodePayload([
            'tenant_id' => $tenantId,
            'tenant_domain' => $primaryDomain,
            'target_user_email' => (string) $targetUser->email,
            'target_user_name' => (string) ($targetUser->name ?? $targetUser->email),
            'central_admin_id' => $admin instanceof Admin ? $admin->getAuthIdentifier() : null,
            'central_admin_email' => $admin instanceof Admin ? $admin->email : null,
            'return_url' => $this->centralTenantShowUrl($tenantId),
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
            'nonce' => Str::random(32),
        ]);

        return $this->tenantImpersonationUrl($primaryDomain, $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function consume(string $token, string $expectedTenantId): array
    {
        $payload = $this->decodePayload($token);

        if (! is_array($payload)) {
            throw new RuntimeException('This impersonation link is invalid or has expired.');
        }

        if (($payload['tenant_id'] ?? null) !== $expectedTenantId) {
            throw new RuntimeException('This impersonation link does not belong to the current tenant.');
        }

        if (empty($payload['expires_at']) || now()->greaterThan($payload['expires_at'])) {
            throw new RuntimeException('This impersonation link is invalid or has expired.');
        }

        return $payload;
    }

    public function centralTenantShowUrl(string $tenantId): string
    {
        $baseUrl = $this->centralBaseUrl();

        return rtrim($baseUrl, '/') . '/admin/tenants/' . urlencode($tenantId);
    }

    protected function tenantImpersonationUrl(string $domain, string $token): string
    {
        return rtrim($this->domainToUrl($domain), '/') . '/automotive/admin/impersonate/' . urlencode($token);
    }

    protected function centralBaseUrl(): string
    {
        $appUrl = trim((string) config('app.url'));

        if ($appUrl !== '') {
            return $appUrl;
        }

        $centralDomain = collect((array) Config::get('tenancy.central_domains', []))
            ->filter(fn ($domain) => filled($domain))
            ->map(fn ($domain) => (string) $domain)
            ->first();

        if (! $centralDomain) {
            throw new RuntimeException('No central domain is configured for impersonation return URLs.');
        }

        return $this->domainToUrl($centralDomain);
    }

    protected function domainToUrl(string $domain): string
    {
        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return $domain;
        }

        return 'https://' . $domain;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function encodePayload(array $payload): string
    {
        $encrypted = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));

        return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodePayload(string $token): array
    {
        try {
            $decoded = base64_decode(strtr($token, '-_', '+/') . str_repeat('=', (4 - strlen($token) % 4) % 4), true);

            if ($decoded === false) {
                throw new RuntimeException('Invalid impersonation token.');
            }

            $json = Crypt::decryptString($decoded);
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($payload) ? $payload : [];
        } catch (\Throwable) {
            throw new RuntimeException('This impersonation link is invalid or has expired.');
        }
    }

    protected function tenantModelClass(): string
    {
        return (string) (Config::get('tenancy.tenant_model') ?: \App\Models\Tenant::class);
    }

    protected function findTenantOrFail(string $tenantId): Model
    {
        $tenantModelClass = $this->tenantModelClass();

        /** @var Model|null $tenant */
        $tenant = $tenantModelClass::query()->find($tenantId);

        if (! $tenant) {
            throw new RuntimeException('The tenant record was not found.');
        }

        return $tenant;
    }

    protected function centralConnectionName(): string
    {
        return (string) (Config::get('tenancy.database.central_connection') ?: Config::get('database.default'));
    }

    protected function primaryDomainForTenant(string $tenantId): ?string
    {
        $connection = $this->centralConnectionName();

        if (! Schema::connection($connection)->hasTable('domains')) {
            return null;
        }

        return DB::connection($connection)
            ->table('domains')
            ->where('tenant_id', $tenantId)
            ->orderBy('domain')
            ->value('domain');
    }

    protected function impersonatableUserForTenant(string $tenantId): ?object
    {
        $connection = $this->centralConnectionName();

        if (
            ! Schema::connection($connection)->hasTable('tenant_users')
            || ! Schema::connection($connection)->hasTable('users')
        ) {
            return null;
        }

        return DB::connection($connection)
            ->table('tenant_users')
            ->join('users', 'users.id', '=', 'tenant_users.user_id')
            ->where('tenant_users.tenant_id', $tenantId)
            ->orderByRaw("CASE tenant_users.role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END")
            ->orderBy('tenant_users.id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'tenant_users.role',
            ])
            ->first();
    }
}
