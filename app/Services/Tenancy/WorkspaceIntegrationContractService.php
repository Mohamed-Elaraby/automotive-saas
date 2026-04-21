<?php

namespace App\Services\Tenancy;

use Illuminate\Support\Collection;

class WorkspaceIntegrationContractService
{
    public function __construct(
        protected WorkspaceManifestService $workspaceManifestService
    ) {
    }

    public function contracts(): Collection
    {
        return collect($this->workspaceManifestService->familyKeys())
            ->flatMap(function (string $sourceFamily): array {
                $definition = $this->workspaceManifestService->familyDefinition($sourceFamily);

                return collect((array) ($definition['integrations'] ?? []))
                    ->map(function (array $integration) use ($sourceFamily): array {
                        return $this->normalizeContract($sourceFamily, $integration);
                    })
                    ->all();
            })
            ->filter(fn (array $contract): bool => $contract['key'] !== '' && $contract['target_family'] !== '')
            ->values();
    }

    public function find(string $key): ?array
    {
        return $this->contracts()
            ->first(fn (array $contract): bool => $contract['key'] === $key);
    }

    public function forEvent(string $eventName): Collection
    {
        return $this->contracts()
            ->filter(fn (array $contract): bool => in_array($eventName, $contract['events'], true))
            ->values();
    }

    public function findForEnvelope(array $envelope): ?array
    {
        $integrationKey = trim((string) ($envelope['integration_key'] ?? ''));
        $eventName = trim((string) ($envelope['event_name'] ?? ''));
        $sourceFamily = $this->workspaceManifestService->resolveFamilyFromText((string) ($envelope['source_product'] ?? ''));
        $targetFamily = trim((string) ($envelope['target_product'] ?? '')) !== ''
            ? $this->workspaceManifestService->resolveFamilyFromText((string) $envelope['target_product'])
            : '';

        return $this->contracts()
            ->first(function (array $contract) use ($integrationKey, $eventName, $sourceFamily, $targetFamily): bool {
                if ($contract['key'] !== $integrationKey || $contract['source_family'] !== $sourceFamily) {
                    return false;
                }

                if ($contract['events'] !== [] && ! in_array($eventName, $contract['events'], true)) {
                    return false;
                }

                return $targetFamily !== '' && $contract['target_family'] === $targetFamily;
            });
    }

    protected function normalizeContract(string $sourceFamily, array $integration): array
    {
        $targetFamilyText = trim((string) ($integration['target_family'] ?? $integration['requires_family'] ?? ''));
        $targetFamily = $targetFamilyText !== ''
            ? $this->workspaceManifestService->resolveFamilyFromText($targetFamilyText)
            : '';

        return [
            'key' => trim((string) ($integration['key'] ?? '')),
            'source_family' => $sourceFamily,
            'target_family' => $targetFamily,
            'events' => array_values(array_filter((array) ($integration['events'] ?? []))),
            'source_capabilities' => array_values(array_filter((array) ($integration['source_capabilities'] ?? []))),
            'target_capabilities' => array_values(array_filter((array) ($integration['target_capabilities'] ?? []))),
            'payload_schema' => (array) ($integration['payload_schema'] ?? []),
            'required' => (bool) ($integration['required'] ?? false),
            'title' => trim((string) ($integration['title'] ?? 'Connected product integration')),
            'description' => trim((string) ($integration['description'] ?? '')),
        ];
    }
}
