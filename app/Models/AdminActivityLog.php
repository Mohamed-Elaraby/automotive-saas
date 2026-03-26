<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_user_id',
        'admin_email',
        'action',
        'subject_type',
        'subject_id',
        'tenant_id',
        'context_payload',
    ];

    protected $casts = [
        'context_payload' => 'array',
    ];

    public function getConnectionName(): ?string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }
}
