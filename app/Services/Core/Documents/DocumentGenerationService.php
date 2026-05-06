<?php

namespace App\Services\Core\Documents;

use App\Models\Core\Documents\GeneratedDocument;
use App\Services\Core\Documents\DTO\DocumentRenderRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class DocumentGenerationService
{
    public function __construct(
        protected DocumentRendererInterface $renderer,
        protected DocumentNumberService $numbers,
        protected DocumentStorageService $storage,
        protected DocumentSnapshotService $snapshots,
        protected DocumentVerificationService $verification
    ) {
    }

    public function generate(string $documentType, array $snapshot, array $options = []): GeneratedDocument
    {
        $type = (array) config('documents.types.' . $documentType);
        abort_if($type === [], 422, 'Unknown document type.');

        $documentable = $options['documentable'] ?? null;
        $documentableType = $documentable instanceof Model ? $documentable::class : ($options['documentable_type'] ?? null);
        $documentableId = $documentable instanceof Model ? (int) $documentable->getKey() : ($options['documentable_id'] ?? null);
        $language = $options['language'] ?? config('documents.default_language', 'en');
        $direction = $options['direction'] ?? ($language === 'ar' ? 'rtl' : config('documents.default_direction', 'ltr'));
        $disk = $options['disk'] ?? config('documents.disk', 'local');
        $version = $this->numbers->nextVersion($documentType, $documentableType, $documentableId);
        $productKey = (string) ($options['product_key'] ?? $type['product_key'] ?? $type['product_code']);
        $branchId = $options['branch_id'] ?? data_get($snapshot, 'branch.id');
        $documentNumber = $options['document_number']
            ?? $this->numbers->existingNumber($documentType, $documentableType, $documentableId)
            ?? $this->numbers->nextScoped($productKey, (string) ($type['document_key'] ?? $documentType), $branchId ? (int) $branchId : null, null, [
                'prefix' => $options['prefix'] ?? $type['prefix'],
                'padding' => (int) ($options['padding'] ?? $type['padding'] ?? 4),
                'reset_strategy' => $options['reset_strategy'] ?? $type['reset_strategy'] ?? 'yearly',
            ]);
        $title = $options['title'] ?? $type['title'];
        $tenantId = (string) ($options['tenant_id'] ?? tenant('id') ?? '');

        return DB::transaction(function () use ($documentType, $snapshot, $options, $type, $documentableType, $documentableId, $language, $direction, $disk, $version, $documentNumber, $title, $tenantId, $productKey, $branchId) {
            $token = $this->verification->token();

            $document = GeneratedDocument::query()->create([
                'tenant_id' => $tenantId ?: null,
                'branch_id' => $branchId,
                'product_key' => $productKey,
                'product_code' => $type['product_code'],
                'module_code' => $type['module_code'],
                'documentable_type' => $documentableType,
                'documentable_id' => $documentableId,
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'document_title' => $title,
                'language' => $language,
                'direction' => $direction,
                'file_disk' => $disk,
                'version' => $version,
                'status' => 'processing',
                'generated_by' => $options['generated_by'] ?? null,
                'verified_token' => $token,
                'metadata' => $options['metadata'] ?? [
                    'document_key' => $type['document_key'] ?? $documentType,
                    'template' => $options['template'] ?? $type['template'],
                ],
            ]);

            $verifyUrl = $this->verificationUrl($token);
            $renderData = [
                'snapshot' => $snapshot,
                'document' => $document->toArray() + [
                    'verify_url' => $verifyUrl,
                    'qr_enabled' => (bool) ($type['qr_enabled'] ?? false),
                ],
                'company' => $options['company'] ?? [],
                'branch' => $snapshot['branch'] ?? [],
            ];

            $result = $this->renderer->render(new DocumentRenderRequest(
                documentType: $documentType,
                template: $options['template'] ?? $type['template'],
                data: $renderData,
                language: $language,
                direction: $direction,
                layout: $options['layout'] ?? []
            ));

            $path = $this->storage->path([
                'tenant_id' => $tenantId ?: 'central',
                'product_key' => $productKey,
                'product_code' => $type['product_code'],
                'module_code' => $type['module_code'],
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'version' => $version,
            ]);

            $this->storage->put($disk, $path, $result->binary);

            $document->forceFill([
                'file_path' => $path,
                'file_name' => basename($path),
                'mime_type' => $result->mimeType,
                'file_size' => $result->size(),
                'checksum' => $result->checksum(),
                'status' => 'completed',
                'generated_at' => now(),
            ])->save();

            if ((bool) ($type['snapshot_required'] ?? true)) {
                $this->snapshots->store($document, $snapshot);
            }

            return $document->fresh(['snapshot', 'branch']);
        });
    }

    protected function verificationUrl(string $token): string
    {
        if (Route::has('documents.verify')) {
            return route('documents.verify', $token);
        }

        return url('/documents/verify/' . $token);
    }
}
