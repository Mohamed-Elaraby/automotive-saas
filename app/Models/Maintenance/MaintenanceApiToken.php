<?php

namespace App\Models\Maintenance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceApiToken extends Model
{
    protected $fillable = [
        'token_name',
        'token_hash',
        'status',
        'scopes',
        'last_used_at',
        'last_used_ip',
        'created_by',
        'revoked_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(MaintenanceApiRequestLog::class, 'token_id')->latest('id');
    }

    public function allows(string $scope): bool
    {
        $scopes = $this->scopes ?: [];

        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }
}
