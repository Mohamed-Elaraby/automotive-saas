<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemErrorLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SystemErrorLogController extends Controller
{
    public function index(Request $request): View
    {
        if (! $this->hasSystemErrorLogsTable()) {
            return view('admin.system-errors.index', [
                'logs' => SystemErrorLog::query()->whereRaw('1 = 0')->paginate(25),
                'filters' => $this->emptyFilters(),
                'routeNames' => collect(),
                'levels' => collect(),
                'stats' => $this->emptyStats(),
                'schemaWarnings' => ['The system_error_logs table does not exist yet.'],
            ]);
        }

        $filters = [
            'search' => trim((string) $request->string('search')),
            'route_name' => trim((string) $request->string('route_name')),
            'is_read' => (string) $request->string('is_read'),
            'is_resolved' => (string) $request->string('is_resolved'),
            'level' => trim((string) $request->string('level')),
        ];

        $availableColumns = $this->availableColumns();
        $schemaWarnings = $this->schemaWarnings($availableColumns);

        $query = SystemErrorLog::query();

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search, $availableColumns) {
                if ($this->hasColumn($availableColumns, 'message')) {
                    $builder->orWhere('message', 'like', '%' . $search . '%');
                }

                if ($this->hasColumn($availableColumns, 'exception_class')) {
                    $builder->orWhere('exception_class', 'like', '%' . $search . '%');
                }

                if ($this->hasColumn($availableColumns, 'request_url')) {
                    $builder->orWhere('request_url', 'like', '%' . $search . '%');
                }

                if ($this->hasColumn($availableColumns, 'user_email')) {
                    $builder->orWhere('user_email', 'like', '%' . $search . '%');
                }

                if ($this->hasColumn($availableColumns, 'tenant_id')) {
                    $builder->orWhere('tenant_id', 'like', '%' . $search . '%');
                }
            });
        }

        if ($filters['route_name'] !== '' && $this->hasColumn($availableColumns, 'route_name')) {
            $query->where('route_name', $filters['route_name']);
        }

        if ($filters['level'] !== '' && $this->hasColumn($availableColumns, 'level')) {
            $query->where('level', $filters['level']);
        }

        if ($filters['is_read'] !== '' && $this->hasColumn($availableColumns, 'is_read')) {
            $query->where('is_read', (bool) $filters['is_read']);
        }

        if ($filters['is_resolved'] !== '' && $this->hasColumn($availableColumns, 'is_resolved')) {
            $query->where('is_resolved', (bool) $filters['is_resolved']);
        }

        if ($this->hasColumn($availableColumns, 'occurred_at')) {
            $query->orderByDesc('occurred_at');
        }

        $query->orderByDesc('id');

        $logs = $query
            ->paginate(25)
            ->withQueryString();

        return view('admin.system-errors.index', [
            'logs' => $logs,
            'filters' => $filters,
            'routeNames' => $this->buildRouteNames($availableColumns),
            'levels' => $this->buildLevels($availableColumns),
            'stats' => $this->buildStats($availableColumns),
            'schemaWarnings' => $schemaWarnings,
        ]);
    }

    public function show(SystemErrorLog $systemError): View
    {
        $availableColumns = $this->availableColumns();

        if ($this->hasColumn($availableColumns, 'is_read') && ! $systemError->is_read) {
            $payload = ['is_read' => true];

            if ($this->hasColumn($availableColumns, 'read_at')) {
                $payload['read_at'] = now();
            }

            $systemError->update($payload);
        }

        return view('admin.system-errors.show', [
            'log' => $systemError->fresh(),
        ]);
    }

    public function markRead(SystemErrorLog $systemError): RedirectResponse
    {
        $availableColumns = $this->availableColumns();

        if (! $this->hasColumn($availableColumns, 'is_read')) {
            return redirect()
                ->back()
                ->with('error', 'The current system_error_logs schema does not support read tracking yet.');
        }

        $payload = ['is_read' => true];

        if ($this->hasColumn($availableColumns, 'read_at')) {
            $payload['read_at'] = now();
        }

        $systemError->update($payload);

        return redirect()
            ->back()
            ->with('success', 'Error log marked as read.');
    }

    public function markResolved(SystemErrorLog $systemError): RedirectResponse
    {
        $availableColumns = $this->availableColumns();

        if (! $this->hasColumn($availableColumns, 'is_resolved')) {
            return redirect()
                ->back()
                ->with('error', 'The current system_error_logs schema does not support resolve tracking yet.');
        }

        $payload = ['is_resolved' => true];

        if ($this->hasColumn($availableColumns, 'resolved_at')) {
            $payload['resolved_at'] = now();
        }

        if ($this->hasColumn($availableColumns, 'is_read')) {
            $payload['is_read'] = true;
        }

        if ($this->hasColumn($availableColumns, 'read_at') && empty($systemError->read_at)) {
            $payload['read_at'] = now();
        }

        $systemError->update($payload);

        return redirect()
            ->back()
            ->with('success', 'Error log marked as resolved.');
    }

    public static function topbarData(): array
    {
        try {
            $connection = (string) (config('tenancy.database.central_connection') ?? config('database.default'));

            if (! Schema::connection($connection)->hasTable('system_error_logs')) {
                return [
                    'count' => 0,
                    'items' => collect(),
                ];
            }

            $columns = Schema::connection($connection)->getColumnListing('system_error_logs');

            if (! in_array('is_read', $columns, true)) {
                return [
                    'count' => 0,
                    'items' => collect(),
                ];
            }

            $query = SystemErrorLog::query()->where('is_read', false);

            if (in_array('occurred_at', $columns, true)) {
                $query->orderByDesc('occurred_at');
            }

            $items = $query
                ->orderByDesc('id')
                ->limit(7)
                ->get();

            return [
                'count' => SystemErrorLog::query()->where('is_read', false)->count(),
                'items' => $items,
            ];
        } catch (\Throwable $e) {
            return [
                'count' => 0,
                'items' => collect(),
            ];
        }
    }

    protected function buildRouteNames(array $availableColumns)
    {
        if (! $this->hasColumn($availableColumns, 'route_name')) {
            return collect();
        }

        return SystemErrorLog::query()
            ->whereNotNull('route_name')
            ->where('route_name', '!=', '')
            ->distinct()
            ->orderBy('route_name')
            ->pluck('route_name');
    }

    protected function buildLevels(array $availableColumns)
    {
        if (! $this->hasColumn($availableColumns, 'level')) {
            return collect();
        }

        return SystemErrorLog::query()
            ->whereNotNull('level')
            ->where('level', '!=', '')
            ->distinct()
            ->orderBy('level')
            ->pluck('level');
    }

    protected function buildStats(array $availableColumns): array
    {
        $base = SystemErrorLog::query();

        return [
            'total' => (clone $base)->count(),
            'unread' => $this->hasColumn($availableColumns, 'is_read')
                ? (clone $base)->where('is_read', false)->count()
                : 0,
            'unresolved' => $this->hasColumn($availableColumns, 'is_resolved')
                ? (clone $base)->where('is_resolved', false)->count()
                : 0,
            'today' => $this->hasColumn($availableColumns, 'occurred_at')
                ? (clone $base)->whereDate('occurred_at', now()->toDateString())->count()
                : 0,
        ];
    }

    protected function schemaWarnings(array $availableColumns): array
    {
        $expectedColumns = [
            'occurred_at',
            'level',
            'exception_class',
            'message',
            'route_name',
            'request_url',
            'user_email',
            'tenant_id',
            'is_read',
            'read_at',
            'is_resolved',
            'resolved_at',
        ];

        $missing = collect($expectedColumns)
            ->reject(fn (string $column) => $this->hasColumn($availableColumns, $column))
            ->values()
            ->all();

        if (empty($missing)) {
            return [];
        }

        return [
            'The system_error_logs table is using an older schema. Missing columns: ' . implode(', ', $missing),
        ];
    }

    protected function hasSystemErrorLogsTable(): bool
    {
        $connection = $this->connectionName();

        return Schema::connection($connection)->hasTable('system_error_logs');
    }

    protected function availableColumns(): array
    {
        $connection = $this->connectionName();

        if (! Schema::connection($connection)->hasTable('system_error_logs')) {
            return [];
        }

        return Schema::connection($connection)->getColumnListing('system_error_logs');
    }

    protected function hasColumn(array $availableColumns, string $column): bool
    {
        return in_array($column, $availableColumns, true);
    }

    protected function connectionName(): string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }

    protected function emptyFilters(): array
    {
        return [
            'search' => '',
            'route_name' => '',
            'is_read' => '',
            'is_resolved' => '',
            'level' => '',
        ];
    }

    protected function emptyStats(): array
    {
        return [
            'total' => 0,
            'unread' => 0,
            'unresolved' => 0,
            'today' => 0,
        ];
    }
}
