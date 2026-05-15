<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory;
use App\Services\Tenancy\BranchScopeService;
use Illuminate\Http\Request;

class InventoryReportController extends Controller
{
    public function __construct(
        protected BranchScopeService $branchScope
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user('automotive_admin');
        $branchId = $request->input('branch_id') ? (int) $request->input('branch_id') : null;
        $search = trim((string) $request->input('search'));
        $allowedBranchIds = $this->branchScope->visibleBranchIds($user, 'automotive_service');
        $branchId = $branchId ?: $this->branchScope->currentBranchIdForUser($user, 'automotive_service');

        if ($branchId) {
            $this->branchScope->assertCanAccessBranch($user, 'automotive_service', (int) $branchId);
        }

        $branches = Branch::query()
            ->whereIn('id', $allowedBranchIds)
            ->orderBy('name')
            ->get();

        $query = Inventory::query()
            ->with(['branch', 'product'])
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->join('branches', 'branches.id', '=', 'inventories.branch_id')
            ->select('inventories.*');

        $this->branchScope->applyAllowedBranches($query, $user, 'automotive_service', 'inventories.branch_id');

        if ($branchId) {
            $query->where('inventories.branch_id', $branchId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', '%' . $search . '%')
                    ->orWhere('products.sku', 'like', '%' . $search . '%')
                    ->orWhere('products.barcode', 'like', '%' . $search . '%')
                    ->orWhere('branches.name', 'like', '%' . $search . '%')
                    ->orWhere('branches.code', 'like', '%' . $search . '%');
            });
        }

        $inventories = $query
            ->orderBy('branches.name')
            ->orderBy('products.name')
            ->get();

        return view('automotive.admin.inventory-reports.index', compact(
            'inventories',
            'branches',
            'branchId',
            'search'
        ));
    }
}
