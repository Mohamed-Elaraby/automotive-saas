<?php

namespace App\Services\Core\Documents;

use App\Models\Core\Documents\GeneratedDocument;
use Illuminate\Support\Str;

class DocumentVerificationService
{
    public function token(): string
    {
        do {
            $token = Str::random(48);
        } while (GeneratedDocument::query()->where('verified_token', $token)->exists());

        return $token;
    }

    public function verify(string $token): ?GeneratedDocument
    {
        return GeneratedDocument::query()
            ->with(['branch', 'snapshot'])
            ->where('verified_token', $token)
            ->first();
    }
}
