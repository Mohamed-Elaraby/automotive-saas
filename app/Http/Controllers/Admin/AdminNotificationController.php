<?php

namespace App\Http\Controllers\Admin;

use App\Data\AdminNotificationData;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\Notifications\AdminNotificationSchemaService;
use App\Services\Notifications\AdminNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminNotificationController extends Controller
{
    public function __construct(
        protected AdminNotificationSchemaService $schemaService
    ) {
    }

public function index(Request $request): View
{
    $filters = $this->filtersFromRequest($request);

    if (! $this->schemaService->tableExists()) {
        return view('admin.notifications.index', [
            'notifications' => AdminNotification::query()->whereRaw('1 = 0')->paginate(25),
            'filters' => $filters,
            'types' => collect(),
            'typeCounts' => [],
            'severities' => collect(),
            'stats' => $this->emptyStats(),
            'schemaWarnings' => ['The admin_notifications table does not exist yet.'],
        ]);
    }

    if (! $this->schemaService->hasRequiredColumns()) {
        return view('admin.notifications.index', [
            'notifications' => AdminNotification::query()->whereRaw('1 = 0')->paginate(25),
            'filters' => $filters,
            'types' => collect(),
            'typeCounts' => [],
            'severities' => collect(),
            'stats' => $this->emptyStats(),
            'schemaWarnings' => [
                'The admin_notifications table is using an older schema. Missing columns: ' . implode(', ', $this->schemaService->missingRequiredColumns()),
            ],
        ]);
    }

    $query = AdminNotification::query();
    $this->applyFilters($query, $filters);

    $notifications = $query
        ->orderByDesc('notified_at')
        ->orderByDesc('id')
        ->paginate(25)
        ->withQueryString();

    $typeCounts = AdminNotification::query()
        ->selectRaw('type, COUNT(*) as aggregate_count')
        ->groupBy('type')
        ->pluck('aggregate_count', 'type')
        ->toArray();

    return view('admin.notifications.index', [
        'notifications' => $notifications,
        'filters' => $filters,
        'types' => AdminNotification::query()->distinct()->orderBy('type')->pluck('type'),
        'typeCounts' => $typeCounts,
        'severities' => AdminNotification::query()->distinct()->orderBy('severity')->pluck('severity'),
        'stats' => [
            'total' => AdminNotification::query()->count(),
            'unread' => AdminNotification::query()->where('is_read', false)->count(),
            'active' => AdminNotification::query()->where('is_archived', false)->count(),
            'today' => AdminNotification::query()->whereDate('notified_at', now()->toDateString())->count(),
            'errors' => AdminNotification::query()->where('severity', 'error')->count(),
            'warnings' => AdminNotification::query()->where('severity', 'warning')->count(),
            'successes' => AdminNotification::query()->where('severity', 'success')->count(),
        ],
        'schemaWarnings' => [],
    ]);
}

public function show(AdminNotification $notification): View
{
    if ($this->schemaService->hasColumn('is_read') && ! $notification->is_read) {
        $payload = ['is_read' => true];

        if ($this->schemaService->hasColumn('read_at')) {
            $payload['read_at'] = now();
        }

        $notification->update($payload);
    }

    return view('admin.notifications.show', [
        'notification' => $notification->fresh(),
    ]);
}

public function markRead(AdminNotification $notification, Request $request): RedirectResponse|JsonResponse
    {
        if (! $this->schemaService->hasColumn('is_read')) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Read tracking columns are missing.'], 422);
            }

            return back()->with('error', 'Read tracking columns are missing from admin_notifications.');
        }

        $payload = ['is_read' => true];

        if ($this->schemaService->hasColumn('read_at')) {
            $payload['read_at'] = now();
        }

        $notification->update($payload);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'id' => $notification->id,
                'count' => AdminNotification::query()
                    ->where('is_archived', false)
                    ->where('is_read', false)
                    ->count(),
            ]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function archive(AdminNotification $notification): RedirectResponse
{
    if (! $this->schemaService->hasColumn('is_archived')) {
        return back()->with('error', 'Archive tracking columns are missing from admin_notifications.');
    }

    $payload = [
        'is_archived' => true,
    ];

    if ($this->schemaService->hasColumn('archived_at')) {
        $payload['archived_at'] = now();
    }

    if ($this->schemaService->hasColumn('is_read')) {
        $payload['is_read'] = true;
    }

    if ($this->schemaService->hasColumn('read_at')) {
        $payload['read_at'] = $notification->read_at ?: now();
    }

    $notification->update($payload);

    return back()->with('success', 'Notification archived successfully.');
}

    public function destroy(AdminNotification $notification): RedirectResponse
{
    $notification->delete();

    return back()->with('success', 'Notification deleted successfully.');
}

    public function destroyAll(Request $request): RedirectResponse
{
    if (! $this->schemaService->tableExists()) {
        return back()->with('error', 'Notification table does not exist.');
    }

    $filters = $this->filtersFromRequest($request);

    $query = AdminNotification::query();
    $this->applyFilters($query, $filters);

    $deletedCount = (clone $query)->count();
    $query->delete();

    return redirect()
        ->route('admin.notifications.index')
        ->with('success', "Deleted {$deletedCount} notification(s) from the current view.");
}

    public function bulkAction(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'action' => ['required', 'in:mark_read,archive,delete'],
        'selected_ids' => ['required', 'array', 'min:1'],
        'selected_ids.*' => ['integer'],
    ]);

    $notifications = AdminNotification::query()
        ->whereIn('id', $validated['selected_ids'])
        ->get();

    $count = $notifications->count();

    if ($count === 0) {
        return back()->with('error', 'No notifications were selected.');
    }

    if ($validated['action'] === 'mark_read') {
        foreach ($notifications as $notification) {
            $payload = ['is_read' => true];

            if ($this->schemaService->hasColumn('read_at')) {
                $payload['read_at'] = now();
            }

            $notification->update($payload);
        }

        return back()->with('success', "Marked {$count} notification(s) as read.");
    }

    if ($validated['action'] === 'archive') {
        foreach ($notifications as $notification) {
            $payload = ['is_archived' => true];

            if ($this->schemaService->hasColumn('archived_at')) {
                $payload['archived_at'] = now();
            }

            if ($this->schemaService->hasColumn('is_read')) {
                $payload['is_read'] = true;
            }

            if ($this->schemaService->hasColumn('read_at')) {
                $payload['read_at'] = $notification->read_at ?: now();
            }

            $notification->update($payload);
        }

        return back()->with('success', "Archived {$count} notification(s).");
    }

    $notifications->each->delete();

    return back()->with('success', "Deleted {$count} notification(s).");
}

    public function seedDemo(AdminNotificationService $notificationService): RedirectResponse
{
    $demoItems = [
        new AdminNotificationData(
            type: 'system_error',
                title: 'System Error Detected',
                message: 'A simulated server exception was captured for UI testing.',
                severity: 'error',
                sourceType: 'demo',
                sourceId: null,
                routeName: 'admin.system-errors.index',
                routeParams: [],
                targetUrl: null,
                tenantId: 'demo-tenant-01',
                userId: null,
                userEmail: 'demo-admin@example.com',
                contextPayload: ['demo' => true, 'event' => 'system_error']
            ),
            new AdminNotificationData(
                type: 'billing',
                title: 'Subscription Synced from Stripe',
                message: 'Subscription #101 was manually synced from Stripe.',
                severity: 'info',
                sourceType: 'subscription',
                sourceId: 101,
                routeName: 'admin.subscriptions.index',
                routeParams: [],
                targetUrl: null,
                tenantId: 'demo-tenant-02',
                userId: null,
                userEmail: 'billing@example.com',
                contextPayload: ['demo' => true, 'event' => 'manual_sync']
            ),
            new AdminNotificationData(
                type: 'billing',
                title: 'Subscription State Refreshed',
                message: 'Subscription #102 state was refreshed manually.',
                severity: 'info',
                sourceType: 'subscription',
                sourceId: 102,
                routeName: 'admin.subscriptions.index',
                routeParams: [],
                targetUrl: null,
                tenantId: 'demo-tenant-03',
                userId: null,
                userEmail: 'billing@example.com',
                contextPayload: ['demo' => true, 'event' => 'manual_refresh_state']
            ),
            new AdminNotificationData(
                type: 'billing',
                title: 'Lifecycle Normalization Applied',
                message: 'Lifecycle normalization applied to subscription #103.',
                severity: 'warning',
                sourceType: 'subscription',
                sourceId: 103,
                routeName: 'admin.subscriptions.index',
                routeParams: [],
                targetUrl: null,
                tenantId: 'demo-tenant-04',
                userId: null,
                userEmail: 'billing@example.com',
                contextPayload: ['demo' => true, 'event' => 'manual_normalize_lifecycle']
            ),
            new AdminNotificationData(
                type: 'billing',
                title: 'Trial Ending Soon',
                message: 'Tenant demo-tenant-05 trial is ending within 2 days.',
                severity: 'warning',
                sourceType: 'subscription',
                sourceId: 104,
                routeName: 'admin.subscriptions.index',
                routeParams: [],
                targetUrl: null,
                tenantId: 'demo-tenant-05',
                userId: null,
                userEmail: 'billing@example.com',
                contextPayload: ['demo' => true, 'event' => 'trial_ending']
            ),
            new AdminNotificationData(
                type: 'billing',
                title: 'Subscription Suspended',
                message: 'Tenant demo-tenant-06 subscription was suspended after grace period.',
                severity: 'error',
                sourceType: 'subscription',
                sourceId: 105,
                routeName: 'admin.subscriptions.index',
                routeParams: [],
                targetUrl: null,
                tenantId: 'demo-tenant-06',
                userId: null,
                userEmail: 'billing@example.com',
                contextPayload: ['demo' => true, 'event' => 'suspended']
            ),
            new AdminNotificationData(
                type: 'billing',
                title: 'Subscription Recovered',
                message: 'Tenant demo-tenant-07 subscription recovered successfully.',
                severity: 'success',
                sourceType: 'subscription',
                sourceId: 106,
                routeName: 'admin.subscriptions.index',
                routeParams: [],
                targetUrl: null,
                tenantId: 'demo-tenant-07',
                userId: null,
                userEmail: 'billing@example.com',
                contextPayload: ['demo' => true, 'event' => 'recovered']
            ),
        ];

        foreach ($demoItems as $item) {
            $notificationService->create($item);
        }

        return redirect()
            ->route('admin.notifications.index')
            ->with('success', 'Demo notifications generated successfully.');
    }

    public function clearDemo(): RedirectResponse
{
    AdminNotification::query()
        ->whereJsonContains('context_payload->demo', true)
        ->delete();

    return redirect()
        ->route('admin.notifications.index')
        ->with('success', 'Demo notifications removed successfully.');
}

    public function unreadSummary(): JsonResponse
{
    return response()->json($this->topbarPayload());
}

    public function stream(): StreamedResponse
{
    $pollSeconds = max(3, (int) config('notifications.admin.sse_poll_seconds', 10));

    return Response::stream(function () use ($pollSeconds) {
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        @ini_set('implicit_flush', true);

        while (true) {
            if (connection_aborted()) {
                break;
            }

            $payload = $this->topbarPayload();

            echo "event: notifications\n";
            echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

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

    public static function topbarData(): array
{
    try {
        return app(self::class)->topbarPayload();
    } catch (\Throwable $e) {
        return [
            'count' => 0,
            'items' => [],
            'index_url' => route('admin.notifications.index'),
        ];
    }
}

    protected function topbarPayload(): array
{
    if (! $this->schemaService->tableExists() || ! $this->schemaService->hasRequiredColumns()) {
        return [
            'count' => 0,
            'items' => [],
            'index_url' => route('admin.notifications.index'),
        ];
    }

    $items = AdminNotification::query()
        ->where('is_archived', false)
        ->where('is_read', false)
        ->orderByDesc('notified_at')
        ->orderByDesc('id')
        ->limit(8)
        ->get()
        ->map(function (AdminNotification $notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'severity' => $notification->severity,
                'is_read' => (bool) $notification->is_read,
                'notified_at' => optional($notification->notified_at)->format('Y-m-d H:i:s'),
                'show_url' => route('admin.notifications.show', $notification->id),
                'mark_read_url' => route('admin.notifications.mark-read', $notification->id),
                'target_url' => $notification->resolvedUrl(),
            ];
        })
        ->values()
        ->all();

    return [
        'count' => AdminNotification::query()
            ->where('is_archived', false)
            ->where('is_read', false)
            ->count(),
        'items' => $items,
        'index_url' => route('admin.notifications.index'),
    ];
}

    protected function filtersFromRequest(Request $request): array
{
    return [
        'search' => trim((string) $request->string('search')),
        'tab' => trim((string) $request->string('tab', 'all')),
        'type' => trim((string) $request->string('type')),
        'severity' => trim((string) $request->string('severity')),
        'is_read' => (string) $request->string('is_read'),
        'is_archived' => (string) $request->string('is_archived'),
    ];
}

    protected function applyFilters(Builder $query, array $filters): void
{
    if (($filters['tab'] ?? 'all') !== '' && ($filters['tab'] ?? 'all') !== 'all') {
        $query->where('type', $filters['tab']);
    }

    if (($filters['search'] ?? '') !== '') {
        $search = $filters['search'];

        $query->where(function ($builder) use ($search) {
            $builder->where('title', 'like', '%' . $search . '%')
                ->orWhere('message', 'like', '%' . $search . '%')
                ->orWhere('user_email', 'like', '%' . $search . '%')
                ->orWhere('tenant_id', 'like', '%' . $search . '%');
        });
    }

    if (($filters['type'] ?? '') !== '') {
        $query->where('type', $filters['type']);
    }

    if (($filters['severity'] ?? '') !== '') {
        $query->where('severity', $filters['severity']);
    }

    if (($filters['is_read'] ?? '') !== '') {
        $query->where('is_read', (bool) $filters['is_read']);
    }

    if (($filters['is_archived'] ?? '') !== '') {
        $query->where('is_archived', (bool) $filters['is_archived']);
    }
}

    protected function emptyStats(): array
{
    return [
        'total' => 0,
        'unread' => 0,
        'active' => 0,
        'today' => 0,
        'errors' => 0,
        'warnings' => 0,
        'successes' => 0,
    ];
}
}
