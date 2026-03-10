<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\StockTransfer;
use App\Services\Tenancy\TenantLimitService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function __construct(
        protected TenantLimitService $tenantLimitService
    ) {
    }

public function index()
{
    $branches = Branch::query()
        ->orderBy('id')
        ->get();

    $tenant = tenant();
    $limitInfo = null;

    if ($tenant) {
        $limitInfo = $this->tenantLimitService->getDecision(
            $tenant->id,
            'max_branches',
            $branches->count()
        );
    }

    return view('automotive.admin.branches.index', compact('branches', 'limitInfo'));
}

public function create()
{
    return view('automotive.admin.branches.create', [
        'branch' => new Branch(),
    ]);
}

public function store(Request $request)
{
    $tenant = tenant();

    if ($tenant) {
        $decision = $this->tenantLimitService->getDecision(
            $tenant->id,
            'max_branches',
            Branch::query()->count()
        );

        if (! $decision['allowed']) {
            return redirect()
                ->route('automotive.admin.branches.index')
                ->withErrors([
                    'limit' => 'Your current plan branch limit has been reached.',
                ]);
        }
    }

    $data = $this->validatedData($request);

    Branch::query()->create($data);

    return redirect()
        ->route('automotive.admin.branches.index')
        ->with('success', 'Branch created successfully.');
}

public function edit(Branch $branch)
{
    return view('automotive.admin.branches.edit', compact('branch'));
}

public function update(Request $request, Branch $branch)
{
    $data = $this->validatedData($request, $branch->id);

    $branch->update($data);

    return redirect()
        ->route('automotive.admin.branches.index')
        ->with('success', 'Branch updated successfully.');
}

public function destroy(Branch $branch)
{
    $hasInventory = Inventory::query()
        ->where('branch_id', $branch->id)
        ->where('quantity', '>', 0)
        ->exists();

    if ($hasInventory) {
        return redirect()
            ->route('automotive.admin.branches.index')
            ->withErrors([
                'delete' => 'Cannot delete a branch that still has inventory quantity.',
            ]);
    }

    $hasTransfers = StockTransfer::query()
        ->where('from_branch_id', $branch->id)
        ->orWhere('to_branch_id', $branch->id)
        ->exists();

    if ($hasTransfers) {
        return redirect()
            ->route('automotive.admin.branches.index')
            ->withErrors([
                'delete' => 'Cannot delete a branch that is already used in stock transfers.',
            ]);
    }

    $branch->delete();

    return redirect()
        ->route('automotive.admin.branches.index')
        ->with('success', 'Branch deleted successfully.');
}

protected function validatedData(Request $request, ?int $branchId = null): array
{
    return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'code')->ignore($branchId),
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];
}
}
