<?php

namespace App\Services\Tenancy;

class WorkspaceModuleCatalogService
{
    public function __construct(
        protected WorkspaceProductFamilyResolver $workspaceProductFamilyResolver,
        protected WorkspaceManifestService $workspaceManifestService
    ) {
    }

    public function getFocusedProductFamily(?array $focusedProduct): string
    {
        return $this->workspaceProductFamilyResolver->resolveFromWorkspaceProduct($focusedProduct);
    }

    public function workspaceQuery(?array $focusedProduct): array
    {
        $productCode = trim((string) data_get($focusedProduct, 'product_code'));

        return $productCode !== '' ? ['workspace_product' => $productCode] : [];
    }

    public function getQuickCreateActions(?array $focusedProduct): array
    {
        $query = $this->workspaceQuery($focusedProduct);
        $sharedActions = $this->withQueryParams(
            $this->workspaceManifestService->sharedQuickCreateActions(),
            $query
        );

        return $this->dedupeItems(array_merge(
            $sharedActions,
            $this->withQueryParams(
                $this->workspaceManifestService->quickCreateActions($this->getFocusedProductFamily($focusedProduct)),
                $query
            )
        ));
    }

    public function getSidebarSections(?array $focusedProduct): array
    {
        $query = $this->workspaceQuery($focusedProduct);
        $sharedSection = $this->workspaceManifestService->sharedSidebarSection();
        $sections = [];

        if ($sharedSection !== []) {
            $sharedSection['items'] = $this->dedupeItems($this->withQueryParams(
                (array) ($sharedSection['items'] ?? []),
                $query
            ));
            $sections[] = $sharedSection;
        }

        $productSection = $this->workspaceManifestService->sidebarSection($this->getFocusedProductFamily($focusedProduct));

        if ($productSection !== null) {
            $productSection['items'] = $this->dedupeItems($this->withQueryParams(
                (array) ($productSection['items'] ?? []),
                $query
            ));
            $sections[] = $productSection;
        }

        return $sections;
    }

    public function getDashboardActions(?array $focusedProduct): array
    {
        $query = $this->workspaceQuery($focusedProduct);
        return $this->dedupeItems($this->withQueryParams(
            $this->workspaceManifestService->dashboardActions($this->getFocusedProductFamily($focusedProduct)),
            $query
        ));
    }

    public function getFocusedProductExperience(?array $focusedProduct): array
    {
        return $this->workspaceManifestService->experience($this->getFocusedProductFamily($focusedProduct));
    }

    protected function dedupeItems(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item) => trim((string) ($item['key'] ?? '')) !== '')
            ->unique('key')
            ->values()
            ->all();
    }

    protected function withQueryParams(array $items, array $query): array
    {
        return collect($items)
            ->map(function (array $item) use ($query): array {
                $item['params'] = array_merge($query, (array) ($item['params'] ?? []));

                return $item;
            })
            ->values()
            ->all();
    }

}
