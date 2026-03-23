<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemErrorLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemErrorLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'route_name' => trim((string) $request->string('route_name')),
            'is_read' => (string) $request->string('is_read'),
            'is_resolved' => (string) $request->string('is_resolved'),
            'level' => trim((string) $request->string('level')),
        ];

        $query = SystemErrorLog::query();

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('message', 'like', '%' . $search . '%')
                    ->orWhere('exception_class', 'like', '%' . $search . '%')
                    ->orWhere('request_url', 'like', '%' . $search . '%')
                    ->orWhere('user_email', 'like', '%' . $search . '%')
                    ->orWhere('tenant_id', 'like', '%' . $search . '%');
            });
        }

        if ($filters['route_name'] !== '') {
            $query->where('route_name', $filters['route_name']);
        }

        if ($filters['level'] !== '') {
            $query->where('level', $filters['level']);
        }

        if ($filters['is_read'] !== '') {
            $query->where('is_read', (bool) $filters['is_read']);
        }

        if ($filters['is_resolved'] !== '') {
            $query->where('is_resolved', (bool) $filters['is_resolved']);
        }

        $logs = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.system-errors.index', [
            'logs' => $logs,
            'filters' => $filters,
            'routeNames' => SystemErrorLog::query()
                ->whereNotNull('route_name')
                ->where('route_name', '!=', '')
                ->distinct()
                ->orderBy('route_name')
                ->pluck('route_name'),
            'levels' => SystemErrorLog::query()
                ->whereNotNull('level')
                ->where('level', '!=', '')
                ->distinct()
                ->orderBy('level')
                ->pluck('level'),
            'stats' => [
                'total' => SystemErrorLog::query()->count(),
                'unread' => SystemErrorLog::query()->where('is_read', false)->count(),
                'unresolved' => SystemErrorLog::query()->where('is_resolved', false)->count(),
                'today' => SystemErrorLog::query()->whereDate('occurred_at', now()->toDateString())->count(),
            ],
        ]);
    }

    public function show(SystemErrorLog $systemError): View
    {
        if (! $systemError->is_read) {
            $systemError->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return view('admin.system-errors.show', [
            'log' => $systemError->fresh(),
        ]);
    }

    public function markRead(SystemErrorLog $systemError): RedirectResponse
    {
        $systemError->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Error log marked as read.');
    }

    public function markResolved(SystemErrorLog $systemError): RedirectResponse
    {
        $systemError->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'is_read' => true,
            'read_at' => $systemError->read_at ?: now(),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Error log marked as resolved.');
    }
}
