<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductRolePermission extends Model
{
    protected $table = 'product_role_permission';

    protected $fillable = [
        'product_role_id',
        'product_permission_id',
    ];

    public function role()
    {
        return $this->belongsTo(ProductRole::class, 'product_role_id');
    }

    public function permission()
    {
        return $this->belongsTo(ProductPermission::class, 'product_permission_id');
    }
}
