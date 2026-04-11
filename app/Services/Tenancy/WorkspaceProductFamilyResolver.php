<?php

namespace App\Services\Tenancy;

class WorkspaceProductFamilyResolver
{
    public function __construct(
        protected WorkspaceManifestService $workspaceManifestService
    ) {
    }

    public function resolveFromWorkspaceProduct(?array $workspaceProduct): string
    {
        $values = [
            trim((string) data_get($workspaceProduct, 'product_code')),
            trim((string) data_get($workspaceProduct, 'product_slug')),
            trim((string) data_get($workspaceProduct, 'product_name')),
        ];

        $haystack = strtolower(implode(' ', array_filter($values)));

        return $this->workspaceManifestService->resolveFamilyFromText($haystack);
    }
}
