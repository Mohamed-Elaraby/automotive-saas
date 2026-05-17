<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceAttachment;
use App\Models\Vehicle;
use App\Models\User;
use App\Services\Tenancy\BranchScopeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

class VinOcrService
{
    public function __construct(
        protected MaintenanceAttachmentService $attachments,
        protected BranchScopeService $branchScope
    )
    {
    }

    public function analyze(MaintenanceAttachment $attachment): array
    {
        $text = $this->runTesseract($attachment);

        return $this->analyzeText($text, true);
    }

    public function analyzeUploadedFile(UploadedFile $file): array
    {
        $text = $this->runTesseractPath($file->getRealPath());

        return $this->analyzeText($text, false);
    }

    public function analyzeText(?string $text, bool $includeVehicleMatches = false): array
    {
        $candidates = $this->extractCandidates($text);
        $best = $candidates[0] ?? null;
        $ocrStatus = match (true) {
            $text === null => 'unavailable',
            $best !== null => 'detected',
            default => 'not_detected',
        };
        $confidence = $best ? $this->confidenceFor($best, $text ?? '') : null;

        return [
            'ocr_available' => $text !== null,
            'ocr_status' => $ocrStatus,
            'raw_text' => $text,
            'detected_vin' => $best,
            'extracted_vin' => $best,
            'normalized_vin' => $best,
            'confidence_score' => $confidence,
            'vin_ocr_confidence' => $confidence,
            'candidates' => $candidates,
            'vehicle_matches' => $includeVehicleMatches && $best ? $this->searchVehicles($best)->values()->all() : [],
        ];
    }

