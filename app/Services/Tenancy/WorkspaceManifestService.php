<?php

namespace App\Services\Tenancy;

use App\Services\Admin\AppSettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class WorkspaceManifestService
{
    public function __construct(
        protected AppSettingsService $settingsService
    ) {
    }

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
        $configDefinition = (array) config("workspace_products.families.{$family}", []);
        $dynamicDefinition = (array) ($this->dynamicFamilies()[$family] ?? []);

        if ($configDefinition === []) {
            return $dynamicDefinition;
        }

        if ($dynamicDefinition === []) {
            return $configDefinition;
        }

        return array_replace_recursive($configDefinition, $dynamicDefinition);
    }

    public function familyKeys(): array
    {
        return array_values(array_unique(array_merge(
            array_keys((array) config('workspace_products.families', [])),
            array_keys($this->dynamicFamilies())
        )));
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

    public function resolveFamilyOrModuleOwner(string $key): string
    {
        $runtimeModule = $this->runtimeModule($key);

        if ($runtimeModule && filled($runtimeModule['family'] ?? null)) {
            return (string) $runtimeModule['family'];
        }

        return in_array($key, $this->familyKeys(), true)
            ? $key
            : $this->defaultFamily();
    }

    public function focusCodeFor(string $key): string
    {
        $runtimeModule = $this->runtimeModule($key);

        if ($runtimeModule && filled($runtimeModule['focus_code'] ?? null)) {
            return (string) $runtimeModule['focus_code'];
        }

        return $this->resolveFamilyOrModuleOwner($key);
    }

    public function hasAccessibleFamily(Collection $workspaceProducts, string $family): bool
    {
        return $workspaceProducts->contains(function (array $workspaceProduct) use ($family): bool {
            return $this->resolveFamilyFromText(strtolower(implode(' ', array_filter([
                (string) ($workspaceProduct['product_code'] ?? ''),
                (string) ($workspaceProduct['product_slug'] ?? ''),
                (string) ($workspaceProduct['product_name'] ?? ''),
            ])))) === $family
                && ! empty($workspaceProduct['is_accessible']);
        });
    }

    protected function dynamicFamilies(): array
    {
        if (! Schema::hasTable('app_settings')) {
            return [];
        }

        return $this->settingsService
            ->getByPrefix('workspace_products.manifest_writeback_package.', 'workspace_products')
            ->mapWithKeys(function (array $setting): array {
                $payload = (array) ($setting['value'] ?? []);
                $familyKey = (string) ($payload['family_key'] ?? '');
                $familyPayload = (array) ($payload['family_payload'] ?? []);

                return $familyKey !== ''
                    ? [$familyKey => $familyPayload]
                    : [];
            })
            ->all();
    }
}
