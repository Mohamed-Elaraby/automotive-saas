<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class TenantNotificationFoundationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            NotificationTemplate::query()->updateOrCreate(
                [
                    'tenant_id' => $template['tenant_id'],
                    'product_key' => $template['product_key'],
                    'event_key' => $template['event_key'],
                    'channel' => $template['channel'],
                    'language' => $template['language'],
                ],
                [
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'is_active' => true,
                    'metadata' => ['seeded_by' => static::class],
                ]
            );
        }
    }

    protected function templates(): array
    {
        $tenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;

        return [
            ['tenant_id' => $tenantId, 'product_key' => 'automotive', 'event_key' => 'automotive.work_order_created', 'channel' => 'in_app', 'language' => null, 'subject' => 'Work order created', 'body' => 'Work order {{ work_order_number }} was created.'],
            ['tenant_id' => $tenantId, 'product_key' => 'automotive', 'event_key' => 'automotive.vehicle_ready', 'channel' => 'in_app', 'language' => null, 'subject' => 'Vehicle ready', 'body' => 'Vehicle {{ plate_number }} is ready.'],
            ['tenant_id' => $tenantId, 'product_key' => 'automotive', 'event_key' => 'automotive.quotation_approved', 'channel' => 'in_app', 'language' => null, 'subject' => 'Quotation approved', 'body' => 'Quotation {{ quotation_number }} was approved.'],
            ['tenant_id' => $tenantId, 'product_key' => 'accounting', 'event_key' => 'accounting.invoice_overdue', 'channel' => 'in_app', 'language' => null, 'subject' => 'Invoice overdue', 'body' => 'Invoice {{ invoice_number }} is overdue.'],
            ['tenant_id' => $tenantId, 'product_key' => 'accounting', 'event_key' => 'accounting.payment_received', 'channel' => 'in_app', 'language' => null, 'subject' => 'Payment received', 'body' => 'Payment {{ payment_number }} was received.'],
            ['tenant_id' => $tenantId, 'product_key' => 'inventory', 'event_key' => 'inventory.low_stock', 'channel' => 'in_app', 'language' => null, 'subject' => 'Low stock', 'body' => '{{ item_name }} is below minimum stock.'],
        ];
    }
}
