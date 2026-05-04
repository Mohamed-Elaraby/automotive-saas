<?php

namespace App\Services\Core\Documents\DTO;

class DocumentRenderResult
{
    public function __construct(
        public string $binary,
        public string $mimeType = 'application/pdf'
    ) {
    }

    public function size(): int
    {
        return strlen($this->binary);
    }

    public function checksum(): string
    {
        return hash('sha256', $this->binary);
    }
}
