<?php

namespace App\Services\Core\Documents\Contracts;

interface DocumentableInterface
{
    public function documentSnapshot(): array;
}
