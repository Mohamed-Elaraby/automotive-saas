<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'type' => trim((string) $request->string('type')),
            'severity' => trim((string) $request->string('severity')),
            'is_read' => (string) $request->string('is_read'),
            'is_archived' => (string) $request->string('is_archived'),
        ];

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
        ]);
    }

    public function show(AdminNotification $notification): View
    {
        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return view('admin.notifications.show', [
            'notification' => $notification->fresh(),
        ]);
    }

    public function markRead(AdminNotification $notification): RedirectResponse
    {
        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Notification marked as read.');
    }

    public function archive(AdminNotification $notification): RedirectResponse
    {
        $notification->update([
            'is_archived' => true,
            'archived_at' => now(),
            'is_read' => true,
            'read_at' => $notification->read_at ?: now(),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Notification archived successfully.');
    }

    public static function topbarData(): array
    {
        try {
            $items = AdminNotification::query()
                ->active()
                ->unread()
                ->orderByDesc('notified_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get();

            return [
                'count' => AdminNotification::query()->active()->unread()->count(),
                'items' => $items,
            ];
        } catch (\Throwable $e) {
            return [
                'count' => 0,
                'items' => collect(),
            ];
        }
    }
}
