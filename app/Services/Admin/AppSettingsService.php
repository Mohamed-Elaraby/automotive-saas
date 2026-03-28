<?php

namespace App\Services\Admin;

use App\Models\AppSetting;

class AppSettingsService
{
    public const KEY_FREE_TRIAL_ENABLED = 'onboarding.free_trial_enabled';

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = AppSetting::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return $this->castValue($setting->value, $setting->value_type, $default);
    }

    public function set(
        string $key,
        mixed $value,
        string $valueType = 'string',
        string $groupKey = 'general'
    ): AppSetting {
        return AppSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group_key' => $groupKey,
                'value' => $this->storeValue($value, $valueType),
                'value_type' => $valueType,
            ]
        );
    }

    public function freeTrialEnabled(): bool
    {
        return (bool) $this->get(self::KEY_FREE_TRIAL_ENABLED, true);
    }

    public function setFreeTrialEnabled(bool $enabled): AppSetting
    {
        return $this->set(
            key: self::KEY_FREE_TRIAL_ENABLED,
            value: $enabled,
            valueType: 'boolean',
            groupKey: 'onboarding'
        );
    }

    protected function castValue(?string $value, string $valueType, mixed $default = null): mixed
    {
        if ($value === null) {
            return $default;
        }

        return match ($valueType) {
        'boolean' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => json_decode($value, true) ?? $default,
            default => $value,
        };
    }

    protected function storeValue(mixed $value, string $valueType): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($valueType) {
        'boolean' => $value ? '1' : '0',
            'integer', 'float', 'string' => (string) $value,
            'json' => json_encode($value, JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }
}
