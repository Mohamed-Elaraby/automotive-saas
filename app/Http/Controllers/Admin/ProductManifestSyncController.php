<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\AppSettingsService;
use App\Services\Tenancy\WorkspaceManifestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $manifestData = $this->manifestDraftData($product);
        $workflow = $this->workflowState($product);
        $latestSnapshot = $this->latestSnapshot($product);

        return view('admin.products.manifest-sync', [
            'product' => $product,
            'draftFamilyKey' => $manifestData['draft_family_key'],
            'currentFamilyDefinition' => $manifestData['current_family_definition'],
            'payload' => $manifestData['payload'],
            'payloadExport' => var_export($manifestData['payload'], true),
            'payloadJson' => json_encode($manifestData['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'familyExport' => var_export([$manifestData['draft_family_key'] => $manifestData['payload']], true),
            'syncChecklist' => $manifestData['sync_checklist'],
            'workflow' => $workflow,
            'latestSnapshot' => $latestSnapshot,
            'writebackPlan' => $this->writebackPlan($product, $manifestData, $workflow),
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

        if (in_array($payload['status'], ['approved', 'applied'], true)) {
            $manifestData = $this->manifestDraftData($product);
            $snapshot = [
                'status' => $payload['status'],
                'notes' => $payload['notes'],
                'family_key' => $manifestData['draft_family_key'],
                'payload' => $manifestData['payload'],
                'captured_at' => now()->toDateTimeString(),
            ];

            $this->settingsService->set(
                key: $this->snapshotSettingKey($product),
                value: $snapshot,
                valueType: 'json',
                groupKey: 'workspace_products'
            );
        }

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

    public function export(Product $product, string $format): Response
    {
        $manifestData = $this->manifestDraftData($product);

        abort_unless(in_array($format, ['json', 'php', 'family'], true), 404);

        return match ($format) {
            'json' => response(
                json_encode($manifestData['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                200,
                ['Content-Type' => 'application/json; charset=UTF-8']
            ),
            'family' => response(
                "<?php\n\nreturn " . var_export([$manifestData['draft_family_key'] => $manifestData['payload']], true) . ";\n",
                200,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            ),
            default => response(
                "<?php\n\n" . '$familyDefinition = ' . var_export($manifestData['payload'], true) . ";\n",
                200,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            ),
        };
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

    protected function snapshotSettingKey(Product $product): string
    {
        return 'workspace_products.manifest_sync_snapshot.' . $product->code;
    }

    protected function latestSnapshot(Product $product): array
    {
        return (array) $this->settingsService->get($this->snapshotSettingKey($product), []);
    }

    protected function manifestDraftData(Product $product): array
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

        return [
            'draft_family_key' => $draftFamilyKey,
            'current_family_definition' => $currentFamilyDefinition,
            'payload' => $payload,
            'sync_checklist' => [
                'experience_draft' => $experienceDraft !== [],
                'runtime_modules' => $runtimeModulesDraft !== [],
                'integrations' => $integrationDraft !== [],
            ],
        ];
    }

    protected function writebackPlan(Product $product, array $manifestData, array $workflow): array
    {
        $familyKey = (string) $manifestData['draft_family_key'];
        $hasExistingFamily = $manifestData['current_family_definition'] !== [];
        $hasRuntimeModules = ! empty($manifestData['payload']['runtime_modules'] ?? []);
        $hasIntegrations = ! empty($manifestData['payload']['integrations'] ?? []);
        $hasDashboardActions = ! empty($manifestData['payload']['dashboard_actions'] ?? []);
        $approved = (string) ($workflow['status'] ?? 'draft') === 'approved';

        $steps = [
            [
                'label' => 'Open config/workspace_products.php',
                'completed' => true,
                'details' => 'This family is written under the `families` array in the workspace manifest config.',
            ],
            [
                'label' => $hasExistingFamily ? 'Update existing family block' : 'Add new family block',
                'completed' => $hasExistingFamily,
                'details' => $hasExistingFamily
                    ? "Family `{$familyKey}` already exists in code and should be updated in place."
                    : "Family `{$familyKey}` does not exist yet and needs a new config block.",
            ],
            [
                'label' => 'Write experience and sidebar data',
                'completed' => ! empty($manifestData['payload']['experience'] ?? []),
                'details' => 'Apply aliases, experience copy, and sidebar section from the approved payload.',
            ],
            [
                'label' => 'Write runtime modules',
                'completed' => $hasRuntimeModules,
                'details' => $hasRuntimeModules
                    ? 'Runtime module definitions are ready to be written into `runtime_modules`.'
                    : 'No runtime modules are currently drafted.',
            ],
            [
                'label' => 'Write integrations',
                'completed' => $hasIntegrations,
                'details' => $hasIntegrations
                    ? 'Integration definitions are ready to be written into `integrations`.'
                    : 'No integrations are currently drafted.',
            ],
            [
                'label' => 'Review dashboard actions',
                'completed' => $hasDashboardActions,
                'details' => $hasDashboardActions
                    ? 'Dashboard actions exist and can be mapped to final route names.'
                    : 'No dashboard actions are currently drafted.',
            ],
            [
                'label' => 'Mark workflow as applied after code merge',
                'completed' => (string) ($workflow['status'] ?? '') === 'applied',
                'details' => $approved
                    ? 'After code writeback lands, return هنا and change status to `Applied In Code`.'
                    : 'Approve the payload first before treating it as code-ready.',
            ],
        ];

        $patchOutline = [
            'file' => 'config/workspace_products.php',
            'family_key' => $familyKey,
            'mode' => $hasExistingFamily ? 'update' : 'add',
            'sections' => array_values(array_filter([
                ! empty($manifestData['payload']['aliases'] ?? []) ? 'aliases' : null,
                ! empty($manifestData['payload']['experience'] ?? []) ? 'experience' : null,
                ! empty($manifestData['payload']['sidebar_section'] ?? []) ? 'sidebar_section' : null,
                $hasDashboardActions ? 'dashboard_actions' : null,
                $hasRuntimeModules ? 'runtime_modules' : null,
                $hasIntegrations ? 'integrations' : null,
            ])),
        ];

        return [
            'steps' => $steps,
            'patch_outline' => $patchOutline,
            'git_commands' => [
                'git add config/workspace_products.php PROJECT_AI_CONTEXT.md',
                'git commit -m "Sync workspace manifest for ' . $product->code . '"',
                'git push origin main',
            ],
        ];
    }
}