    public function searchVehicles(string $vin): Collection
    {
        $normalized = $this->normalizeVin($vin);

        if ($normalized === '') {
            return collect();
        }

        $variants = $this->vinSearchVariants($normalized);

        return Vehicle::query()
            ->with('customer')
            ->where(function ($query) use ($variants): void {
                foreach ($variants as $index => $variant) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}('vin', 'like', '%' . $variant . '%');
                }
            })
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

    public function searchVehicleSummary(string $vin, ?User $user = null): array
    {
        $normalized = $this->normalizeVin($vin);

        if ($normalized === '') {
            return [
                'found' => false,
                'message' => 'No vehicle found for this VIN.',
                'normalized_vin' => $normalized,
            ];
        }

        $variants = $this->vinSearchVariants($normalized);

        $vehicle = Vehicle::query()
            ->with([
                'customer',
                'checkIns' => fn ($query) => $query->with(['branch', 'workOrder'])->latest('checked_in_at')->latest('id'),
                'workOrders' => fn ($query) => $query->latest('opened_at')->latest('id'),
            ])
            ->where(function ($query) use ($variants): void {
                foreach ($variants as $index => $variant) {
                    if ($index === 0) {
                        $query->where('vin', $variant)
                            ->orWhere('vin', 'like', '%' . $variant . '%');

                        continue;
                    }

                    $query->orWhere('vin', $variant)
                        ->orWhere('vin', 'like', '%' . $variant . '%');
                }
            })
            ->latest('id')
            ->first();

        if (! $vehicle) {
            return [
                'found' => false,
                'message' => 'No vehicle found for this VIN.',
                'normalized_vin' => $normalized,
            ];
        }

        $lastCheckIn = $vehicle->checkIns->first();
        $lastWorkOrder = $vehicle->workOrders->first() ?: $lastCheckIn?->workOrder;
        $lastBranchId = $lastCheckIn?->branch_id ?: $lastWorkOrder?->branch_id;
        $canViewBranchHistory = ! $user || ! $lastBranchId
            ? true
            : $this->branchScope->canAccessBranch($user, 'automotive_service', (int) $lastBranchId);

        return [
            'found' => true,
            'normalized_vin' => $normalized,
            'restricted_history' => ! $canViewBranchHistory,
            'vehicle' => [
                'id' => $vehicle->id,
                'vehicle_number' => $vehicle->vehicle_number,
                'vin' => $vehicle->vin,
                'plate_number' => $vehicle->plate_number,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'trim' => $vehicle->trim,
                'color' => $vehicle->color,
                'odometer' => $vehicle->odometer,
                'fuel_type' => $vehicle->fuel_type,
                'transmission' => $vehicle->transmission,
            ],
            'customer' => [
                'id' => $vehicle->customer?->id,
                'name' => $vehicle->customer?->name,
                'phone' => $canViewBranchHistory ? $vehicle->customer?->phone : null,
                'email' => $canViewBranchHistory ? $vehicle->customer?->email : null,
                'customer_type' => $vehicle->customer?->customer_type,
                'company_name' => $vehicle->customer?->company_name,
            ],
            'branch' => $canViewBranchHistory ? [
                'id' => $lastCheckIn?->branch?->id,
                'name' => $lastCheckIn?->branch?->name,
            ] : null,
            'history' => [
                'last_check_in_at' => $canViewBranchHistory ? $lastCheckIn?->checked_in_at?->toDateTimeString() : null,
                'last_check_in_number' => $canViewBranchHistory ? $lastCheckIn?->check_in_number : null,
                'last_work_order_number' => $canViewBranchHistory ? $lastWorkOrder?->work_order_number : null,
                'last_status' => $canViewBranchHistory ? ($lastWorkOrder?->status ?: $lastCheckIn?->status) : null,
                'total_visits' => $canViewBranchHistory ? $vehicle->checkIns->count() : null,
                'open_work_order' => $canViewBranchHistory && $vehicle->workOrders->contains(fn ($workOrder): bool => in_array($workOrder->status, ['open', 'in_progress'], true)),
            ],
            'actions' => [
                'use_for_check_in' => true,
                'profile_url' => route('automotive.admin.maintenance.vehicles.profile', $vehicle),
                'start_check_in_url' => route('automotive.admin.maintenance.check-ins.create', ['vehicle_id' => $vehicle->id]),
            ],
        ];
    }

    public function normalize(string $vin): string
    {
        return $this->normalizeVin($vin);
    }

    protected function runTesseract(MaintenanceAttachment $attachment): ?string
    {
        return $this->runTesseractPath($this->attachments->absolutePath($attachment));
    }

    protected function runTesseractPath(?string $path): ?string
    {
        if (! $path || ! is_file($path) || ! $this->tesseractAvailable()) {
            return null;
        }

        $paths = [$path];
        $temporaryPath = $this->preprocessImageForOcr($path);
        if ($temporaryPath) {
            $paths[] = $temporaryPath;
        }

        $outputs = [];

        try {
            foreach ($paths as $ocrPath) {
                foreach ([7, 6, 11, 13] as $psm) {
                    $process = new Process([
                        'tesseract',
                        $ocrPath,
                        'stdout',
                        '--psm',
                        (string) $psm,
                        '-c',
                        'tessedit_char_whitelist=ABCDEFGHJKLMNPRSTUVWXYZ0123456789',
                    ]);
                    $process->setTimeout(20);
                    $process->run();

                    if ($process->isSuccessful()) {
                        $outputs[] = trim($process->getOutput());
                    }
                }
            }
        } finally {
            if ($temporaryPath && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        $text = trim(implode("\n", array_filter($outputs)));

        return $text;
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
        preg_match_all('/[A-HJ-NPR-Z0-9]{17}/', $normalized, $standardMatches);
        preg_match_all('/[A-Z0-9]{12,25}/', $normalized, $flexibleMatches);

        $cleanedCandidate = strlen($normalized) >= 10 && strlen($normalized) <= 25 ? [$normalized] : [];

        return collect([
                ...($standardMatches[0] ?? []),
                ...($flexibleMatches[0] ?? []),
                ...$cleanedCandidate,
            ])
            ->map(fn (string $candidate): string => $this->normalizeVin($candidate))
            ->filter(fn (string $candidate): bool => strlen($candidate) >= 10)
            ->sortByDesc(function (string $candidate): int {
                if (strlen($candidate) === 17 && preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $candidate)) {
                    return 300;
                }

                if (strlen($candidate) >= 12 && strlen($candidate) <= 25) {
                    return 200 + strlen($candidate);
                }

                return strlen($candidate);
            })
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeVin(string $value): string
    {
        $value = strtoupper($value);
        $value = str_replace([' ', '-', '_', '.', ':', "\n", "\r", "\t"], '', $value);

        return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
    }

    protected function vinSearchVariants(string $normalized): array
    {
        $digitToLetter = strtr($normalized, [
            '0' => 'O',
            '1' => 'I',
            '5' => 'S',
            '8' => 'B',
            '2' => 'Z',
        ]);

        $letterToDigit = strtr($normalized, [
            'O' => '0',
            'I' => '1',
            'S' => '5',
            'B' => '8',
            'Z' => '2',
        ]);

        return collect([$normalized, $digitToLetter, $letterToDigit])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function confidenceFor(string $candidate, string $rawText): int
    {
        $score = strlen($candidate) === 17 && preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $candidate) ? 88 : 70;

        if (strlen($candidate) < 12) {
            $score = 45;
        }

        if (preg_match('/VIN|CHASSIS|FRAME/i', $rawText)) {
            $score += 5;
        }

        return min(95, $score);
    }

    protected function preprocessImageForOcr(string $path): ?string
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $image = @imagecreatefromstring($contents);
        if (! $image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = $width < 1400 ? 2 : 1;
        $targetWidth = max($width * $scale, $width);
        $targetHeight = max($height * $scale, $height);
        $processed = imagecreatetruecolor($targetWidth, $targetHeight);

        imagecopyresampled($processed, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagefilter($processed, IMG_FILTER_GRAYSCALE);
        imagefilter($processed, IMG_FILTER_CONTRAST, -25);
        imagefilter($processed, IMG_FILTER_SMOOTH, -4);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'vin-ocr-');
        if (! $temporaryPath) {
            imagedestroy($image);
            imagedestroy($processed);

            return null;
        }

        $jpegPath = $temporaryPath . '.jpg';
        @unlink($temporaryPath);

        imagejpeg($processed, $jpegPath, 95);
        imagedestroy($image);
        imagedestroy($processed);

        return is_file($jpegPath) ? $jpegPath : null;
    }
}
