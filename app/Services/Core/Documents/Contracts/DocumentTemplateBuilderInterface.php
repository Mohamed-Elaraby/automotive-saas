<?php

namespace App\Services\Core\Documents\Contracts;

interface DocumentTemplateBuilderInterface
{
    public function build(array $snapshot): array;
}
