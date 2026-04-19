<?php

namespace App\Services\Tenancy;

use Illuminate\Support\Collection;
class WorkspaceIntegrationCatalogService
{
    public function __construct(
        protected WorkspaceProductFamilyResolver $workspaceProductFamilyResolver,
        protected WorkspaceManifestService $workspaceManifestService
    ) {
    }

    public function getIntegrations(Collection $workspaceProducts, ?array $focusedProduct): array
    {
        $focusedFamily = $this->workspaceProductFamilyResolver->resolveFromWorkspaceProduct($focusedProduct);
        $familyProducts = $workspaceProducts
            ->filter(fn (array $product) => ! empty($product['is_accessible']))
            ->mapWithKeys(fn (array $product) => [
                $this->workspaceProductFamilyResolver->resolveFromWorkspaceProduct($product) => $product,
            ]);

        return collect($this->workspaceManifestService->integrations($focusedFamily))
            ->map(fn (array $integration): array => $this->normalizeIntegration($integration))
            ->filter(function (array $integration) use ($familyProducts): bool {
                return filled($integration['target_family'] ?? null)
                    && filled($integration['target_route'] ?? null)
                    && $familyProducts->has((string) $integration['target_family']);
            })
            ->map(function (array $integration) use ($familyProducts): array {
                $targetFamily = (string) ($integration['target_family'] ?? '');
                $targetProduct = $familyProducts->get($targetFamily);
                $integration['target_params'] = array_merge(
                    (array) ($integration['target_params'] ?? []),
                    ['workspace_product' => $targetProduct['product_code'] ?? null]
                );
                $integration['target_params'] = array_filter(
                    $integration['target_params'],
                    fn ($value): bool => filled($value)
                );
                $integration['target_product'] = $targetProduct;
                $integration['target_product_code'] = (string) ($targetProduct['product_code'] ?? '');
                $integration['target_product_name'] = (string) ($targetProduct['product_name'] ?? $targetFamily);
                $integration['target_status_label'] = (string) ($targetProduct['status_label'] ?? 'ACTIVE');

                return $integration;
            })
            ->unique(fn (array $integration): string => implode('|', [
                (string) ($integration['key'] ?? ''),
                (string) ($integration['target_family'] ?? ''),
                (string) ($integration['target_route'] ?? ''),
            ]))
            ->values()
            ->all();
    }

    protected function normalizeIntegration(array $integration): array
    {
        $targetFamily = trim((string) ($integration['target_family'] ?? $integration['requires_family'] ?? ''));
        $targetProductCode = trim((string) ($integration['target_product_code'] ?? ''));

        if ($targetFamily === '' && $targetProductCode !== '') {
            $targetFamily = $this->workspaceManifestService->resolveFamilyFromText($targetProductCode);
        }

        return [
            'key' => trim((string) ($integration['key'] ?? '')),
            'target_family' => $targetFamily,
            'title' => trim((string) ($integration['title'] ?? 'Connected product integration')),
            'description' => trim((string) ($integration['description'] ?? '')),
            'target_label' => trim((string) ($integration['target_label'] ?? 'Open Product')),
            'target_route' => trim((string) ($integration['target_route'] ?? '')),
            'target_params' => (array) ($integration['target_params'] ?? []),
        ];
    }
}
