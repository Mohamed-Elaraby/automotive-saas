<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockItem;
use App\Models\StockTransfer;
use App\Services\Inventory\StockTransferService;
use App\Services\Tenancy\BranchScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    public function __construct(
        protected StockTransferService $stockTransferService,
        protected BranchScopeService $branchScope
    ) {
    }

public function index(Request $request)
{
    $branchIds = $this->branchScope->visibleBranchIds($request->user('automotive_admin'), 'automotive_service');
    $transfers = StockTransfer::query()
        ->with(['fromBranch', 'toBranch', 'creator'])
        ->where(function ($query) use ($branchIds): void {
            $query->whereIn('from_branch_id', $branchIds)->orWhereIn('to_branch_id', $branchIds);
        })
        ->latest('id')
        ->get();

    return view('automotive.admin.stock-transfers.index', compact('transfers'));
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

    return view('automotive.admin.stock-transfers.create', compact('branches', 'products'));
}

public function store(Request $request)
{
    $rawItems = $request->input('items', []);

    $filteredItems = collect($rawItems)
        ->filter(function ($item) {
            return ! empty($item['product_id']) && ! empty($item['quantity']);
        })
        ->values()
        ->all();

    $request->merge([
        'items' => $filteredItems,
    ]);

    $data = $request->validate([
        'from_branch_id' => ['required', 'exists:branches,id', 'different:to_branch_id'],
        'to_branch_id' => ['required', 'exists:branches,id'],
        'notes' => ['nullable', 'string', 'max:2000'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.product_id' => ['required', 'exists:products,id'],
        'items.*.quantity' => ['required', 'numeric', 'gt:0'],
    ]);

    $this->branchScope->assertCanAccessBranch($request->user('automotive_admin'), 'automotive_service', (int) $data['from_branch_id']);
    $this->branchScope->assertCanAccessBranch($request->user('automotive_admin'), 'automotive_service', (int) $data['to_branch_id']);

    $reference = 'TRF-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));

    $transfer = DB::transaction(function () use ($data, $reference) {
        $transfer = StockTransfer::query()->create([
            'reference' => $reference,
            'from_branch_id' => $data['from_branch_id'],
            'to_branch_id' => $data['to_branch_id'],
            'status' => 'draft',
            'transfer_date' => null,
            'created_by' => auth('automotive_admin')->id(),
            'notes' => $data['notes'] ?? null,
        ]);

        foreach ($data['items'] as $item) {
            $transfer->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_cost' => 0,
            ]);
        }

        return $transfer;
    });

    return redirect()
        ->route('automotive.admin.stock-transfers.show', $transfer)
        ->with('success', 'Stock transfer created as draft.');
}

public function show(StockTransfer $stockTransfer)
{
    $user = request()->user('automotive_admin');
    $canAccess = $this->branchScope->canAccessBranch($user, 'automotive_service', (int) $stockTransfer->from_branch_id)
        || $this->branchScope->canAccessBranch($user, 'automotive_service', (int) $stockTransfer->to_branch_id);

    abort_unless($canAccess, 403);

    $stockTransfer->load([
        'fromBranch',
        'toBranch',
        'creator',
        'items.product',
    ]);

    return view('automotive.admin.stock-transfers.show', compact('stockTransfer'));
}

public function post(StockTransfer $stockTransfer)
{
    $user = request()->user('automotive_admin');
    $canAccess = $this->branchScope->canAccessBranch($user, 'automotive_service', (int) $stockTransfer->from_branch_id)
        || $this->branchScope->canAccessBranch($user, 'automotive_service', (int) $stockTransfer->to_branch_id);

    abort_unless($canAccess, 403);

    try {
        $this->stockTransferService->postTransfer($stockTransfer);

        return redirect()
            ->route('automotive.admin.stock-transfers.show', $stockTransfer)
            ->with('success', 'Stock transfer posted successfully.');
    } catch (ValidationException $e) {
        return redirect()
            ->route('automotive.admin.stock-transfers.show', $stockTransfer)
            ->withErrors($e->errors());
    }
}
}
