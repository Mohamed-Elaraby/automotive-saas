<?php

namespace App\Services\Admin;

use App\Models\Product;

class ProductIntegrationGovernanceService
{
    public function evaluate(Product $product, array $experienceDraft = [], array $integrationsDraft = []): array
    {
        $declaredIntent = collect((array) ($experienceDraft['integrations'] ?? []))
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->values();

        $contracts = collect($integrationsDraft)
            ->map(fn (array $integration): array => $this->normalize($integration))
            ->filter(fn (array $integration): bool => $this->hasAnyContractField($integration))
            ->values();

        $blockers = [];
        $warnings = [];

        if ($declaredIntent->isNotEmpty() && $contracts->isEmpty()) {
            $blockers[] = 'Experience draft declares integration intent, but no structured integration contracts were saved.';
        }

        $existingProductCodes = Product::query()
            ->pluck('code')
            ->map(fn ($code): string => (string) $code)
            ->all();

        foreach ($contracts as $index => $contract) {
            $label = $contract['key'] !== '' ? $contract['key'] : 'integration #' . ($index + 1);

            if ($contract['key'] === '') {
                $blockers[] = "{$label} needs a stable integration key.";
            }

            if ($contract['target_product_code'] === '') {
                $blockers[] = "{$label} needs a target product.";
            } elseif (! in_array($contract['target_product_code'], $existingProductCodes, true) && $contract['target_product_code'] !== (string) $product->code) {
                $blockers[] = "{$label} targets a product that does not exist: {$contract['target_product_code']}.";
            }

            if ($contract['events'] === []) {
                $blockers[] = "{$label} needs at least one emitted or consumed event.";
            }

            if ($contract['target_route_slug'] === '') {
                $warnings[] = "{$label} has no target runtime route slug; runtime navigation will not be available.";
            }

            if ($contract['payload_schema'] === []) {
                $warnings[] = "{$label} has no payload schema hints; future integrations may need manual interpretation.";
            }

            if ($contract['source_capabilities'] === []) {
                $warnings[] = "{$label} has no source capabilities declared.";
            }

            if ($contract['target_capabilities'] === []) {
                $warnings[] = "{$label} has no target capabilities declared.";
            }
        }

        return [
            'ready' => $blockers === [],
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
            'contracts' => $contracts->all(),
            'declared_intent' => $declaredIntent->all(),
            'summary' => [
                'declared_intent_count' => $declaredIntent->count(),
                'contract_count' => $contracts->count(),
                'event_count' => $contracts->sum(fn (array $contract): int => count($contract['events'])),
                'blocking_count' => count(array_unique($blockers)),
                'warning_count' => count(array_unique($warnings)),
            ],
        ];
    }

    protected function normalize(array $integration): array
    {
        return [
            'key' => trim((string) ($integration['key'] ?? '')),
            'target_product_code' => trim((string) ($integration['target_product_code'] ?? '')),
            'target_route_slug' => trim((string) ($integration['target_route_slug'] ?? '')),
            'title' => trim((string) ($integration['title'] ?? '')),
            'description' => trim((string) ($integration['description'] ?? '')),
            'target_label' => trim((string) ($integration['target_label'] ?? '')),
            'events' => $this->listFromMixed($integration['events'] ?? []),
            'source_capabilities' => $this->listFromMixed($integration['source_capabilities'] ?? []),
            'target_capabilities' => $this->listFromMixed($integration['target_capabilities'] ?? []),
            'payload_schema' => $this->payloadSchema($integration['payload_schema'] ?? []),
        ];
    }

    protected function hasAnyContractField(array $integration): bool
    {
        return collect($integration)
            ->reject(fn ($value, string $key): bool => in_array($key, ['payload_schema', 'events', 'source_capabilities', 'target_capabilities'], true) && $value === [])
            ->filter(fn ($value): bool => is_array($value) ? $value !== [] : filled($value))
            ->isNotEmpty();
    }

    protected function listFromMixed(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }

        return collect((array) $value)
            ->map(fn ($item): string => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function payloadSchema(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return collect(preg_split('/[\r\n,]+/', $value) ?: [])
                ->map(fn ($item): string => trim((string) $item))
                ->filter()
                ->mapWithKeys(function (string $item): array {
                    [$field, $type] = array_pad(array_map('trim', explode(':', $item, 2)), 2, 'mixed');

                    return $field !== '' ? [$field => $type !== '' ? $type : 'mixed'] : [];
                })
                ->all();
        }

        return collect((array) $value)
            ->filter(fn ($type, $field): bool => filled((string) $field))
            ->map(fn ($type): string => trim((string) $type) ?: 'mixed')
            ->all();
    }
}
