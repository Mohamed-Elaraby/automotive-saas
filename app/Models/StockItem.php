<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'unit',
        'cost_price',
        'sale_price',
        'min_stock_alert',
        'description',
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'min_stock_alert' => 'integer',
        'is_active' => 'boolean',
    ];
}
