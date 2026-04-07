<?php

namespace App\Data;

class CustomerPortalNotificationData
{
    public function __construct(
        public int $userId,
        public string $type,
        public string $title,
        public ?string $message = null,
        public string $severity = 'info',
        public ?string $tenantId = null,
        public ?int $productId = null,
        public ?string $targetUrl = null,
        public array $contextPayload = [],
    ) {
    }

    public function toModelAttributes(): array
    {
        return [
            'user_id' => $this->userId,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'severity' => $this->severity,
            'tenant_id' => $this->tenantId,
            'product_id' => $this->productId,
            'target_url' => $this->targetUrl,
            'context_payload' => $this->contextPayload,
            'is_read' => false,
            'read_at' => null,
            'notified_at' => now(),
        ];
    }
}
