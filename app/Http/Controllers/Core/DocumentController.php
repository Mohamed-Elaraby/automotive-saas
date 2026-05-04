<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\Documents\GeneratedDocument;
use App\Services\Core\Documents\DocumentVerificationService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function download(GeneratedDocument $document): Response
    {
        abort_unless($document->status === 'completed' && $document->file_path, 404);

        return response(Storage::disk($document->file_disk)->get($document->file_path), 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'attachment; filename="' . $document->file_name . '"',
        ]);
    }

    public function preview(GeneratedDocument $document): Response
    {
        abort_unless($document->status === 'completed' && $document->file_path, 404);

        return response(Storage::disk($document->file_disk)->get($document->file_path), 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . $document->file_name . '"',
        ]);
    }

    public function verify(string $token, DocumentVerificationService $verification): View
    {
        return view('core.documents.verify', [
            'document' => $verification->verify($token),
            'verifiedAt' => now(),
        ]);
    }
}
