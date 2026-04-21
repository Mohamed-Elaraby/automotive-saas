<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingAuditEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'auditable_type',
        'auditable_id',
        'description',
        'payload',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
