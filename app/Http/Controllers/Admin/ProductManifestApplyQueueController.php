<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\AppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductManifestApplyQueueController extends Controller
{
    public function __construct(
        protected AppSettingsService $settingsService
    ) {
    }

    public function show(Product $product): View
    {
        $workflow = $this->workflowState($product);
        $snapshot = $this->latestSnapshot($product);
        $queue = $this->queueState($product);

        return view('admin.products.manifest-apply-queue', [
            'product' => $product,
            'workflow' => $workflow,
            'latestSnapshot' => $snapshot,
            'queue' => $queue,
            'readiness' => [
                'workflow_approved' => in_array((string) ($workflow['status'] ?? 'draft'), ['approved', 'applied'], true),
                'snapshot_available' => $snapshot !== [],
                'owner_assigned' => filled($queue['owner_name'] ?? null),
                'status_started' => in_array((string) ($queue['status'] ?? 'queued'), ['in_progress', 'done'], true),
            ],
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:queued,in_progress,blocked,done'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'owner_contact' => ['nullable', 'string', 'max:255'],
            'blocking_reason' => ['nullable', 'string'],
            'implementation_notes' => ['nullable', 'string'],
            'deployment_notes' => ['nullable', 'string'],
        ]);

        $existing = $this->queueState($product);
        $now = now()->toDateTimeString();
        $status = (string) $validated['status'];

        $payload = [
            'status' => $status,
            'owner_name' => trim((string) ($validated['owner_name'] ?? '')),
            'owner_contact' => trim((string) ($validated['owner_contact'] ?? '')),
            'blocking_reason' => trim((string) ($validated['blocking_reason'] ?? '')),
            'implementation_notes' => trim((string) ($validated['implementation_notes'] ?? '')),
            'deployment_notes' => trim((string) ($validated['deployment_notes'] ?? '')),
            'queued_at' => $existing['queued_at'] ?? null,
            'started_at' => $existing['started_at'] ?? null,
            'completed_at' => $existing['completed_at'] ?? null,
            'updated_at' => $now,
        ];

        if ($status === 'queued') {
            $payload['queued_at'] = $payload['queued_at'] ?? $now;
            $payload['started_at'] = null;
            $payload['completed_at'] = null;
        }

        if ($status === 'in_progress') {
            $payload['queued_at'] = $payload['queued_at'] ?? $now;
            $payload['started_at'] = $payload['started_at'] ?? $now;
            $payload['completed_at'] = null;
        }

        if ($status === 'blocked') {
            $payload['queued_at'] = $payload['queued_at'] ?? $now;
            $payload['started_at'] = $payload['started_at'] ?? $now;
            $payload['completed_at'] = null;
        }

        if ($status === 'done') {
            $payload['queued_at'] = $payload['queued_at'] ?? $now;
            $payload['started_at'] = $payload['started_at'] ?? $now;
            $payload['completed_at'] = $payload['completed_at'] ?? $now;
        }

        $this->settingsService->set(
            key: $this->queueSettingKey($product),
            value: $payload,
            valueType: 'json',
            groupKey: 'workspace_products'
        );

        return redirect()
            ->route('admin.products.manifest-apply-queue.show', $product)
            ->with('success', 'Manifest apply queue updated successfully.');
    }

    protected function queueState(Product $product): array
    {
        return (array) $this->settingsService->get($this->queueSettingKey($product), [
            'status' => 'queued',
            'owner_name' => '',
            'owner_contact' => '',
            'blocking_reason' => '',
            'implementation_notes' => '',
            'deployment_notes' => '',
            'queued_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'updated_at' => null,
        ]);
    }

    protected function workflowState(Product $product): array
    {
        return (array) $this->settingsService->get('workspace_products.manifest_sync_workflow.' . $product->code, [
            'status' => 'draft',
            'notes' => '',
            'reviewed_at' => null,
        ]);
    }

    protected function latestSnapshot(Product $product): array
    {
        return (array) $this->settingsService->get('workspace_products.manifest_sync_snapshot.' . $product->code, []);
    }

    protected function queueSettingKey(Product $product): string
    {
        return 'workspace_products.manifest_apply_queue.' . $product->code;
    }
}
