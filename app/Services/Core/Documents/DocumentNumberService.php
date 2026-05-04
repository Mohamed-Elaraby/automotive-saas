<?php

namespace App\Services\Core\Documents;

use App\Models\Core\Documents\GeneratedDocument;

class DocumentNumberService
{
    public function next(string $documentType, string $prefix): string
    {
        $last = GeneratedDocument::query()
            ->where('document_type', $documentType)
            ->where('document_number', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->value('document_number');

        $next = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%06d', $prefix, $next);
    }

    public function nextVersion(string $documentType, ?string $documentableType, ?int $documentableId): int
    {
        if (! $documentableType || ! $documentableId) {
            return 1;
        }

        return ((int) GeneratedDocument::query()
            ->where('document_type', $documentType)
            ->where('documentable_type', $documentableType)
            ->where('documentable_id', $documentableId)
            ->max('version')) + 1;
    }

    public function existingNumber(string $documentType, ?string $documentableType, ?int $documentableId): ?string
    {
        if (! $documentableType || ! $documentableId) {
            return null;
        }

        return GeneratedDocument::query()
            ->where('document_type', $documentType)
            ->where('documentable_type', $documentableType)
            ->where('documentable_id', $documentableId)
            ->orderByDesc('version')
            ->value('document_number');
    }
}
