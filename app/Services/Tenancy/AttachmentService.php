<?php

namespace App\Services\Tenancy;

use App\Models\TenantAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttachmentService
{
    public function __construct(
        protected ProductEntitlementService $entitlements,
        protected BranchScopeService $branchScope
    ) {
    }

    public function storeAttachment(Model $attachable, UploadedFile $file, string $productKey, array $data = []): TenantAttachment
    {
        $this->validateAttachment($file, $productKey);
        $this->assertWithinStorageLimit($productKey, (int) ($file->getSize() ?: 0));

        $tenantId = $this->tenantId();
        $disk = $data['disk'] ?? 'public';
        $visibility = $data['visibility'] ?? 'private';
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));
        $storedName = ($data['stored_name'] ?? Str::uuid()->toString()) . ($extension ? '.' . $extension : '');
        $directory = $this->directory($tenantId, $productKey, $attachable, $data);
        $path = $file->storeAs($directory, $storedName, $disk);

        return TenantAttachment::query()->create([
            'tenant_id' => $tenantId,
            'product_key' => $productKey,
            'branch_id' => $data['branch_id'] ?? null,
            'attachable_type' => $attachable::class,
            'attachable_id' => $attachable->getKey(),
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => basename($path),
            'mime_type' => $file->getClientMimeType(),
            'extension' => $extension,
            'file_size' => $file->getSize() ?: 0,
            'disk' => $disk,
            'storage_path' => $path,
            'visibility' => $visibility,
            'uploaded_by' => $data['uploaded_by'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    public function deleteAttachment(TenantAttachment $attachment, bool $deleteFile = true): bool
    {
        if ($deleteFile && $attachment->storage_path) {
            Storage::disk($attachment->disk ?: 'public')->delete($attachment->storage_path);
        }

        return (bool) $attachment->delete();
    }

    public function listForEntity(Model $attachable, ?string $productKey = null): Collection
    {
        return TenantAttachment::query()
            ->where('attachable_type', $attachable::class)
            ->where('attachable_id', $attachable->getKey())
            ->when($productKey, fn ($query) => $query->where('product_key', $productKey))
            ->latest('id')
            ->get();
    }

    public function listForEntityVisibleToUser(Model $attachable, User $user, ?string $productKey = null): Collection
    {
        $productKey = $productKey ?: 'automotive_service';
        $query = TenantAttachment::query()
            ->where('attachable_type', $attachable::class)
            ->where('attachable_id', $attachable->getKey())
            ->where('product_key', $productKey);

        $this->branchScope->applyAllowedBranchesOrGlobal($query, $user, $productKey);

        return $query->latest('id')->get();
    }

    public function validateAttachment(UploadedFile $file, string $productKey): void
    {
        $maxMb = $this->maxFileSizeMb($productKey);
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));
        $allowed = $this->allowedFileTypes($productKey);

        if ($maxMb !== null && ($file->getSize() ?: 0) > ($maxMb * 1024 * 1024)) {
            throw ValidationException::withMessages([
                'file' => "File size exceeds the {$maxMb} MB limit.",
            ]);
        }

        if ($allowed !== [] && ! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => 'File type is not allowed for this product plan.',
            ]);
        }
    }

    public function calculateTenantUsage(?string $tenantId = null): int
    {
        return (int) TenantAttachment::query()
            ->where('tenant_id', $tenantId ?? $this->tenantId())
            ->sum('file_size');
    }

    public function calculateProductUsage(string $productKey, ?string $tenantId = null): int
    {
        return (int) TenantAttachment::query()
            ->where('tenant_id', $tenantId ?? $this->tenantId())
            ->where('product_key', $productKey)
            ->sum('file_size');
    }

    public function assertWithinStorageLimit(string $productKey, int $incomingBytes = 0): void
    {
        $limitMb = $this->storageLimitMb($productKey);

        if ($limitMb === null) {
            return;
        }

        $limitBytes = $limitMb * 1024 * 1024;
        $used = $this->calculateProductUsage($productKey);

        if (($used + $incomingBytes) > $limitBytes) {
            throw ValidationException::withMessages([
                'file' => "Product storage limit of {$limitMb} MB has been reached.",
            ]);
        }
    }

    public function storageLimitMb(string $productKey): ?int
    {
        $tenantId = $this->tenantId();
        $limit = $this->integerPlanLimit($tenantId, $productKey, 'storage_limit_mb');

        if ($limit === null) {
            return null;
        }

        return $limit + $this->entitlements->activeAddonQuantity($tenantId, $productKey, 'extra_storage');
    }

    public function maxFileSizeMb(string $productKey): ?int
    {
        $tenantId = $this->tenantId();
        $limit = $this->integerPlanLimit($tenantId, $productKey, 'max_file_size_mb');

        if ($limit === null) {
            return null;
        }

        return $limit + $this->entitlements->activeAddonQuantity($tenantId, $productKey, 'extra_file_size_limit');
    }

    public function allowedFileTypes(string $productKey): array
    {
        $value = $this->rawPlanLimit($this->tenantId(), $productKey, 'allowed_file_types');

        if ($value === null || trim((string) $value) === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        $items = is_array($decoded) ? $decoded : explode(',', (string) $value);

        return collect($items)
            ->map(fn ($type) => strtolower(trim((string) $type, " \t\n\r\0\x0B.")))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function directory(string $tenantId, string $productKey, Model $attachable, array $data): string
    {
        return trim((string) ($data['directory'] ?? sprintf(
            'tenants/%s/attachments/%s/%s/%s',
            $tenantId ?: 'central',
            $productKey,
            $attachable->getTable(),
            $attachable->getKey()
        )), '/');
    }

    protected function integerPlanLimit(string $tenantId, string $productKey, string $limitKey): ?int
    {
        $value = $this->rawPlanLimit($tenantId, $productKey, $limitKey);

        return $value === null || $value === '' ? null : (int) $value;
    }

    protected function rawPlanLimit(string $tenantId, string $productKey, string $limitKey): mixed
    {
        $subscription = $this->entitlements->subscriptionFor($tenantId, $productKey);
        $connection = config('tenancy.database.central_connection') ?? config('database.default');

        if (! $subscription || ! Schema::connection($connection)->hasTable('plan_limits')) {
            return null;
        }

        return DB::connection($connection)
            ->table('plan_limits')
            ->where('plan_id', (int) $subscription->plan_id)
            ->where('product_key', $productKey)
            ->where('limit_key', $limitKey)
            ->value('limit_value');
    }

    protected function tenantId(): string
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        return (string) ($tenant?->id ?? '');
    }
}
