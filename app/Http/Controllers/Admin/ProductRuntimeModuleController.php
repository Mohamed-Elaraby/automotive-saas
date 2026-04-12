<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\AppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductRuntimeModuleController extends Controller
{
    public function __construct(
        protected AppSettingsService $settingsService
    ) {
    }

    public function edit(Product $product): View
    {
        $modules = collect($this->runtimeModulesDraft($product))
            ->values();

        while ($modules->count() < 4) {
            $modules->push([
                'key' => '',
                'title' => '',
                'focus_code' => $product->code,
                'route_slug' => '',
                'icon' => '',
                'description' => '',
            ]);
        }

        return view('admin.products.runtime-modules', [
            'product' => $product,
            'modules' => $modules,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*.key' => ['nullable', 'string', 'max:255'],
            'modules.*.title' => ['nullable', 'string', 'max:255'],
            'modules.*.focus_code' => ['nullable', 'string', 'max:255'],
            'modules.*.route_slug' => ['nullable', 'string', 'max:255'],
            'modules.*.icon' => ['nullable', 'string', 'max:255'],
            'modules.*.description' => ['nullable', 'string'],
        ]);

        $modules = collect($validated['modules'] ?? [])
            ->map(function (array $module) use ($product): array {
                $key = Str::slug((string) ($module['key'] ?? ''), '-');

                return [
                    'key' => $key,
                    'title' => trim((string) ($module['title'] ?? '')),
                    'focus_code' => trim((string) ($module['focus_code'] ?? $product->code)),
                    'route_slug' => Str::slug((string) ($module['route_slug'] ?? ''), '-'),
                    'icon' => trim((string) ($module['icon'] ?? '')),
                    'description' => trim((string) ($module['description'] ?? '')),
                ];
            })
            ->filter(fn (array $module) => $module['key'] !== '' || $module['title'] !== '' || $module['route_slug'] !== '')
            ->values()
            ->all();

        $this->settingsService->set(
            key: $this->runtimeModulesSettingKey($product),
            value: $modules,
            valueType: 'json',
            groupKey: 'workspace_products'
        );

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Runtime module draft saved successfully.');
    }

    protected function runtimeModulesDraft(Product $product): array
    {
        return (array) $this->settingsService->get($this->runtimeModulesSettingKey($product), []);
    }

    protected function runtimeModulesSettingKey(Product $product): string
    {
        return 'workspace_products.runtime_modules.' . $product->code;
    }
}
