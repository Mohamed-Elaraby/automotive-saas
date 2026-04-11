<?php

namespace App\Services\Tenancy;

class WorkspaceManifestService
{
    public function defaultFamily(): string
    {
        return (string) config('workspace_products.default_family', 'automotive_service');
    }

    public function sharedSidebarSection(): array
    {
        return (array) config('workspace_products.shared.sidebar_section', []);
    }

    public function sharedQuickCreateActions(): array
    {
        return (array) config('workspace_products.shared.quick_create_actions', []);
    }

    public function familyDefinition(string $family): array
    {
        return (array) config("workspace_products.families.{$family}", []);
    }

    public function familyKeys(): array
    {
        return array_keys((array) config('workspace_products.families', []));
    }

    public function resolveFamilyFromText(string $haystack): string
    {
        $haystack = strtolower(trim($haystack));

        if ($haystack === '') {
            return $this->defaultFamily();
        }

        foreach ($this->familyKeys() as $family) {
            $definition = $this->familyDefinition($family);
            $aliases = array_filter(array_map('strval', (array) ($definition['aliases'] ?? [])));
            $needles = array_unique(array_merge([$family], $aliases));

            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($haystack, strtolower($needle))) {
                    return (string) $family;
                }
            }
        }

        return $this->defaultFamily();
    }

    public function experience(string $family): array
    {
        return (array) ($this->familyDefinition($family)['experience'] ?? []);
    }

    public function sidebarSection(string $family): ?array
    {
        $section = $this->familyDefinition($family)['sidebar_section'] ?? null;

        return is_array($section) ? $section : null;
    }

    public function dashboardActions(string $family): array
    {
        return (array) ($this->familyDefinition($family)['dashboard_actions'] ?? []);
    }

    public function quickCreateActions(string $family): array
    {
        return (array) ($this->familyDefinition($family)['quick_create_actions'] ?? []);
    }

    public function integrations(string $family): array
    {
        return (array) ($this->familyDefinition($family)['integrations'] ?? []);
    }

    public function runtimeModule(string $moduleKey): ?array
    {
        foreach ($this->familyKeys() as $family) {
            $module = data_get($this->familyDefinition($family), 'runtime_modules.' . $moduleKey);

            if (is_array($module)) {
                return $module + ['family' => $family];
            }
        }

        return null;
    }
}
