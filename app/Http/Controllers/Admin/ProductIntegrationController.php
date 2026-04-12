<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\AppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductIntegrationController extends Controller
{
    public function __construct(
        protected AppSettingsService $settingsService
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
                ];
            })
            ->filter(fn (array $integration) => $integration['key'] !== '' || $integration['target_product_code'] !== '' || $integration['title'] !== '')
            ->values()
            ->all();

        $this->settingsService->set(
            key: $this->integrationSettingKey($product),
            value: $integrations,
            valueType: 'json',
            groupKey: 'workspace_products'
        );

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
}
