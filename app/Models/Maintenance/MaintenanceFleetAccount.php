<?php

namespace App\Models\Maintenance;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceFleetAccount extends Model
{
    protected $fillable = [
        'fleet_number',
        'customer_id',
        'status',
        'contract_type',
        'contract_starts_on',
        'contract_ends_on',
        'credit_limit',
        'monthly_billing_enabled',
        'approval_required',
        'approval_limit',
        'billing_cycle_day',
        'preventive_schedule',
        'terms',
        'internal_notes',
        'created_by',
    ];

    protected $casts = [
        'contract_starts_on' => 'date',
        'contract_ends_on' => 'date',
        'credit_limit' => 'decimal:2',
        'monthly_billing_enabled' => 'boolean',
        'approval_required' => 'boolean',
        'approval_limit' => 'decimal:2',
        'preventive_schedule' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
