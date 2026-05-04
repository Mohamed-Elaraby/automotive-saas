<?php

namespace App\Services\Core\Documents;

class DocumentFooterBuilder
{
    public function build(array $data): string
    {
        return view(config('documents.templates.footer'), $data)->render();
    }
}
