<?php

namespace App\Http\Controllers\Automotive\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\VehicleCheckIn;
use App\Models\Maintenance\MaintenanceWorkOrderJob;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Automotive\Maintenance\MaintenanceAttachmentService;
use App\Services\Automotive\Maintenance\MaintenanceNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MaintenanceAttachmentController extends Controller
{
    public function __construct(
        protected MaintenanceAttachmentService $attachmentService,
        protected MaintenanceNotificationService $notifications
    )
    {
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'attachable_type' => ['required', 'in:check_in,vehicle,work_order,job'],
            'attachable_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'category' => ['required', 'string', 'max:80'],
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attachable = match ($validated['attachable_type']) {
            'check_in' => VehicleCheckIn::query()->findOrFail($validated['attachable_id']),
            'vehicle' => Vehicle::query()->findOrFail($validated['attachable_id']),
            'work_order' => WorkOrder::query()->findOrFail($validated['attachable_id']),
            'job' => MaintenanceWorkOrderJob::query()->findOrFail($validated['attachable_id']),
        };

        $attachment = $this->attachmentService->store($attachable, $request->file('photo'), [
            'branch_id' => $validated['branch_id'] ?? null,
            'category' => $validated['category'],
            'notes' => $validated['notes'] ?? null,
            'uploaded_by' => auth('automotive_admin')->id(),
        ]);

        if ($attachable instanceof MaintenanceWorkOrderJob) {
            $this->notifications->create('job.photo_uploaded', 'Job photo uploaded: ' . $attachable->job_number, [
                'branch_id' => $attachable->workOrder?->branch_id ?? ($validated['branch_id'] ?? null),
                'user_id' => $attachable->assigned_technician_id,
                'notifiable' => $attachable,
                'payload' => [
                    'job_id' => $attachable->id,
                    'job_number' => $attachable->job_number,
                    'work_order_id' => $attachable->work_order_id,
                    'category' => $attachment->category,
                    'attachment_id' => $attachment->id,
                ],
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'attachment' => [
                    'id' => $attachment->id,
                    'category' => $attachment->category,
                    'url' => $this->attachmentService->publicUrl($attachment),
                    'original_name' => $attachment->original_name,
                ],
            ]);
        }

        return back()->with('success', __('maintenance.messages.attachment_uploaded'));
    }
}
