<?php

namespace App\Models\Maintenance;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkspaceIntegrationHandoff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePartsRequest extends Model
{
    protected $fillable = [
        'request_number',
        'branch_id',
        'work_order_id',
        'job_id',
        'vehicle_id',
        'customer_id',
        'product_id',
        'stock_movement_id',
        'handoff_id',
        'status',
        'source',
        'part_name',
        'part_number',
        'supplier_name',
        'quantity',
        'unit_price',
        'total_price',
        'needed_by',
        'notes',
        'internal_notes',
        'requested_by',
        'approved_by',
        'approved_at',
        'fulfilled_at',
        'cancelled_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'needed_by' => 'date',
        'approved_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
    public function job(): BelongsTo { return $this->belongsTo(MaintenanceWorkOrderJob::class, 'job_id'); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function product(): BelongsTo { return $this->belongsTo(StockItem::class, 'product_id'); }
    public function stockMovement(): BelongsTo { return $this->belongsTo(StockMovement::class); }
    public function handoff(): BelongsTo { return $this->belongsTo(WorkspaceIntegrationHandoff::class, 'handoff_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
