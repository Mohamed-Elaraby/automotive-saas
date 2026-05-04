<?php

namespace App\Services\Core\Documents;

use App\Services\Core\Documents\DTO\DocumentRenderRequest;
use App\Services\Core\Documents\DTO\DocumentRenderResult;

interface DocumentRendererInterface
{
    public function render(DocumentRenderRequest $request): DocumentRenderResult;
}
