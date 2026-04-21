<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\AppSettingsService;
use App\Services\Admin\ProductLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductIntegrationController extends Controller
{
    public function __construct(
        protected AppSettingsService $settingsService,
        protected ProductLifecycleService $lifecycleService
    ) {
    }

    public function edit(Product $product): View
    {
        $integrations = collect($this->integrationDraft($product))
            ->values();

        while ($integrations->count() < 4) {
            $integrations->push([
                'key' => '',
                'target_product_code' => '',
                'title' => '',
                'description' => '',
                'target_label' => '',
                'target_route_slug' => '',
                'events' => [],
                'source_capabilities' => [],
                'target_capabilities' => [],
                'payload_schema' => [],
            ]);
        }

        return view('admin.products.integrations', [
            'product' => $product,
            'integrations' => $integrations,
            'availableProducts' => Product::query()
                ->whereKeyNot($product->id)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'integrations' => ['nullable', 'array'],
            'integrations.*.key' => ['nullable', 'string', 'max:255'],
            'integrations.*.target_product_code' => ['nullable', 'string', 'max:255'],
            'integrations.*.title' => ['nullable', 'string', 'max:255'],
            'integrations.*.description' => ['nullable', 'string'],
            'integrations.*.target_label' => ['nullable', 'string', 'max:255'],
            'integrations.*.target_route_slug' => ['nullable', 'string', 'max:255'],
            'integrations.*.events' => ['nullable', 'string'],
            'integrations.*.source_capabilities' => ['nullable', 'string'],
            'integrations.*.target_capabilities' => ['nullable', 'string'],
            'integrations.*.payload_schema' => ['nullable', 'string'],
        ]);

        $integrations = collect($validated['integrations'] ?? [])
            ->map(function (array $integration): array {
                return [
                    'key' => trim((string) ($integration['key'] ?? '')),
                    'target_product_code' => trim((string) ($integration['target_product_code'] ?? '')),
                    'title' => trim((string) ($integration['title'] ?? '')),
                    'description' => trim((string) ($integration['description'] ?? '')),
                    'target_label' => trim((string) ($integration['target_label'] ?? '')),
                    'target_route_slug' => trim((string) ($integration['target_route_slug'] ?? '')),
                    'events' => $this->multilineList($integration['events'] ?? null),
                    'source_capabilities' => $this->multilineList($integration['source_capabilities'] ?? null),
                    'target_capabilities' => $this->multilineList($integration['target_capabilities'] ?? null),
                    'payload_schema' => $this->payloadSchema($integration['payload_schema'] ?? null),
                ];
            })
            ->filter(fn (array $integration) => $integration['key'] !== '' || $integration['target_product_code'] !== '' || $integration['title'] !== '' || $integration['events'] !== [])
            ->values()
            ->all();

        $this->settingsService->set(
            key: $this->integrationSettingKey($product),
            value: $integrations,
            valueType: 'json',
            groupKey: 'workspace_products'
        );

        $this->lifecycleService->appendAudit($product, 'integrations.saved', [
            'integrations_count' => count($integrations),
            'target_product_codes' => collect($integrations)->pluck('target_product_code')->filter()->values()->all(),
        ]);

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Integration draft saved successfully.');
    }

    protected function integrationDraft(Product $product): array
    {
        return (array) $this->settingsService->get($this->integrationSettingKey($product), []);
    }

    protected function integrationSettingKey(Product $product): string
    {
        return 'workspace_products.integrations.' . $product->code;
    }

    protected function multilineList(?string $value): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) $value) ?: [])
            ->map(fn ($item): string => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function payloadSchema(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return collect(preg_split('/[\r\n,]+/', $value) ?: [])
            ->map(fn ($item): string => trim($item))
            ->filter()
            ->mapWithKeys(function (string $item): array {
                [$field, $type] = array_pad(array_map('trim', explode(':', $item, 2)), 2, 'mixed');

                return $field !== '' ? [$field => $type !== '' ? $type : 'mixed'] : [];
            })
            ->all();
    }
}
