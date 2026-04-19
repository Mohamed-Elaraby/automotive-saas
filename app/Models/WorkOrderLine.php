<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderLine extends Model
{
    protected $fillable = [
        'work_order_id',
        'line_type',
        'product_id',
        'stock_movement_id',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(StockItem::class, 'product_id');
    }

    public function stockMovement()
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
