<?php

namespace App\Services\Core\Documents\DTO;

class DocumentRenderRequest
{
    public function __construct(
        public string $documentType,
        public string $template,
        public array $data,
        public string $language,
        public string $direction,
        public array $layout = [],
        public array $metadata = []
    ) {
    }
}
