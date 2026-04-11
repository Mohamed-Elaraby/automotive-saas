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
            ->filter(function (array $integration) use ($familyProducts): bool {
                return filled($integration['requires_family'] ?? null)
                    && $familyProducts->has((string) $integration['requires_family']);
            })
            ->map(function (array $integration) use ($familyProducts): array {
                $targetFamily = (string) ($integration['requires_family'] ?? '');
                $targetProduct = $familyProducts->get($targetFamily);
                $integration['target_params'] = array_merge(
                    (array) ($integration['target_params'] ?? []),
                    ['workspace_product' => $targetProduct['product_code'] ?? null]
                );

                return $integration;
            })
            ->values()
            ->all();
    }
}
