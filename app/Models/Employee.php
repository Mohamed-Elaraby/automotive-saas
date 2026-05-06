<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    public const TYPE_TECHNICIAN = 'technician';
    public const TYPE_SERVICE_ADVISOR = 'service_advisor';
    public const TYPE_ACCOUNTANT = 'accountant';
    public const TYPE_MANAGER = 'manager';
    public const TYPE_DRIVER = 'driver';
    public const TYPE_WORKER = 'worker';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'phone',
        'email',
        'job_title',
        'employee_type',
        'status',
        'metadata',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function productProfiles()
    {
        return $this->hasMany(ProductEmployeeProfile::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOfType(Builder $query, string $employeeType): Builder
    {
        return $query->where('employee_type', $employeeType);
    }
}
