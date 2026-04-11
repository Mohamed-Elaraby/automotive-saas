<?php

namespace App\Services\Tenancy;

class WorkspaceProductFamilyResolver
{
    public function resolveFromWorkspaceProduct(?array $workspaceProduct): string
    {
        $values = [
            trim((string) data_get($workspaceProduct, 'product_code')),
            trim((string) data_get($workspaceProduct, 'product_slug')),
            trim((string) data_get($workspaceProduct, 'product_name')),
        ];

        $haystack = strtolower(implode(' ', array_filter($values)));

        if ($haystack === '') {
            return 'automotive_service';
        }

        if (str_contains($haystack, 'account')) {
            return 'accounting';
        }

        if (
            str_contains($haystack, 'spare') ||
            str_contains($haystack, 'part') ||
            str_contains($haystack, 'inventor') ||
            str_contains($haystack, 'stock')
        ) {
            return 'parts_inventory';
        }

        if (
            str_contains($haystack, 'automotive') ||
            str_contains($haystack, 'service') ||
            str_contains($haystack, 'workshop') ||
            str_contains($haystack, 'maint')
        ) {
            return 'automotive_service';
        }

        return 'automotive_service';
    }
}
