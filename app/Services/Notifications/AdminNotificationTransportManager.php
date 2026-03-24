<?php

namespace App\Services\Notifications;

class AdminNotificationTransportManager
{
    public function currentTransport(): string
    {
        return (string) config('notifications.admin.transport', 'sse');
    }

    public function supportsRealtime(): bool
    {
        return in_array($this->currentTransport(), ['sse', 'websocket'], true);
    }

    public function isSse(): bool
    {
        return $this->currentTransport() === 'sse';
    }

    public function isWebsocket(): bool
    {
        return $this->currentTransport() === 'websocket';
    }
}
