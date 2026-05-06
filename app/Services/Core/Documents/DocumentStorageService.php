<?php

namespace App\Services\Core\Documents;

use Illuminate\Support\Facades\Storage;

class DocumentStorageService
{
    public function put(string $disk, string $path, string $binary): void
    {
        Storage::disk($disk)->put($path, $binary);
    }

    public function path(array $data): string
    {
        $tenantId = $data['tenant_id'] ?: 'central';
        $year = now()->format('Y');
        $month = now()->format('m');

        return sprintf(
            'tenants/%s/documents/%s/%s/%s/%s/%s/%s-v%d.pdf',
            $tenantId,
            $data['product_key'] ?? $data['product_code'],
            $data['module_code'],
            $data['document_type'],
            $year,
            $month,
            $data['document_number'],
            $data['version']
        );
    }
}
