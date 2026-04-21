<?php

namespace App\Services\Tenancy;

use App\Models\WorkspaceIntegrationHandoff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkspaceIntegrationHandoffService
{
    public function __construct(
        protected WorkspaceIntegrationContractService $workspaceIntegrationContractService
    ) {
    }

    public function start(array $envelope, ?int $createdBy = null): WorkspaceIntegrationHandoff
    {
        $this->assertContractAllowsEnvelope($envelope);

        $idempotencyKey = $this->idempotencyKey($envelope);

        return DB::transaction(function () use ($envelope, $createdBy, $idempotencyKey): WorkspaceIntegrationHandoff {
            $handoff = WorkspaceIntegrationHandoff::query()->firstOrNew([
                'idempotency_key' => $idempotencyKey,
            ]);

            if (! $handoff->exists) {
                $handoff->fill([
                    'integration_key' => $envelope['integration_key'],
                    'event_name' => $envelope['event_name'],
                    'source_product' => $envelope['source_product'],
                    'target_product' => $envelope['target_product'] ?? null,
                    'source_type' => $envelope['source_type'] ?? null,
                    'source_id' => $envelope['source_id'] ?? null,
                    'status' => 'pending',
                    'payload' => $envelope['payload'] ?? [],
                    'created_by' => $createdBy,
                ]);
            }

            $handoff->forceFill([
                'attempts' => ((int) $handoff->attempts) + 1,
                'last_attempted_at' => now(),
                'error_message' => null,
            ])->save();

            return $handoff;
        });
    }

    public function markPosted(WorkspaceIntegrationHandoff $handoff, ?Model $target = null, array $payload = []): WorkspaceIntegrationHandoff
    {
        $handoff->forceFill([
            'status' => 'posted',
            'target_type' => $target ? $target::class : $handoff->target_type,
            'target_id' => $target?->getKey() ?? $handoff->target_id,
            'payload' => array_replace_recursive((array) $handoff->payload, $payload),
            'error_message' => null,
            'completed_at' => now(),
        ])->save();

        return $handoff;
    }

    public function markSkipped(WorkspaceIntegrationHandoff $handoff, string $reason, array $payload = []): WorkspaceIntegrationHandoff
    {
        $handoff->forceFill([
            'status' => 'skipped',
            'payload' => array_replace_recursive((array) $handoff->payload, $payload),
            'error_message' => $reason,
            'completed_at' => now(),
        ])->save();

        return $handoff;
    }

    public function markFailed(WorkspaceIntegrationHandoff $handoff, string $error, array $payload = []): WorkspaceIntegrationHandoff
    {
        $handoff->forceFill([
            'status' => 'failed',
            'payload' => array_replace_recursive((array) $handoff->payload, $payload),
            'error_message' => $error,
        ])->save();

        return $handoff;
    }

    public function recent(int $limit = 25)
    {
        return WorkspaceIntegrationHandoff::query()
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    protected function idempotencyKey(array $envelope): string
    {
        return implode('|', [
            (string) ($envelope['integration_key'] ?? ''),
            (string) ($envelope['event_name'] ?? ''),
            (string) ($envelope['source_type'] ?? ''),
            (string) ($envelope['source_id'] ?? ''),
        ]);
    }

    protected function assertContractAllowsEnvelope(array $envelope): void
    {
        foreach (['integration_key', 'event_name', 'source_product', 'target_product'] as $field) {
            if (blank((string) ($envelope[$field] ?? ''))) {
                throw ValidationException::withMessages([
                    $field => "Integration handoff envelope is missing {$field}.",
                ]);
            }
        }

        if (! $this->workspaceIntegrationContractService->findForEnvelope($envelope)) {
            throw ValidationException::withMessages([
                'integration_key' => 'No active workspace integration contract matches this handoff envelope.',
            ]);
        }
    }
}
