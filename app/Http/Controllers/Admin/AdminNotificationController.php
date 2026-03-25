<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\Notifications\AdminNotificationSchemaService;
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
    $filters = [
        'search' => trim((string) $request->string('search')),
        'type' => trim((string) $request->string('type')),
        'severity' => trim((string) $request->string('severity')),
        'is_read' => (string) $request->string('is_read'),
        'is_archived' => (string) $request->string('is_archived'),
    ];

    if (! $this->schemaService->tableExists()) {
        return view('admin.notifications.index', [
            'notifications' => AdminNotification::query()->whereRaw('1 = 0')->paginate(25),
            'filters' => $filters,
            'types' => collect(),
            'severities' => collect(),
            'stats' => [
                'total' => 0,
                'unread' => 0,
                'active' => 0,
                'today' => 0,
            ],
            'schemaWarnings' => ['The admin_notifications table does not exist yet.'],
        ]);
    }

    if (! $this->schemaService->hasRequiredColumns()) {
        return view('admin.notifications.index', [
            'notifications' => AdminNotification::query()->whereRaw('1 = 0')->paginate(25),
            'filters' => $filters,
            'types' => collect(),
            'severities' => collect(),
            'stats' => [
                'total' => 0,
                'unread' => 0,
                'active' => 0,
                'today' => 0,
            ],
            'schemaWarnings' => [
                'The admin_notifications table is using an older schema. Missing columns: ' . implode(', ', $this->schemaService->missingRequiredColumns()),
            ],
        ]);
    }

    $query = AdminNotification::query();

    if ($filters['search'] !== '') {
        $search = $filters['search'];

        $query->where(function ($builder) use ($search) {
            $builder->where('title', 'like', '%' . $search . '%')
                ->orWhere('message', 'like', '%' . $search . '%')
                ->orWhere('user_email', 'like', '%' . $search . '%')
                ->orWhere('tenant_id', 'like', '%' . $search . '%');
        });
    }

    if ($filters['type'] !== '') {
        $query->where('type', $filters['type']);
    }

    if ($filters['severity'] !== '') {
        $query->where('severity', $filters['severity']);
    }

    if ($filters['is_read'] !== '') {
        $query->where('is_read', (bool) $filters['is_read']);
    }

    if ($filters['is_archived'] !== '') {
        $query->where('is_archived', (bool) $filters['is_archived']);
    }

    $notifications = $query
        ->orderByDesc('notified_at')
        ->orderByDesc('id')
        ->paginate(25)
        ->withQueryString();

    return view('admin.notifications.index', [
        'notifications' => $notifications,
        'filters' => $filters,
        'types' => AdminNotification::query()->distinct()->orderBy('type')->pluck('type'),
        'severities' => AdminNotification::query()->distinct()->orderBy('severity')->pluck('severity'),
        'stats' => [
            'total' => AdminNotification::query()->count(),
            'unread' => AdminNotification::query()->where('is_read', false)->count(),
            'active' => AdminNotification::query()->where('is_archived', false)->count(),
            'today' => AdminNotification::query()->whereDate('notified_at', now()->toDateString())->count(),
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

public function markRead(AdminNotification $notification): RedirectResponse
{
    if (! $this->schemaService->hasColumn('is_read')) {
        return back()->with('error', 'Read tracking columns are missing from admin_notifications.');
    }

    $payload = ['is_read' => true];

    if ($this->schemaService->hasColumn('read_at')) {
        $payload['read_at'] = now();
    }

    $notification->update($payload);

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
            'items' => collect(),
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
}
