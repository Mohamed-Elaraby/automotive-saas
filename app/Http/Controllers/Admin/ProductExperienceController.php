<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\AppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductExperienceController extends Controller
{
    public function __construct(
        protected AppSettingsService $settingsService
    ) {
    }

    public function edit(Product $product): View
    {
        return view('admin.products.experience', [
            'product' => $product,
            'experience' => $this->experienceDraft($product),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'family_key' => ['nullable', 'string', 'max:255'],
            'aliases' => ['nullable', 'string'],
            'portal_eyebrow' => ['nullable', 'string', 'max:255'],
            'portal_title' => ['nullable', 'string', 'max:255'],
            'portal_description' => ['nullable', 'string'],
            'portal_accent' => ['nullable', 'string', 'max:50'],
            'sidebar_title' => ['nullable', 'string', 'max:255'],
            'dashboard_actions' => ['nullable', 'string'],
            'runtime_modules' => ['nullable', 'string'],
            'integrations' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = [
            'family_key' => trim((string) ($validated['family_key'] ?? '')),
            'aliases' => $this->multilineList($validated['aliases'] ?? null),
            'portal' => [
                'eyebrow' => trim((string) ($validated['portal_eyebrow'] ?? '')),
                'title' => trim((string) ($validated['portal_title'] ?? '')),
                'description' => trim((string) ($validated['portal_description'] ?? '')),
                'accent' => trim((string) ($validated['portal_accent'] ?? '')),
            ],
            'sidebar_title' => trim((string) ($validated['sidebar_title'] ?? '')),
            'dashboard_actions' => $this->multilineList($validated['dashboard_actions'] ?? null),
            'runtime_modules' => $this->multilineList($validated['runtime_modules'] ?? null),
            'integrations' => $this->multilineList($validated['integrations'] ?? null),
            'notes' => trim((string) ($validated['notes'] ?? '')),
            'updated_at' => now()->toDateTimeString(),
        ];

        $this->settingsService->set(
            key: $this->experienceSettingKey($product),
            value: $payload,
            valueType: 'json',
            groupKey: 'workspace_products'
        );

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Workspace experience draft saved successfully.');
    }

    protected function multilineList(?string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $value) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    protected function experienceDraft(Product $product): array
    {
        return (array) $this->settingsService->get($this->experienceSettingKey($product), []);
    }

    protected function experienceSettingKey(Product $product): string
    {
        return 'workspace_products.experience.' . $product->code;
    }
}
