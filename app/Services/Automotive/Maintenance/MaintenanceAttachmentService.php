<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MaintenanceAttachmentService
{
    public function store(Model $attachable, UploadedFile $file, array $data): MaintenanceAttachment
    {
        $tenantId = (string) (tenant('id') ?: 'central');
        $category = $data['category'] ?? 'other';
        $directory = 'tenants/' . $tenantId . '/maintenance/attachments/' . $attachable->getTable() . '/' . $attachable->getKey();
        $path = $file->store($directory, 'public');

        return MaintenanceAttachment::query()->create([
            'tenant_id' => $tenantId,
            'branch_id' => $data['branch_id'] ?? null,
            'attachable_type' => $attachable::class,
            'attachable_id' => $attachable->getKey(),
            'category' => $category,
            'file_disk' => 'public',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => $data['uploaded_by'] ?? null,
            'captured_at' => $data['captured_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function publicUrl(MaintenanceAttachment $attachment): string
    {
        return Storage::disk($attachment->file_disk ?: 'public')->url($attachment->file_path);
    }
}
