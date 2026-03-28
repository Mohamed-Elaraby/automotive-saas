<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_key',
        'key',
        'value',
        'value_type',
    ];

    public function getConnectionName(): ?string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }
}
