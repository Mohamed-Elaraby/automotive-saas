<?php

namespace App\Services\Core\Documents;

class DocumentLayoutManager
{
    public function resolve(array $overrides = []): array
    {
        return array_replace((array) config('documents.layout', []), $overrides);
    }
}
