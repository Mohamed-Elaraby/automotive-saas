<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryAdjustmentService;
use App\Services\Tenancy\BranchScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryAdjustmentController extends Controller
{
    public function __construct(
        protected InventoryAdjustmentService $inventoryAdjustmentService,
        protected BranchScopeService $branchScope
    ) {
    }

public function index(Request $request)
{
    $movements = StockMovement::query()
        ->with(['branch', 'product', 'creator'])
        ->whereIn('type', ['opening', 'adjustment_in', 'adjustment_out'])
        ->latest('id');

    $this->branchScope->applyAllowedBranches($movements, $request->user('automotive_admin'), 'automotive_service');

    $movements = $movements->get();

    return view('automotive.admin.inventory-adjustments.index', compact('movements'));
}

public function create(Request $request)
{
    $branches = Branch::query()
        ->whereIn('id', $this->branchScope->visibleBranchIds($request->user('automotive_admin'), 'automotive_service'))
        ->orderBy('name')
        ->get();

    $products = StockItem::query()
        ->where('is_active', true)
        ->orderBy('name')
        ->get();

    return view('automotive.admin.inventory-adjustments.create', compact('branches', 'products'));
}

public function store(Request $request)
{
    $data = $request->validate([
        'branch_id' => ['required', 'exists:branches,id'],
        'product_id' => ['required', 'exists:products,id'],
        'type' => ['required', Rule::in(['opening', 'adjustment_in', 'adjustment_out'])],
        'quantity' => ['required', 'numeric', 'gt:0'],
        'notes' => ['nullable', 'string', 'max:2000'],
    ]);

    $this->branchScope->assertCanAccessBranch($request->user('automotive_admin'), 'automotive_service', (int) $data['branch_id']);

    $data['created_by'] = auth('automotive_admin')->id();

    $this->inventoryAdjustmentService->createMovement($data);

    return redirect()
        ->route('automotive.admin.inventory-adjustments.index')
        ->with('success', 'Inventory adjustment saved successfully.');
}
}
