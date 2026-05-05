<?php

namespace App\Http\Controllers\Automotive\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\Automotive\Maintenance\MaintenanceProfileService;
use Illuminate\View\View;

class MaintenanceProfileController extends Controller
{
    public function __construct(protected MaintenanceProfileService $profiles)
    {
    }

    public function customer(Customer $customer): View
    {
        return view('automotive.admin.maintenance.profiles.customer', $this->profiles->customerProfile($customer));
    }

    public function vehicle(Vehicle $vehicle): View
    {
        return view('automotive.admin.maintenance.profiles.vehicle', $this->profiles->vehicleProfile($vehicle));
    }
}
