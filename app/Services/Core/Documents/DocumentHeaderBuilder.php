<?php

namespace App\Services\Core\Documents;

class DocumentHeaderBuilder
{
    public function build(array $data): string
    {
        return view(config('documents.templates.header'), $data)->render();
    }
}
