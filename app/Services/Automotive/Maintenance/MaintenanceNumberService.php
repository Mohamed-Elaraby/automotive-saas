<?php

namespace App\Services\Automotive\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MaintenanceNumberService
{
    public function next(string $table, string $column, string $prefix): string
    {
        $lastValue = DB::table($table)
            ->whereNotNull($column)
            ->where($column, 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->value($column);

        $next = 1;

        if (is_string($lastValue) && preg_match('/(\d+)$/', $lastValue, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%06d', $prefix, $next);
    }

    public function assignIfMissing(Model $model, string $column, string $prefix): void
    {
        if (filled($model->{$column})) {
            return;
        }

        $model->forceFill([
            $column => $this->next($model->getTable(), $column, $prefix),
        ])->save();
    }
}
