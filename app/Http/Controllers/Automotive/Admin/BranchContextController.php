<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Tenancy\BranchContextService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BranchContextController extends Controller
{
    public function __construct(
        protected BranchContextService $branchContext
    ) {
    }

    public function select(): View
    {
        $user = auth('automotive_admin')->user();
        $productKeys = $this->branchContext->productKeysForUser($user);

        $productRows = $productKeys
            ->map(fn (string $productKey): array => [
                'product_key' => $productKey,
                'branches' => $this->branchContext->allowedBranchesForUser($user, $productKey),
            ])
            ->values();

        return view('automotive.admin.access.branch-context.select', [
            'page' => 'access-control',
            'productRows' => $productRows,
            'branchContext' => $this->branchContext->contextForUser($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->switch($request);
    }

    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_key' => ['required', 'string', 'max:80'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        try {
            $this->branchContext->setCurrentBranch(
                auth('automotive_admin')->user(),
                $validated['product_key'],
                Branch::query()->findOrFail((int) $validated['branch_id'])
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['branch_id' => __('access.forbidden_branch_selection')]);
        }

        return redirect()
            ->route('automotive.admin.dashboard', ['workspace_product' => $validated['product_key']])
            ->with('success', __('access.branch_context_updated'));
    }

    public function clear(): RedirectResponse
    {
        $this->branchContext->clearCurrentBranch();

        return redirect()
            ->route('automotive.admin.access.branch-context.select')
            ->with('success', __('access.branch_context_cleared'));
    }
}
