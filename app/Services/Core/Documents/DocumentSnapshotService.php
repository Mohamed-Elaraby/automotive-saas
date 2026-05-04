<?php

namespace App\Services\Core\Documents;

use App\Models\Core\Documents\GeneratedDocument;

class DocumentSnapshotService
{
    public function store(GeneratedDocument $document, array $snapshot): void
    {
        $document->snapshot()->create([
            'snapshot' => $snapshot,
        ]);
    }
}
