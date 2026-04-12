<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\AppSettingsService;
use App\Services\Tenancy\WorkspaceManifestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductManifestSyncController extends Controller
{
    public function __construct(
        protected AppSettingsService $settingsService,
        protected WorkspaceManifestService $workspaceManifestService
    ) {
    }

    public function show(Product $product): View
    {
        $experienceDraft = $this->experienceDraft($product);
        $runtimeModulesDraft = $this->runtimeModulesDraft($product);
        $integrationDraft = $this->integrationDraft($product);
        $draftFamilyKey = (string) ($experienceDraft['family_key'] ?? $product->code);
        $currentFamilyDefinition = $this->workspaceManifestService->familyDefinition($draftFamilyKey);

        $payload = [
            'aliases' => array_values(array_filter((array) ($experienceDraft['aliases'] ?? []))),
            'experience' => array_filter([
                'eyebrow' => data_get($experienceDraft, 'portal.eyebrow'),
                'title' => data_get($experienceDraft, 'portal.title'),
                'description' => data_get($experienceDraft, 'portal.description'),
                'accent' => data_get($experienceDraft, 'portal.accent'),
            ], fn ($value) => filled($value)),
            'sidebar_section' => array_filter([
                'key' => $draftFamilyKey,
                'title' => $experienceDraft['sidebar_title'] ?? null,
            ], fn ($value) => filled($value)),
            'dashboard_actions' => collect((array) ($experienceDraft['dashboard_actions'] ?? []))
                ->map(fn ($label, $index) => [
                    'key' => $draftFamilyKey . '.action-' . ($index + 1),
                    'label' => $label,
                ])
                ->values()
                ->all(),
            'runtime_modules' => collect($runtimeModulesDraft)
                ->mapWithKeys(fn (array $module) => [
                    (string) ($module['key'] ?? '') => array_filter([
                        'family' => $draftFamilyKey,
                        'focus_code' => $module['focus_code'] ?? $product->code,
                        'title' => $module['title'] ?? null,
                        'description' => $module['description'] ?? null,
                    ], fn ($value) => filled($value)),
                ])
                ->filter(fn ($definition, $key) => $key !== '')
                ->all(),
            'integrations' => collect($integrationDraft)
                ->map(fn (array $integration) => array_filter([
                    'key' => $integration['key'] ?? null,
                    'requires_family' => $integration['target_product_code'] ?? null,
                    'title' => $integration['title'] ?? null,
                    'description' => $integration['description'] ?? null,
                    'target_label' => $integration['target_label'] ?? null,
                    'target_route' => filled($integration['target_route_slug'] ?? null)
                        ? 'automotive.admin.modules.' . $integration['target_route_slug']
                        : null,
                ], fn ($value) => filled($value)))
                ->filter()
                ->values()
                ->all(),
        ];

        $syncChecklist = [
            'experience_draft' => $experienceDraft !== [],
            'runtime_modules' => $runtimeModulesDraft !== [],
            'integrations' => $integrationDraft !== [],
        ];
        $workflow = $this->workflowState($product);

        return view('admin.products.manifest-sync', [
            'product' => $product,
            'draftFamilyKey' => $draftFamilyKey,
            'currentFamilyDefinition' => $currentFamilyDefinition,
            'payload' => $payload,
            'payloadExport' => var_export($payload, true),
            'syncChecklist' => $syncChecklist,
            'workflow' => $workflow,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,approved,applied'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = [
            'status' => (string) $validated['status'],
            'notes' => trim((string) ($validated['notes'] ?? '')),
            'reviewed_at' => now()->toDateTimeString(),
        ];

        $this->settingsService->set(
            key: $this->workflowSettingKey($product),
            value: $payload,
            valueType: 'json',
            groupKey: 'workspace_products'
        );

        return redirect()
            ->route('admin.products.manifest-sync.show', $product)
            ->with('success', 'Manifest sync workflow updated successfully.');
    }

    protected function experienceDraft(Product $product): array
    {
        return (array) $this->settingsService->get('workspace_products.experience.' . $product->code, []);
    }

    protected function runtimeModulesDraft(Product $product): array
    {
        return (array) $this->settingsService->get('workspace_products.runtime_modules.' . $product->code, []);
    }

    protected function integrationDraft(Product $product): array
    {
        return (array) $this->settingsService->get('workspace_products.integrations.' . $product->code, []);
    }

    protected function workflowState(Product $product): array
    {
        return (array) $this->settingsService->get($this->workflowSettingKey($product), [
            'status' => 'draft',
            'notes' => '',
            'reviewed_at' => null,
        ]);
    }

    protected function workflowSettingKey(Product $product): string
    {
        return 'workspace_products.manifest_sync_workflow.' . $product->code;
    }
}
