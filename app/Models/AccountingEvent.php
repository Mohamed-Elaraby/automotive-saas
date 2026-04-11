<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingEvent extends Model
{
    protected $fillable = [
        'event_type',
        'reference_type',
        'reference_id',
        'status',
        'event_date',
        'currency',
        'labor_amount',
        'parts_amount',
        'total_amount',
        'payload',
        'created_by',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'labor_amount' => 'decimal:2',
        'parts_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payload' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
