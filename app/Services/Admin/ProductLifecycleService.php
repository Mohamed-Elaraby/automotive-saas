<?php

namespace App\Services\Admin;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class ProductLifecycleService
{
    public function __construct(
        protected AppSettingsService $settingsService,
        protected ProductIntegrationGovernanceService $integrationGovernanceService
    ) {
    }

    public function validationSummary(Product $product): array
    {
        $product->loadCount([
            'capabilities',
            'plans as active_plans_count' => fn ($query) => $query->where('is_active', true),
        ]);

        $experienceDraft = $this->experienceDraft($product);
        $runtimeModulesDraft = $this->runtimeModulesDraft($product);
        $integrationsDraft = $this->integrationsDraft($product);
        $workflow = $this->manifestWorkflow($product);
        $snapshot = $this->manifestSnapshot($product);
        $applyQueue = $this->manifestApplyQueue($product);

        $publicationBlockers = [];

        if ((int) $product->capabilities_count <= 0) {
            $publicationBlockers[] = 'Add at least one active product capability.';
        }

        if ((int) $product->active_plans_count <= 0) {
            $publicationBlockers[] = 'Add at least one active plan.';
        }

        if ($experienceDraft === []) {
            $publicationBlockers[] = 'Save the workspace experience draft first.';
        }

        if (blank((string) ($experienceDraft['family_key'] ?? ''))) {
            $publicationBlockers[] = 'Set a family key in the workspace experience draft.';
        }

        $manifestSyncBlockers = [];

        if ($experienceDraft === []) {
            $manifestSyncBlockers[] = 'Workspace experience draft is missing.';
        }

        if (blank((string) ($experienceDraft['family_key'] ?? ''))) {
            $manifestSyncBlockers[] = 'Workspace experience draft needs a family key.';
        }

        if (! empty($experienceDraft['runtime_modules'] ?? []) && $runtimeModulesDraft === []) {
            $manifestSyncBlockers[] = 'Structured runtime modules are missing even though the experience draft references runtime modules.';
        }

        if (! empty($experienceDraft['integrations'] ?? []) && $integrationsDraft === []) {
            $manifestSyncBlockers[] = 'Structured integrations are missing even though the experience draft references integrations.';
        }

        $invalidTargets = $this->invalidIntegrationTargets($product, $integrationsDraft);
        $integrationGovernance = $this->integrationGovernanceService->evaluate($product, $experienceDraft, $integrationsDraft);

        if ($invalidTargets !== []) {
            $manifestSyncBlockers[] = 'One or more integration targets do not exist in the product catalog: ' . implode(', ', $invalidTargets) . '.';
        }

        $manifestSyncBlockers = array_values(array_unique(array_merge(
            $manifestSyncBlockers,
            $integrationGovernance['blockers']
        )));

        $applyQueueBlockers = [];

        if (! in_array((string) ($workflow['status'] ?? 'draft'), ['approved', 'applied'], true)) {
            $applyQueueBlockers[] = 'Manifest workflow must be approved before execution starts.';
        }

        if ($snapshot === []) {
            $applyQueueBlockers[] = 'Capture an approved manifest snapshot before queueing execution.';
        }

        if (in_array((string) ($applyQueue['status'] ?? 'queued'), ['in_progress', 'done'], true) && blank((string) ($applyQueue['owner_name'] ?? ''))) {
            $applyQueueBlockers[] = 'Assign an owner before moving execution into progress or done.';
        }

        $applyQueueBlockers = array_values(array_unique(array_merge(
            $applyQueueBlockers,
            $integrationGovernance['blockers']
        )));

        return [
            'publication' => [
                'ready' => $publicationBlockers === [],
                'blockers' => $publicationBlockers,
            ],
            'manifest_sync' => [
                'ready' => $manifestSyncBlockers === [],
                'blockers' => $manifestSyncBlockers,
            ],
            'integration_governance' => $integrationGovernance,
            'apply_queue' => [
                'ready' => $applyQueueBlockers === [],
                'blockers' => $applyQueueBlockers,
            ],
        ];
    }

    public function publicationBlockers(Product $product): array
    {
        return $this->validationSummary($product)['publication']['blockers'];
    }

    public function manifestSyncBlockers(Product $product): array
    {
        return $this->validationSummary($product)['manifest_sync']['blockers'];
    }

    public function applyQueueBlockers(Product $product): array
    {
        return $this->validationSummary($product)['apply_queue']['blockers'];
    }

    public function appendAudit(Product $product, string $action, array $details = []): void
    {
        $entries = collect($this->auditEntries($product, 100));
        $actor = Auth::guard('admin')->user();

        $entries->prepend([
            'action' => $action,
            'actor' => $actor?->name ?: $actor?->email ?: 'system',
            'details' => $details,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $this->settingsService->set(
            key: $this->auditSettingKey($product),
            value: $entries->take(100)->values()->all(),
            valueType: 'json',
            groupKey: 'workspace_products'
        );
    }

    public function auditEntries(Product $product, int $limit = 12): array
    {
        return collect((array) $this->settingsService->get($this->auditSettingKey($product), []))
            ->take($limit)
            ->values()
            ->all();
    }

    protected function invalidIntegrationTargets(Product $product, array $integrationsDraft): array
    {
        $targets = collect($integrationsDraft)
            ->pluck('target_product_code')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values();

        if ($targets->isEmpty()) {
            return [];
        }

        $existing = Product::query()
            ->whereIn('code', $targets->all())
            ->pluck('code')
            ->map(fn ($code) => (string) $code)
            ->all();

        return $targets
            ->reject(fn (string $code) => in_array($code, $existing, true) || $code === (string) $product->code)
            ->values()
            ->all();
    }

    protected function experienceDraft(Product $product): array
    {
        return (array) $this->settingsService->get('workspace_products.experience.' . $product->code, []);
    }

    protected function runtimeModulesDraft(Product $product): array
    {
        return (array) $this->settingsService->get('workspace_products.runtime_modules.' . $product->code, []);
    }

    protected function integrationsDraft(Product $product): array
    {
        return (array) $this->settingsService->get('workspace_products.integrations.' . $product->code, []);
    }

    protected function manifestWorkflow(Product $product): array
    {
        return (array) $this->settingsService->get('workspace_products.manifest_sync_workflow.' . $product->code, []);
    }

    protected function manifestSnapshot(Product $product): array
    {
        return (array) $this->settingsService->get('workspace_products.manifest_sync_snapshot.' . $product->code, []);
    }

    protected function manifestApplyQueue(Product $product): array
    {
        return (array) $this->settingsService->get('workspace_products.manifest_apply_queue.' . $product->code, []);
    }

    protected function auditSettingKey(Product $product): string
    {
        return 'workspace_products.audit_trail.' . $product->code;
    }
}
