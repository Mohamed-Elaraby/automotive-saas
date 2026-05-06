<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePaymentRequest extends Model
{
    protected $fillable = [
        'request_number',
        'branch_id',
        'invoice_id',
        'work_order_id',
        'customer_id',
        'vehicle_id',
        'status',
        'amount',
        'currency',
        'provider',
        'payment_token',
        'payment_url',
        'expires_at',
        'paid_at',
        'cancelled_at',
        'payload',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'payload' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(MaintenanceInvoice::class, 'invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
