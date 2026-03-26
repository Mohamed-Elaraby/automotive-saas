<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'action' => trim((string) $request->input('action', '')),
            'tenant_id' => trim((string) $request->input('tenant_id', '')),
            'admin_email' => trim((string) $request->input('admin_email', '')),
            'subject_type' => trim((string) $request->input('subject_type', '')),
        ];

        $query = AdminActivityLog::query();

        if ($filters['action'] !== '') {
            $query->where('action', $filters['action']);
        }

        if ($filters['tenant_id'] !== '') {
            $query->where('tenant_id', 'like', '%' . $filters['tenant_id'] . '%');
        }

        if ($filters['admin_email'] !== '') {
            $query->where('admin_email', 'like', '%' . $filters['admin_email'] . '%');
        }

        if ($filters['subject_type'] !== '') {
            $query->where('subject_type', $filters['subject_type']);
        }

        $logs = $query
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $actionOptions = AdminActivityLog::query()
            ->select('action')
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $subjectTypeOptions = AdminActivityLog::query()
            ->select('subject_type')
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type');

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actionOptions' => $actionOptions,
            'subjectTypeOptions' => $subjectTypeOptions,
        ]);
    }

    public function show(AdminActivityLog $activityLog): View
    {
        return view('admin.activity-logs.show', [
            'activityLog' => $activityLog,
        ]);
    }
}
