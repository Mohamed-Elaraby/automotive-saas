<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\Tenancy\BranchScopeService;
use Illuminate\Database\Eloquent\Builder;

trait HasBranchScope
{
    public function scopeVisibleToUser(Builder $query, User $user, string $productKey = 'automotive_service', string $branchColumn = 'branch_id'): Builder
    {
        return app(BranchScopeService::class)->applyAllowedBranches($query, $user, $productKey, $branchColumn);
    }

    public function scopeVisibleToUserOrGlobal(Builder $query, User $user, string $productKey = 'automotive_service', string $branchColumn = 'branch_id'): Builder
    {
        return app(BranchScopeService::class)->applyAllowedBranchesOrGlobal($query, $user, $productKey, $branchColumn);
    }

    public function scopeForAllowedBranches(Builder $query, array $branchIds, string $branchColumn = 'branch_id'): Builder
    {
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($branchColumn, $branchIds);
    }

    public function scopeForCurrentBranch(Builder $query, ?int $branchId, string $branchColumn = 'branch_id'): Builder
    {
        if (! $branchId) {
            return $query;
        }

        return $query->where($branchColumn, $branchId);
    }
}
