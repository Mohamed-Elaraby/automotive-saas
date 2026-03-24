<?php

namespace App\Data;

class AdminNotificationData
{
    public function __construct(
        public string $type,
        public string $title,
        public ?string $message = null,
        public string $severity = 'info',
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?string $routeName = null,
        public array $routeParams = [],
        public ?string $targetUrl = null,
        public ?string $tenantId = null,
        public ?int $userId = null,
        public ?string $userEmail = null,
        public array $contextPayload = [],
    ) {
    }

public function toModelAttributes(): array
{
    return [
        'type' => $this->type,
        'title' => $this->title,
        'message' => $this->message,
        'severity' => $this->severity,
        'source_type' => $this->sourceType,
        'source_id' => $this->sourceId,
        'route_name' => $this->routeName,
        'route_params' => $this->routeParams,
        'target_url' => $this->targetUrl,
        'tenant_id' => $this->tenantId,
        'user_id' => $this->userId,
        'user_email' => $this->userEmail,
        'context_payload' => $this->contextPayload,
        'is_read' => false,
        'read_at' => null,
        'is_archived' => false,
        'archived_at' => null,
        'notified_at' => now(),
    ];
}
}
