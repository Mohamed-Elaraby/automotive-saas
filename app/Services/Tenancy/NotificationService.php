<?php

namespace App\Services\Tenancy;

use App\Models\NotificationTemplate;
use App\Models\TenantNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class NotificationService
{
    public const CHANNELS = ['in_app', 'email', 'whatsapp', 'sms', 'webhook'];

    public function __construct(
        protected ProductEntitlementService $entitlements,
        protected BranchScopeService $branchScope
    ) {
    }

    public function dispatchEvent(string $productKey, string $eventKey, array $payload = [], array $options = []): Collection
    {
        $channels = $options['channels'] ?? ['in_app'];

        return collect($channels)
            ->map(fn (string $channel): TenantNotification => $this->createForChannel($productKey, $eventKey, $channel, $payload, $options));
    }

    public function createInAppNotification(string $productKey, string $eventKey, array $payload = [], array $options = []): TenantNotification
    {
        return $this->createForChannel($productKey, $eventKey, 'in_app', $payload, $options);
    }

    public function renderTemplate(string $productKey, string $eventKey, string $channel, array $payload = [], ?string $language = null): array
    {
        $template = NotificationTemplate::query()
            ->active()
            ->where('tenant_id', $this->tenantId())
            ->where('product_key', $productKey)
            ->where('event_key', $eventKey)
            ->where('channel', $channel)
            ->when($language, fn ($query) => $query->where(function ($scoped) use ($language) {
                $scoped->whereNull('language')->orWhere('language', $language);
            }))
            ->orderByRaw('language is null')
            ->first();

        $subject = $template?->subject ?? (string) ($payload['title'] ?? Str::headline(str_replace('.', ' ', $eventKey)));
        $body = $template?->body ?? (string) ($payload['body'] ?? $payload['message'] ?? '');

        return [
            'subject' => $this->interpolate($subject, $payload),
            'body' => $this->interpolate($body, $payload),
        ];
    }

    public function markAsRead(TenantNotification $notification): TenantNotification
    {
        $notification->forceFill(['read_at' => now()])->save();

        return $notification->refresh();
    }

    public function archive(TenantNotification $notification): TenantNotification
    {
        $notification->forceFill(['archived_at' => now()])->save();

        return $notification->refresh();
    }

    public function visibleForUser(User $user, string $productKey, int $limit = 50): Collection
    {
        $query = TenantNotification::query()
            ->active()
            ->forProduct($productKey)
            ->latest('id');

        $this->branchScope->applyAllowedBranchesOrGlobal($query, $user, $productKey);

        return $query->limit($limit)->get();
    }

    public function channelEnabledForPlan(string $productKey, string $channel): bool
    {
        return $this->entitlements->featureEnabled($this->tenantId(), $productKey, 'notifications.' . $channel);
    }

    public function assertNotificationEntitlement(string $productKey, string $channel): void
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw new RuntimeException("Unsupported notification channel [{$channel}].");
        }

        if (! $this->channelEnabledForPlan($productKey, $channel)) {
            throw new RuntimeException("Notification channel [{$channel}] is not enabled for product [{$productKey}].");
        }
    }

    protected function createForChannel(string $productKey, string $eventKey, string $channel, array $payload, array $options): TenantNotification
    {
        if (! (bool) ($options['skip_entitlement'] ?? false)) {
            $this->assertNotificationEntitlement($productKey, $channel);
        }

        $rendered = $this->renderTemplate($productKey, $eventKey, $channel, $payload, $options['language'] ?? null);

        return TenantNotification::query()->create([
            'tenant_id' => $this->tenantId(),
            'product_key' => $productKey,
            'branch_id' => $options['branch_id'] ?? null,
            'event_key' => $eventKey,
            'channel' => $channel,
            'recipient_type' => $options['recipient_type'] ?? null,
            'recipient_id' => $options['recipient_id'] ?? null,
            'recipient_contact' => $options['recipient_contact'] ?? null,
            'title' => $options['title'] ?? $rendered['subject'],
            'body' => $options['body'] ?? $rendered['body'],
            'status' => $channel === 'in_app' ? 'delivered' : 'pending',
            'metadata' => $options['metadata'] ?? $payload,
            'sent_at' => $channel === 'in_app' ? now() : null,
        ]);
    }

    protected function interpolate(string $template, array $payload): string
    {
        return preg_replace_callback('/{{\s*([A-Za-z0-9_.-]+)\s*}}/', function (array $matches) use ($payload): string {
            return (string) data_get($payload, $matches[1], '');
        }, $template) ?? $template;
    }

    protected function tenantId(): string
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        return (string) ($tenant?->id ?? '');
    }
}
