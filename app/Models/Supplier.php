<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'display_name',
        'contact_name',
        'phone',
        'email',
        'tax_number',
        'address',
        'notes',
        'is_active',
        'status',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function productProfiles()
    {
        return $this->hasMany(ProductSupplierProfile::class);
    }
}
