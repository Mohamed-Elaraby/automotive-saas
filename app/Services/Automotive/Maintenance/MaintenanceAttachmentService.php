<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceAttachment;
use App\Services\Tenancy\AttachmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MaintenanceAttachmentService
{
    public function __construct(
        protected AttachmentService $attachments
    ) {
    }

    public function store(Model $attachable, UploadedFile $file, array $data): MaintenanceAttachment
    {
        $tenantId = (string) (tenant('id') ?: 'central');
        $category = $data['category'] ?? 'other';
        $attachment = $this->attachments->storeAttachment($attachable, $file, 'automotive', [
            'branch_id' => $data['branch_id'] ?? null,
            'uploaded_by' => $data['uploaded_by'] ?? null,
            'disk' => 'public',
            'visibility' => 'private',
            'metadata' => [
                'legacy_table' => 'maintenance_attachments',
                'category' => $category,
                'captured_at' => $data['captured_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ],
        ]);

        return MaintenanceAttachment::query()->create([
            'tenant_id' => $tenantId,
            'branch_id' => $data['branch_id'] ?? null,
            'attachable_type' => $attachable::class,
            'attachable_id' => $attachable->getKey(),
            'category' => $category,
            'file_disk' => $attachment->disk,
            'file_path' => $attachment->storage_path,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->file_size,
            'uploaded_by' => $data['uploaded_by'] ?? null,
            'captured_at' => $data['captured_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function publicUrl(MaintenanceAttachment $attachment): string
    {
        return Storage::disk($attachment->file_disk ?: 'public')->url($attachment->file_path);
    }

    public function absolutePath(MaintenanceAttachment $attachment): ?string
    {
        $disk = Storage::disk($attachment->file_disk ?: 'public');

        if (! method_exists($disk, 'path')) {
            return null;
        }

        return $disk->path($attachment->file_path);
    }
}
