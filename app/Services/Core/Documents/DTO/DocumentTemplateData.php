<?php

namespace App\Services\Core\Documents\DTO;

class DocumentTemplateData
{
    public function __construct(
        public array $snapshot,
        public array $document,
        public array $company = [],
        public array $branch = []
    ) {
    }
}
