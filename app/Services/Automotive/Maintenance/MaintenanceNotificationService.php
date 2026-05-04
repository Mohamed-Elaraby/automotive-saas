<?php

namespace App\Services\Automotive\Maintenance;

use App\Models\Maintenance\MaintenanceNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MaintenanceNotificationService
{
    public function create(string $eventType, string $title, array $data = []): MaintenanceNotification
    {
        return MaintenanceNotification::query()->create([
            'branch_id' => $data['branch_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'channel' => $data['channel'] ?? 'branch',
            'event_type' => $eventType,
            'title' => $title,
            'message' => $data['message'] ?? null,
            'severity' => $data['severity'] ?? 'info',
            'notifiable_type' => isset($data['notifiable']) && $data['notifiable'] instanceof Model ? $data['notifiable']::class : ($data['notifiable_type'] ?? null),
            'notifiable_id' => isset($data['notifiable']) && $data['notifiable'] instanceof Model ? $data['notifiable']->getKey() : ($data['notifiable_id'] ?? null),
            'payload' => $data['payload'] ?? null,
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
}
