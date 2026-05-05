<?php

namespace App\Http\Controllers\Automotive\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Maintenance\MaintenanceEstimate;
use App\Models\Maintenance\MaintenanceServiceCatalogItem;
use App\Models\Maintenance\VehicleCheckIn;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Automotive\Maintenance\EstimateService;
use App\Services\Automotive\Maintenance\ServiceCatalogService;
use App\Services\Automotive\Maintenance\MaintenanceAttachmentService;
use App\Services\Automotive\Maintenance\VehicleCheckInService;
use App\Services\Automotive\Maintenance\VinOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function __construct(
        protected VehicleCheckInService $checkInService,
        protected ServiceCatalogService $serviceCatalogService,
        protected EstimateService $estimateService,
        protected MaintenanceAttachmentService $attachmentService,
        protected VinOcrService $vinOcrService
    ) {
    }

    public function index(): View
    {
        return view('automotive.admin.maintenance.index', [
            'dashboard' => $this->checkInService->dashboardData(),
            'serviceItems' => $this->serviceCatalogService->list(8),
            'estimates' => $this->estimateService->recent(8),
        ]);
    }

    public function checkInsIndex(): View
    {
        return view('automotive.admin.maintenance.check-ins.index', [
            'checkIns' => $this->checkInService->recentCheckIns(50),
        ]);
    }

    public function checkInsCreate(): View
    {
        return view('automotive.admin.maintenance.check-ins.create', $this->formContext());
    }

    public function checkInsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_type' => ['nullable', 'in:individual,fleet,company,government'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'make' => ['required_without:vehicle_id', 'nullable', 'string', 'max:255'],
            'model' => ['required_without:vehicle_id', 'nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'trim' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'plate_number' => ['nullable', 'string', 'max:255'],
            'plate_source' => ['nullable', 'string', 'max:255'],
            'plate_country' => ['nullable', 'string', 'max:255'],
            'vin_number' => ['nullable', 'string', 'max:255'],
            'vin_confirmed' => ['nullable', 'boolean'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'fuel_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'fuel_type' => ['nullable', 'string', 'max:60'],
            'transmission' => ['nullable', 'string', 'max:60'],
            'warning_lights' => ['nullable', 'string', 'max:1000'],
            'personal_belongings' => ['nullable', 'string', 'max:1000'],
            'customer_complaint' => ['nullable', 'string', 'max:5000'],
            'existing_damage_notes' => ['nullable', 'string', 'max:5000'],
            'customer_visible_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'expected_delivery_at' => ['nullable', 'date'],
            'create_work_order' => ['nullable', 'boolean'],
            'work_order_title' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'condition_items' => ['nullable', 'array'],
            'condition_items.*.vehicle_area_code' => ['nullable', 'string', 'max:80'],
            'condition_items.*.label' => ['nullable', 'string', 'max:255'],
            'condition_items.*.note_type' => ['nullable', 'in:complaint,existing_damage,inspection_note'],
            'condition_items.*.severity' => ['nullable', 'in:low,medium,high,urgent'],
            'condition_items.*.description' => ['nullable', 'string', 'max:2000'],
            'condition_items.*.customer_visible_note' => ['nullable', 'string', 'max:2000'],
            'condition_items.*.internal_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $checkIn = $this->checkInService->create($validated + [
            'created_by' => auth('automotive_admin')->id(),
            'service_advisor_id' => auth('automotive_admin')->id(),
        ]);

        return redirect()
            ->route('automotive.admin.maintenance.check-ins.show', $checkIn)
            ->with('success', __('maintenance.messages.check_in_created'));
    }

    public function checkInsShow(VehicleCheckIn $checkIn): View
    {
        $checkIn->load([
            'branch',
            'customer',
            'vehicle',
            'workOrder',
            'serviceAdvisor',
            'attachments.uploader',
            'conditionMaps.items.photo',
        ]);

        return view('automotive.admin.maintenance.check-ins.show', [
            'checkIn' => $checkIn,
        ]);
    }

    public function verifyVin(Request $request, VehicleCheckIn $checkIn): RedirectResponse
    {
        $validated = $request->validate([
            'vin_number' => ['required', 'string', 'max:255'],
            'vin_verification_method' => ['required', 'in:manual,ocr'],
            'vin_confidence_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vin_source_image_id' => ['nullable', 'integer', 'exists:maintenance_attachments,id'],
        ]);

        $this->checkInService->verifyVin($checkIn, $validated + [
            'verified_by' => auth('automotive_admin')->id(),
        ]);

        return back()->with('success', __('maintenance.messages.vin_confirmed'));
    }

    public function captureVin(Request $request, VehicleCheckIn $checkIn): JsonResponse
    {
        $validated = $request->validate([
            'vin_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $attachment = $this->attachmentService->store($checkIn, $request->file('vin_photo'), [
            'branch_id' => $checkIn->branch_id,
            'category' => 'vin',
            'notes' => __('maintenance.vin_source_photo'),
            'uploaded_by' => auth('automotive_admin')->id(),
        ]);

        $analysis = $this->vinOcrService->analyze($attachment);

        return response()->json([
            'ok' => true,
            'attachment' => [
                'id' => $attachment->id,
                'url' => $this->attachmentService->publicUrl($attachment),
                'category' => $attachment->category,
            ],
            'analysis' => $analysis,
            'message' => $analysis['detected_vin']
                ? __('maintenance.vin_detected_confirm', ['vin' => $analysis['detected_vin']])
                : __('maintenance.vin_ocr_unavailable_manual'),
        ]);
    }

    public function searchVin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vin' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'ok' => true,
            'vehicles' => $this->vinOcrService->searchVehicles($validated['vin']),
        ]);
    }

    public function serviceCatalogIndex(): View
    {
        return view('automotive.admin.maintenance.service-catalog.index', [
            'serviceItems' => $this->serviceCatalogService->list(),
        ]);
    }

    public function serviceCatalogStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'default_labor_price' => ['nullable', 'numeric', 'min:0'],
            'is_taxable' => ['nullable', 'boolean'],
            'warranty_days' => ['nullable', 'integer', 'min:0'],
            'required_skill' => ['nullable', 'string', 'max:255'],
            'required_bay_type' => ['nullable', 'string', 'max:255'],
            'is_package' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->serviceCatalogService->create($validated);

        return back()->with('success', __('maintenance.messages.service_created'));
    }

    public function estimatesIndex(): View
    {
        return view('automotive.admin.maintenance.estimates.index', [
            'estimates' => $this->estimateService->recent(50),
        ]);
    }

    public function estimatesCreate(): View
    {
        return view('automotive.admin.maintenance.estimates.create', $this->formContext() + [
            'serviceItems' => $this->serviceCatalogService->list(),
            'checkIns' => $this->checkInService->recentCheckIns(100),
            'workOrders' => WorkOrder::query()->with(['customer', 'vehicle'])->latest('id')->limit(100)->get(),
        ]);
    }

    public function estimatesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'check_in_id' => ['nullable', 'integer', 'exists:vehicle_check_ins,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'valid_until' => ['nullable', 'date'],
            'expected_delivery_at' => ['nullable', 'date'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'customer_visible_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.service_catalog_item_id' => ['nullable', 'integer', 'exists:maintenance_service_catalog_items,id'],
            'lines.*.line_type' => ['required', 'in:labor,part,package,other'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $estimate = $this->estimateService->create($validated + [
            'created_by' => auth('automotive_admin')->id(),
        ]);

        return redirect()
            ->route('automotive.admin.maintenance.estimates.show', $estimate)
            ->with('success', __('maintenance.messages.estimate_created'));
    }

    public function estimatesShow(MaintenanceEstimate $estimate): View
    {
        return view('automotive.admin.maintenance.estimates.show', [
            'estimate' => $estimate->load(['branch', 'customer', 'vehicle', 'checkIn', 'workOrder', 'lines.serviceCatalogItem']),
        ]);
    }

    protected function formContext(): array
    {
        $customers = Customer::query()->orderBy('name')->limit(200)->get();
        $vehicles = Vehicle::query()->with('customer')->latest('id')->limit(200)->get();

        return [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'customers' => $customers,
            'vehicles' => $vehicles,
            'customerOptions' => $customers->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'customer_type' => $customer->customer_type,
                'company_name' => $customer->company_name,
                'search' => strtolower(implode(' ', array_filter([
                    $customer->name,
                    $customer->phone,
                    $customer->email,
                    $customer->company_name,
                    $customer->customer_number,
                ]))),
            ])->values(),
            'vehicleOptions' => $vehicles->map(fn (Vehicle $vehicle): array => [
                'id' => $vehicle->id,
                'customer_id' => $vehicle->customer_id,
                'customer_name' => $vehicle->customer?->name,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'trim' => $vehicle->trim,
                'color' => $vehicle->color,
                'plate_number' => $vehicle->plate_number,
                'plate_source' => $vehicle->plate_source,
                'plate_country' => $vehicle->plate_country,
                'vin' => $vehicle->vin,
                'odometer' => $vehicle->odometer,
                'fuel_type' => $vehicle->fuel_type,
                'transmission' => $vehicle->transmission,
                'search' => strtolower(implode(' ', array_filter([
                    $vehicle->vehicle_number,
                    $vehicle->plate_number,
                    $vehicle->vin,
                    $vehicle->make,
                    $vehicle->model,
                    $vehicle->customer?->name,
                    $vehicle->customer?->phone,
                ]))),
            ])->values(),
            'users' => User::query()->orderBy('name')->get(),
            'vehicleAreas' => $this->vehicleAreas(),
        ];
    }

    protected function vehicleAreas(): array
    {
        return [
            'front_bumper' => __('maintenance.vehicle_areas.front_bumper'),
            'rear_bumper' => __('maintenance.vehicle_areas.rear_bumper'),
            'hood' => __('maintenance.vehicle_areas.hood'),
            'roof' => __('maintenance.vehicle_areas.roof'),
            'trunk' => __('maintenance.vehicle_areas.trunk'),
            'left_front_fender' => __('maintenance.vehicle_areas.left_front_fender'),
            'right_front_fender' => __('maintenance.vehicle_areas.right_front_fender'),
            'left_front_door' => __('maintenance.vehicle_areas.left_front_door'),
            'right_front_door' => __('maintenance.vehicle_areas.right_front_door'),
            'left_rear_door' => __('maintenance.vehicle_areas.left_rear_door'),
            'right_rear_door' => __('maintenance.vehicle_areas.right_rear_door'),
            'left_quarter_panel' => __('maintenance.vehicle_areas.left_quarter_panel'),
            'right_quarter_panel' => __('maintenance.vehicle_areas.right_quarter_panel'),
            'left_mirror' => __('maintenance.vehicle_areas.left_mirror'),
            'right_mirror' => __('maintenance.vehicle_areas.right_mirror'),
            'windshield' => __('maintenance.vehicle_areas.windshield'),
            'rear_glass' => __('maintenance.vehicle_areas.rear_glass'),
            'front_left_tire' => __('maintenance.vehicle_areas.front_left_tire'),
            'front_right_tire' => __('maintenance.vehicle_areas.front_right_tire'),
            'rear_left_tire' => __('maintenance.vehicle_areas.rear_left_tire'),
            'rear_right_tire' => __('maintenance.vehicle_areas.rear_right_tire'),
            'interior' => __('maintenance.vehicle_areas.interior'),
            'dashboard' => __('maintenance.vehicle_areas.dashboard'),
            'engine_bay' => __('maintenance.vehicle_areas.engine_bay'),
        ];
    }
}
