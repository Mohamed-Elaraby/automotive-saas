<?php

namespace Database\Seeders;

use App\Models\Core\Documents\DocumentTemplate;
use Illuminate\Database\Seeder;

class TenantDocumentFoundationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            DocumentTemplate::query()->updateOrCreate(
                [
                    'tenant_id' => $template['tenant_id'],
                    'product_key' => $template['product_key'],
                    'document_type' => $template['document_type'],
                    'document_key' => $template['document_key'],
                    'language' => $template['language'],
                ],
                [
                    'name' => $template['name'],
                    'view_path' => $template['view_path'],
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
            ['tenant_id' => $tenantId, 'product_key' => 'automotive', 'document_type' => 'maintenance_work_order', 'document_key' => 'work_order', 'name' => 'Automotive Work Order', 'view_path' => 'documents.products.automotive.work_order', 'language' => null],
            ['tenant_id' => $tenantId, 'product_key' => 'automotive', 'document_type' => 'automotive_job_card', 'document_key' => 'job_card', 'name' => 'Automotive Job Card', 'view_path' => 'documents.products.automotive.job_card', 'language' => null],
            ['tenant_id' => $tenantId, 'product_key' => 'automotive', 'document_type' => 'maintenance_invoice', 'document_key' => 'invoice', 'name' => 'Automotive Invoice', 'view_path' => 'documents.products.automotive.invoice', 'language' => null],
            ['tenant_id' => $tenantId, 'product_key' => 'accounting', 'document_type' => 'accounting_tax_invoice', 'document_key' => 'tax_invoice', 'name' => 'Accounting Tax Invoice', 'view_path' => 'documents.products.accounting.tax_invoice', 'language' => null],
            ['tenant_id' => $tenantId, 'product_key' => 'inventory', 'document_type' => 'inventory_purchase_order', 'document_key' => 'purchase_order', 'name' => 'Inventory Purchase Order', 'view_path' => 'documents.products.inventory.purchase_order', 'language' => null],
        ];
    }
}
