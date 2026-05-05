<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceAttachment;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

class VinOcrService
{
    public function __construct(protected MaintenanceAttachmentService $attachments)
    {
    }

    public function analyze(MaintenanceAttachment $attachment): array
    {
        $text = $this->runTesseract($attachment);
        $candidates = $this->extractCandidates($text);
        $best = $candidates[0] ?? null;

        return [
            'ocr_available' => $text !== null,
            'raw_text' => $text,
            'detected_vin' => $best,
            'confidence_score' => $best ? $this->confidenceFor($best, $text ?? '') : null,
            'candidates' => $candidates,
            'vehicle_matches' => $best ? $this->searchVehicles($best)->values()->all() : [],
        ];
    }

    public function searchVehicles(string $vin): Collection
    {
        $normalized = $this->normalizeVin($vin);

        if ($normalized === '') {
            return collect();
        }

        return Vehicle::query()
            ->with('customer')
            ->where('vin', 'like', '%' . $normalized . '%')
            ->orWhere('vin', 'like', '%' . str_replace(['0', '1', '5', '8', '2'], ['O', 'I', 'S', 'B', 'Z'], $normalized) . '%')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (Vehicle $vehicle): array => [
                'id' => $vehicle->id,
                'vehicle_number' => $vehicle->vehicle_number,
                'vin' => $vehicle->vin,
                'plate_number' => $vehicle->plate_number,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'customer_name' => $vehicle->customer?->name,
            ]);
    }

    protected function runTesseract(MaintenanceAttachment $attachment): ?string
    {
        $path = $this->attachments->absolutePath($attachment);

        if (! $path || ! is_file($path) || ! $this->tesseractAvailable()) {
            return null;
        }

        $process = new Process(['tesseract', $path, 'stdout', '--psm', '7']);
        $process->setTimeout(20);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $text = trim($process->getOutput());

        return $text !== '' ? $text : null;
    }

    protected function tesseractAvailable(): bool
    {
        $process = new Process(['which', 'tesseract']);
        $process->setTimeout(3);
        $process->run();

        return $process->isSuccessful();
    }

    protected function extractCandidates(?string $text): array
    {
        if (! $text) {
            return [];
        }

        $normalized = $this->normalizeVin($text);
        preg_match_all('/[A-HJ-NPR-Z0-9]{10,20}/', $normalized, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $candidate): string => $this->normalizeVin($candidate))
            ->filter(fn (string $candidate): bool => strlen($candidate) >= 10)
            ->sortByDesc(fn (string $candidate): int => strlen($candidate) === 17 ? 100 : strlen($candidate))
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeVin(string $value): string
    {
        $value = strtoupper($value);
        $value = str_replace([' ', '-', '_', '.', ':', "\n", "\r", "\t"], '', $value);
        $value = strtr($value, [
            'O' => '0',
            'I' => '1',
            'S' => '5',
            'B' => '8',
            'Z' => '2',
        ]);

        return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
    }

    protected function confidenceFor(string $candidate, string $rawText): int
    {
        $score = strlen($candidate) === 17 ? 85 : 60;

        if (preg_match('/VIN|CHASSIS|FRAME/i', $rawText)) {
            $score += 5;
        }

        return min(95, $score);
    }
}
