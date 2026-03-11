<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryReportController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->input('branch_id');
        $search = trim((string) $request->input('search'));

        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $query = Inventory::query()
            ->with(['branch', 'product'])
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->join('branches', 'branches.id', '=', 'inventories.branch_id')
            ->select('inventories.*');

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
