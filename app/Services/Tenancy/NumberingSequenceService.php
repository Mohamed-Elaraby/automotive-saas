<?php

namespace App\Services\Tenancy;

use App\Models\Branch;
use App\Models\NumberingSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NumberingSequenceService
{
    public function next(
        string $productKey,
        string $documentType,
        ?int $branchId = null,
        ?int $year = null,
        array $options = []
    ): string {
        $scope = $this->scope($productKey, $documentType, $branchId, $year, $options);

        return DB::transaction(function () use ($scope, $options): string {
            $sequence = NumberingSequence::query()
                ->forScope($scope)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = NumberingSequence::query()->create([
                    'tenant_id' => $scope['tenant_id'],
                    'product_key' => $scope['product_key'],
                    'document_type' => $scope['document_type'],
                    'branch_id' => $scope['branch_id'],
                    'year' => $scope['year'],
                    'prefix' => $options['prefix'] ?? $this->defaultPrefix($scope),
                    'next_number' => (int) ($options['starts_at'] ?? 1),
                    'padding' => (int) ($options['padding'] ?? 4),
                    'reset_strategy' => $scope['reset_strategy'],
                    'metadata' => $options['metadata'] ?? null,
                ]);
            }

            $number = (int) $sequence->next_number;
            $sequence->forceFill([
                'next_number' => $number + 1,
            ])->save();

            return $this->format($sequence->prefix, $number, (int) $sequence->padding);
        });
    }

    public function previewPrefix(string $productKey, string $documentType, ?int $branchId = null, ?int $year = null, array $options = []): string
    {
        return $this->defaultPrefix($this->scope($productKey, $documentType, $branchId, $year, $options));
    }

    protected function scope(string $productKey, string $documentType, ?int $branchId, ?int $year, array $options): array
    {
        $productKey = trim($productKey);
        $documentType = trim($documentType);
        $resetStrategy = $options['reset_strategy'] ?? 'yearly';

        if ($productKey === '' || $documentType === '') {
            throw new InvalidArgumentException('Product key and document type are required for numbering sequences.');
        }

        $year = match ($resetStrategy) {
            'never' => null,
            default => $year ?: (int) now()->format('Y'),
        };

        return [
            'tenant_id' => $options['tenant_id'] ?? $this->tenantId(),
            'product_key' => $productKey,
            'document_type' => $documentType,
            'branch_id' => $branchId,
            'year' => $year,
            'reset_strategy' => $resetStrategy,
        ];
    }

    protected function defaultPrefix(array $scope): string
    {
        $documentPrefix = $this->documentPrefix($scope['document_type']);
        $branchCode = $scope['branch_id'] ? $this->branchCode((int) $scope['branch_id']) : null;
        $parts = array_filter([$documentPrefix, $branchCode, $scope['year']]);

        return implode('-', $parts);
    }

    protected function documentPrefix(string $documentType): string
    {
        return match ($documentType) {
            'work_order' => 'WO',
            'job_card' => 'JOB',
            'quotation', 'estimate' => 'QUO',
            'invoice', 'tax_invoice' => 'INV',
            'receipt' => 'RCPT',
            'purchase_order' => 'PO',
            'stock_transfer' => 'ST',
            'delivery_note' => 'DN',
            'payment_voucher' => 'PV',
            'journal_voucher' => 'JV',
            'statement_of_account' => 'SOA',
            default => strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $documentType), 0, 8)) ?: 'DOC',
        };
    }

    protected function branchCode(int $branchId): ?string
    {
        $code = Branch::query()->whereKey($branchId)->value('code');

        return $code ? strtoupper((string) $code) : null;
    }

    protected function format(string $prefix, int $number, int $padding): string
    {
        return sprintf('%s-%s', $prefix, str_pad((string) $number, $padding, '0', STR_PAD_LEFT));
    }

    protected function tenantId(): ?string
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        return $tenant?->id ? (string) $tenant->id : null;
    }
}
