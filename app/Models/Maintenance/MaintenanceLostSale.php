<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLostSale extends Model
{
    protected $fillable = [
        'branch_id',
        'estimate_id',
        'estimate_line_id',
        'customer_id',
        'vehicle_id',
        'item_description',
        'reason',
        'amount',
        'follow_up_date',
        'notes',
        'advisor_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'follow_up_date' => 'date',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function estimate(): BelongsTo { return $this->belongsTo(MaintenanceEstimate::class, 'estimate_id'); }
    public function estimateLine(): BelongsTo { return $this->belongsTo(MaintenanceEstimateLine::class, 'estimate_line_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function advisor(): BelongsTo { return $this->belongsTo(User::class, 'advisor_id'); }
}
