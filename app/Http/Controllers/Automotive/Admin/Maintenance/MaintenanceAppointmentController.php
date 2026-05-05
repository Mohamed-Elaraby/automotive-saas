<?php

namespace App\Http\Controllers\Automotive\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceAppointment;
use App\Services\Automotive\Maintenance\MaintenanceAppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceAppointmentController extends Controller
{
    public function __construct(protected MaintenanceAppointmentService $appointments)
    {
    }

    public function index(Request $request): View
    {
        $date = $request->query('date', today()->toDateString());

        return view('automotive.admin.maintenance.appointments.index', $this->appointments->formContext() + [
            'dashboard' => $this->appointments->dashboard(),
            'appointments' => $this->appointments->recent(),
            'selectedDate' => $date,
            'dayAppointments' => $this->appointments->forDate($date),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'service_advisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['required', 'in:appointment,walk_in'],
            'source' => ['nullable', 'in:in_branch,phone,whatsapp,email,portal,other'],
            'scheduled_at' => ['nullable', 'date'],
            'expected_arrival_at' => ['nullable', 'date'],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'vehicle_make' => ['required_without:vehicle_id', 'nullable', 'string', 'max:255'],
            'vehicle_model' => ['required_without:vehicle_id', 'nullable', 'string', 'max:255'],
            'vehicle_year' => ['nullable', 'string', 'max:20'],
            'plate_number' => ['nullable', 'string', 'max:255'],
            'vin_number' => ['nullable', 'string', 'max:255'],
            'service_type' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'customer_complaint' => ['nullable', 'string', 'max:5000'],
            'customer_visible_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $appointment = $this->appointments->create($validated + [
            'created_by' => auth('automotive_admin')->id(),
        ]);

        return redirect()
            ->route('automotive.admin.maintenance.appointments.index')
            ->with('success', __('maintenance.messages.appointment_created'));
    }

    public function arrived(MaintenanceAppointment $appointment): RedirectResponse
    {
        $this->appointments->markArrived($appointment, auth('automotive_admin')->id());

        return back()->with('success', __('maintenance.messages.appointment_arrived'));
    }

    public function convert(Request $request, MaintenanceAppointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'odometer' => ['nullable', 'integer', 'min:0'],
            'fuel_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_delivery_at' => ['nullable', 'date'],
            'customer_complaint' => ['nullable', 'string', 'max:5000'],
            'customer_visible_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'create_work_order' => ['nullable', 'boolean'],
            'work_order_title' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ]);

        $checkIn = $this->appointments->convertToCheckIn($appointment, $validated + [
            'created_by' => auth('automotive_admin')->id(),
            'create_work_order' => $request->boolean('create_work_order', true),
        ]);

        return redirect()
            ->route('automotive.admin.maintenance.check-ins.show', $checkIn)
            ->with('success', __('maintenance.messages.appointment_converted'));
    }

    public function cancel(MaintenanceAppointment $appointment): RedirectResponse
    {
        $this->appointments->cancel($appointment);

        return back()->with('success', __('maintenance.messages.appointment_cancelled'));
    }
}
