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
        $candidates = $this->extractCandidates($text);
        $best = $candidates[0] ?? null;

        return [
            'ocr_available' => $text !== null,
            'raw_text' => $text,
            'detected_vin' => $best,
            'normalized_vin' => $best,
            'confidence_score' => $best ? $this->confidenceFor($best, $text ?? '') : null,
            'candidates' => $candidates,
            'vehicle_matches' => $best ? $this->searchVehicles($best)->values()->all() : [],
        ];
    }

    public function analyzeUploadedFile(UploadedFile $file): array
    {
        $text = $this->runTesseractPath($file->getRealPath());
        $candidates = $this->extractCandidates($text);
        $best = $candidates[0] ?? null;

        return [
            'ocr_available' => $text !== null,
            'raw_text' => $text,
            'detected_vin' => $best,
            'normalized_vin' => $best,
            'confidence_score' => $best ? $this->confidenceFor($best, $text ?? '') : null,
            'candidates' => $candidates,
            'vehicle_matches' => [],
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
        $score = strlen($candidate) === 17 ? 85 : 60;

        if (preg_match('/VIN|CHASSIS|FRAME/i', $rawText)) {
            $score += 5;
        }

        return min(95, $score);
    }
}
