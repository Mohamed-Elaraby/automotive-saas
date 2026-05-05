<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class MaintenanceNotificationService
{
    public function create(string $eventType, string $title, array $data = []): MaintenanceNotification
    {
        $rule = $this->ruleFor($eventType);
        $payload = $this->sanitizePayload($data['payload'] ?? []);

        return MaintenanceNotification::query()->create([
            'branch_id' => $data['branch_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'channel' => $data['channel'] ?? ($rule['audience'] === 'customer' ? 'customer' : 'branch'),
            'event_type' => $eventType,
            'title' => $title,
            'message' => $data['message'] ?? null,
            'severity' => $data['severity'] ?? $rule['severity'],
            'notifiable_type' => isset($data['notifiable']) && $data['notifiable'] instanceof Model ? $data['notifiable']::class : ($data['notifiable_type'] ?? null),
            'notifiable_id' => isset($data['notifiable']) && $data['notifiable'] instanceof Model ? $data['notifiable']->getKey() : ($data['notifiable_id'] ?? null),
            'payload' => $payload + [
                'audience' => $rule['audience'],
                'customer_safe' => (bool) $rule['customer_safe'],
            ],
        ]);
    }

    public function unread(int $limit = 25): Collection
    {
        return MaintenanceNotification::query()
            ->with(['branch', 'user'])
            ->whereNull('read_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function streamSince(?int $lastId = null, int $limit = 50): Collection
    {
        return MaintenanceNotification::query()
            ->with('branch')
            ->when($lastId, fn ($query) => $query->where('id', '>', $lastId))
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function streamFor(?int $lastId = null, array $scope = [], int $limit = 50): Collection
    {
        return MaintenanceNotification::query()
            ->with('branch')
            ->when($lastId, fn ($query) => $query->where('id', '>', $lastId))
            ->when(! empty($scope['branch_ids']), fn ($query) => $query->where(function ($scoped) use ($scope) {
                $scoped->whereNull('branch_id')->orWhereIn('branch_id', $scope['branch_ids']);
            }))
            ->when(! empty($scope['user_id']), fn ($query) => $query->where(function ($scoped) use ($scope) {
                $scoped->whereNull('user_id')->orWhere('user_id', $scope['user_id']);
            }))
            ->when(! empty($scope['channels']), fn ($query) => $query->whereIn('channel', $scope['channels']))
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function toSsePayload(MaintenanceNotification $notification): array
    {
        $payload = $notification->payload ?? [];

        return [
            'id' => $notification->id,
            'event_type' => $notification->event_type,
            'title' => $notification->title,
            'message' => $notification->message,
            'severity' => $notification->severity,
            'channel' => $notification->channel,
            'branch' => $notification->branch?->name,
            'notifiable_type' => class_basename((string) $notification->notifiable_type),
            'notifiable_id' => $notification->notifiable_id,
            'customer_safe' => (bool) ($payload['customer_safe'] ?? false),
            'created_at' => optional($notification->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    protected function ruleFor(string $eventType): array
    {
        return config('maintenance_notifications.rules.' . $eventType, [
            'audience' => 'internal',
            'severity' => 'info',
            'customer_safe' => false,
        ]);
    }

    protected function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = config('maintenance_notifications.sensitive_payload_keys', []);

        foreach ($sensitiveKeys as $key) {
            Arr::forget($payload, $key);
        }

        return collect($payload)
            ->reject(fn ($value) => is_string($value) && strlen($value) > 500)
            ->all();
    }
}
