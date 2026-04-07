<?php

namespace App\Http\Controllers\Automotive\Front;

use App\Models\CustomerPortalNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerPortalNotificationController
{
    public function unreadSummary(): JsonResponse
    {
        return response()->json($this->topbarPayload());
    }

    public function stream(): StreamedResponse
    {
        $pollSeconds = max(3, (int) config('notifications.portal.sse_poll_seconds', 10));

        return Response::stream(function () use ($pollSeconds) {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', false);
            @ini_set('implicit_flush', true);

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                echo "event: notifications\n";
                echo 'data: ' . json_encode($this->topbarPayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

                @ob_flush();
                @flush();

                sleep($pollSeconds);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function markRead(CustomerPortalNotification $notification, Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::guard('web')->user();
        abort_unless($user && (int) $notification->user_id === (int) $user->id, 403);

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'id' => $notification->id,
                'count' => $this->unreadCountForUser((int) $user->id),
            ]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public static function topbarData(?int $userId = null): array
    {
        try {
            return app(self::class)->topbarPayload($userId);
        } catch (\Throwable) {
            return [
                'count' => 0,
                'items' => [],
                'index_url' => route('automotive.portal'),
            ];
        }
    }

    protected function topbarPayload(?int $userId = null): array
    {
        $resolvedUserId = $userId ?: (int) optional(Auth::guard('web')->user())->id;
        if ($resolvedUserId <= 0 || ! $this->tableExists()) {
            return [
                'count' => 0,
                'items' => [],
                'index_url' => route('automotive.portal'),
            ];
        }

        $items = CustomerPortalNotification::query()
            ->where('user_id', $resolvedUserId)
            ->where('is_read', false)
            ->orderByDesc('notified_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (CustomerPortalNotification $notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'severity' => $notification->severity,
                    'is_read' => (bool) $notification->is_read,
                    'notified_at' => optional($notification->notified_at)->format('Y-m-d H:i:s'),
                    'target_url' => $notification->target_url ?: route('automotive.portal'),
                    'mark_read_url' => route('automotive.portal.notifications.mark-read', $notification->id),
                ];
            })
            ->values()
            ->all();

        return [
            'count' => $this->unreadCountForUser($resolvedUserId),
            'items' => $items,
            'index_url' => route('automotive.portal'),
        ];
    }

    protected function unreadCountForUser(int $userId): int
    {
        return CustomerPortalNotification::query()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    protected function tableExists(): bool
    {
        $connection = (string) (config('tenancy.database.central_connection') ?? config('database.default'));

        return Schema::connection($connection)->hasTable('customer_portal_notifications');
    }
}
