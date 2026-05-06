<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceSetting;
use Illuminate\Support\Collection;

class MaintenanceSettingsService
{
    public function defaults(): array
    {
        return [
            'general.company_name' => ['group' => 'general', 'value' => ''],
            'general.default_language' => ['group' => 'general', 'value' => 'en'],
            'general.timezone' => ['group' => 'general', 'value' => config('app.timezone')],
            'general.currency' => ['group' => 'general', 'value' => 'USD'],
            'general.tax_percentage' => ['group' => 'general', 'value' => 0],
            'work_orders.auto_numbering' => ['group' => 'work_orders', 'value' => true],
            'work_orders.require_approval_before_work' => ['group' => 'work_orders', 'value' => true],
            'work_orders.allow_technician_close_job' => ['group' => 'work_orders', 'value' => false],
            'work_orders.require_qc_before_delivery' => ['group' => 'work_orders', 'value' => true],
            'work_orders.require_payment_before_delivery' => ['group' => 'work_orders', 'value' => false],
            'work_orders.allow_partial_approval' => ['group' => 'work_orders', 'value' => true],
            'work_orders.allow_walk_in_work_orders' => ['group' => 'work_orders', 'value' => true],
            'inspection.require_photos' => ['group' => 'inspection', 'value' => false],
            'inspection.require_customer_signature' => ['group' => 'inspection', 'value' => false],
            'inspection.damage_marking_required' => ['group' => 'inspection', 'value' => true],
            'warranty.default_labor_days' => ['group' => 'warranty', 'value' => 30],
            'warranty.default_service_days' => ['group' => 'warranty', 'value' => 90],
            'warranty.default_mileage_limit' => ['group' => 'warranty', 'value' => 1000],
            'approval.discount_threshold' => ['group' => 'approval', 'value' => 10],
            'approval.high_value_estimate_threshold' => ['group' => 'approval', 'value' => 5000],
            'documents.default_pdf_language' => ['group' => 'documents', 'value' => 'en'],
            'documents.qr_verification_enabled' => ['group' => 'documents', 'value' => true],
            'documents.show_logo' => ['group' => 'documents', 'value' => true],
            'documents.show_branch_info' => ['group' => 'documents', 'value' => true],
            'documents.generation_mode' => ['group' => 'documents', 'value' => 'sync'],
        ];
    }

    public function all(): Collection
    {
        $stored = MaintenanceSetting::query()->get()->keyBy('setting_key');

        return collect($this->defaults())->map(function (array $default, string $key) use ($stored): array {
            $setting = $stored->get($key);

            return [
                'key' => $key,
                'group' => $setting?->group_code ?? $default['group'],
                'value' => $setting?->setting_value['value'] ?? $default['value'],
                'updated_at' => $setting?->updated_at,
            ];
        });
    }

    public function grouped(): array
    {
        return $this->all()
            ->groupBy('group')
            ->map(fn (Collection $items): array => $items->keyBy('key')->all())
            ->all();
    }

    public function updateMany(array $values, ?int $userId = null): array
    {
        $before = $this->all()->pluck('value', 'key')->all();
        $defaults = $this->defaults();

        foreach ($values as $key => $value) {
            if (! isset($defaults[$key])) {
                continue;
            }

            MaintenanceSetting::query()->updateOrCreate(
                ['setting_key' => $key],
                [
                    'group_code' => $defaults[$key]['group'],
                    'setting_value' => ['value' => $this->castValue($value, $defaults[$key]['value'])],
                    'updated_by' => $userId,
                ]
            );
        }

        return [
            'before' => $before,
            'after' => $this->all()->pluck('value', 'key')->all(),
        ];
    }

    protected function castValue(mixed $value, mixed $default): mixed
    {
        if (is_bool($default)) {
            return (bool) $value;
        }

        if (is_int($default)) {
            return (int) $value;
        }

        if (is_float($default)) {
            return (float) $value;
        }

        return $value;
    }
}
