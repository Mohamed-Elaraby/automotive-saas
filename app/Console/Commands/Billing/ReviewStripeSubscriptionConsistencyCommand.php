<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\StripeSubscriptionConsistencyReviewService;
use Illuminate\Console\Command;
use Throwable;

class ReviewStripeSubscriptionConsistencyCommand extends Command
{
    protected $signature = 'billing:review-stripe-consistency
                            {--sync : Run the existing Stripe sync flow before evaluating the final state}
                            {--only-issues : Display only rows that still need review after evaluation}
                            {--format=table : Output format: table, json, or csv}
                            {--output= : Optional file path to write the rendered output}
                            {--tenant= : Review only one tenant_id}
                            {--subscription= : Review only one local subscription id}
                            {--limit=100 : Maximum number of local subscriptions to review}';

    protected $description = 'Review Stripe-linked local subscription consistency, product mirrors, and invoice linkage signals';

    public function __construct(
        protected StripeSubscriptionConsistencyReviewService $stripeSubscriptionConsistencyReviewService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = strtolower(trim((string) $this->option('format')));
        if (! in_array($format, ['table', 'json', 'csv'], true)) {
            $this->error('Invalid format. Allowed values: table, json, csv.');
            return self::FAILURE;
        }

        try {
            $reviewRows = $this->stripeSubscriptionConsistencyReviewService->review(
                (bool) $this->option('sync'),
                $this->option('tenant') ?: null,
                $this->option('subscription') ? (int) $this->option('subscription') : null,
                (int) $this->option('limit')
            );
        } catch (Throwable $e) {
            $this->error('Unable to review Stripe subscription consistency: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($reviewRows->isEmpty()) {
            $this->warn('No Stripe-linked subscriptions were found for review.');
            return self::SUCCESS;
        }

        $needsReview = $reviewRows->where('result', 'NEEDS_REVIEW')->count();
        $synced = $reviewRows->where('sync_state', 'SYNCED')->count();
        $failedSync = $reviewRows->where('sync_state', 'FAILED')->count();
        $onlyIssues = (bool) $this->option('only-issues');

        $displayRows = $onlyIssues
            ? $reviewRows->where('result', 'NEEDS_REVIEW')->values()
            : $reviewRows->values();

        $headers = [
            'Sub ID',
            'Tenant',
            'Status',
            'Plan',
            'Stripe Sub ID',
            'Sync',
            'Mirror',
            'Invoices',
            'Mixed Cust Inv',
            'Issues After',
            'Result',
        ];

        $rows = $displayRows->map(function (array $row) {
                return [
                    'Sub ID' => $row['subscription_id'],
                    'Tenant' => $row['tenant_id'],
                    'Status' => $row['status_before'] . ' -> ' . $row['status_after'],
                    'Plan' => $row['plan_before'] . ' -> ' . $row['plan_after'],
                    'Stripe Sub ID' => ($row['gateway_subscription_id_before'] ?: '-') . ' -> ' . ($row['gateway_subscription_id_after'] ?: '-'),
                    'Sync' => $row['sync_state'],
                    'Mirror' => $row['mirror_status'],
                    'Invoices' => $row['subscription_invoice_count'],
                    'Mixed Cust Inv' => $row['mixed_customer_invoice_count'],
                    'Issues After' => $row['issues_after'],
                    'Result' => $row['result'],
                ];
            })->all();

        if (empty($rows) && $onlyIssues) {
            $this->info('No Stripe subscription issues remain after filtering.');
        } else {
            $renderedOutput = $this->renderRows($headers, $rows, $format);

            if ($renderedOutput !== '') {
                $this->line($renderedOutput);
            }
        }

        $this->line(sprintf(
            'Summary: reviewed=%d, needs_review=%d, synced=%d, sync_failed=%d',
            $reviewRows->count(),
            $needsReview,
            $synced,
            $failedSync
        ));

        $outputPath = trim((string) $this->option('output'));
        if ($outputPath !== '') {
            $written = @file_put_contents($outputPath, $this->renderFilePayload($headers, $rows, $reviewRows->count(), $needsReview, $synced, $failedSync, $format));

            if ($written === false) {
                $this->error('Failed to write review output to: ' . $outputPath);
                return self::FAILURE;
            }

            $this->info('Wrote review output to: ' . $outputPath);
        }

        if ($needsReview > 0) {
            $this->error("Detected {$needsReview} Stripe subscription records that still need review.");
            return self::FAILURE;
        }

        $this->info('Stripe subscription consistency review completed with no remaining issues.');
        return self::SUCCESS;
    }

    protected function renderRows(array $headers, array $rows, string $format): string
    {
        if ($format === 'json') {
            return json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        if ($format === 'csv') {
            return $this->toCsv($headers, $rows);
        }

        $buffer = fopen('php://temp', 'r+');
        $table = new \Symfony\Component\Console\Helper\Table($this->output);
        $table->setHeaders($headers)->setRows($rows);
        $table->render();

        rewind($buffer);

        return '';
    }

    protected function renderFilePayload(
        array $headers,
        array $rows,
        int $reviewed,
        int $needsReview,
        int $synced,
        int $failedSync,
        string $format
    ): string {
        if ($format === 'json') {
            return (string) json_encode([
                'summary' => [
                    'reviewed' => $reviewed,
                    'needs_review' => $needsReview,
                    'synced' => $synced,
                    'sync_failed' => $failedSync,
                ],
                'rows' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return $this->toCsv($headers, $rows);
    }

    protected function toCsv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($header) => $row[$header] ?? '', $headers));
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }
}
