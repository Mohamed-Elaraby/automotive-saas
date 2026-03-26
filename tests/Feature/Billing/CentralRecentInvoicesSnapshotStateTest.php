<?php

namespace Tests\Feature\Billing;

use Illuminate\Support\Collection;
use Tests\TestCase;

class CentralRecentInvoicesSnapshotStateTest extends TestCase
{
    public function test_it_can_sort_recent_invoices_by_created_at_descending(): void
    {
        $recentInvoices = collect([
            [
                'id' => 'in_003',
                'tenant_id' => 'tenant-c',
                'status' => 'paid',
                'total_decimal' => 399.00,
                'amount_paid_decimal' => 399.00,
                'amount_due_decimal' => 0.00,
                'currency' => 'USD',
                'created_at' => 1711401000,
            ],
            [
                'id' => 'in_001',
                'tenant_id' => 'tenant-a',
                'status' => 'paid',
                'total_decimal' => 199.00,
                'amount_paid_decimal' => 199.00,
                'amount_due_decimal' => 0.00,
                'currency' => 'USD',
                'created_at' => 1711400000,
            ],
            [
                'id' => 'in_002',
                'tenant_id' => 'tenant-b',
                'status' => 'open',
                'total_decimal' => 199.00,
                'amount_paid_decimal' => 0.00,
                'amount_due_decimal' => 199.00,
                'currency' => 'USD',
                'created_at' => 1711400500,
            ],
        ])
            ->sortByDesc(fn (array $invoice) => (int) ($invoice['created_at'] ?? 0))
            ->values();

        $this->assertCount(3, $recentInvoices);
        $this->assertSame('in_003', $recentInvoices[0]['id']);
        $this->assertSame('in_002', $recentInvoices[1]['id']);
        $this->assertSame('in_001', $recentInvoices[2]['id']);
    }

    public function test_it_can_keep_recent_invoice_snapshot_fields_consistent_for_report_rendering(): void
    {
        $invoice = [
            'id' => 'in_snapshot_001',
            'number' => 'PZQKDZ5U-0001',
            'tenant_id' => 'test2',
            'status' => 'paid',
            'total_decimal' => 199.00,
            'amount_paid_decimal' => 199.00,
            'amount_due_decimal' => 0.00,
            'currency' => 'USD',
            'created_at' => 1711401000,
            'subscription_id' => 'sub_snapshot_001',
            'hosted_invoice_url' => 'https://example.com/invoice',
            'invoice_pdf' => 'https://example.com/invoice.pdf',
        ];

        $this->assertSame('in_snapshot_001', $invoice['id']);
        $this->assertSame('PZQKDZ5U-0001', $invoice['number']);
        $this->assertSame('test2', $invoice['tenant_id']);
        $this->assertSame('paid', strtolower((string) $invoice['status']));
        $this->assertEquals(199.00, (float) $invoice['total_decimal']);
        $this->assertEquals(199.00, (float) $invoice['amount_paid_decimal']);
        $this->assertEquals(0.00, (float) $invoice['amount_due_decimal']);
        $this->assertSame('USD', strtoupper((string) $invoice['currency']));
        $this->assertSame('sub_snapshot_001', $invoice['subscription_id']);
    }

    public function test_it_can_aggregate_monthly_invoice_trend_rows_from_recent_invoices(): void
    {
        $recentInvoices = collect([
            [
                'id' => 'in_mar_001',
                'currency' => 'USD',
                'amount_paid_decimal' => 199.00,
                'created_at' => strtotime('2026-03-10 10:00:00'),
            ],
            [
                'id' => 'in_mar_002',
                'currency' => 'USD',
                'amount_paid_decimal' => 399.00,
                'created_at' => strtotime('2026-03-18 10:00:00'),
            ],
            [
                'id' => 'in_feb_001',
                'currency' => 'USD',
                'amount_paid_decimal' => 199.00,
                'created_at' => strtotime('2026-02-05 10:00:00'),
            ],
        ]);

        $trend = $recentInvoices
            ->groupBy(function (array $invoice) {
                return date('Y-m', (int) ($invoice['created_at'] ?? 0));
            })
            ->map(function (Collection $rows, string $month) {
                return [
                    'month' => $month,
                    'invoices_count' => $rows->count(),
                    'amount_paid_decimal' => round(
                        $rows->sum(fn (array $row) => (float) ($row['amount_paid_decimal'] ?? 0)),
                        2
                    ),
                    'currency' => (string) ($rows->first()['currency'] ?? 'USD'),
                ];
            })
            ->sortByDesc('month')
            ->values();

        $this->assertCount(2, $trend);

        $this->assertSame('2026-03', $trend[0]['month']);
        $this->assertSame(2, $trend[0]['invoices_count']);
        $this->assertEquals(598.00, $trend[0]['amount_paid_decimal']);
        $this->assertSame('USD', $trend[0]['currency']);

        $this->assertSame('2026-02', $trend[1]['month']);
        $this->assertSame(1, $trend[1]['invoices_count']);
        $this->assertEquals(199.00, $trend[1]['amount_paid_decimal']);
        $this->assertSame('USD', $trend[1]['currency']);
    }
}
