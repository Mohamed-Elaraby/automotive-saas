<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransferItem;
use App\Services\Tenancy\TenantLimitService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(
        protected TenantLimitService $tenantLimitService
    ) {
    }

public function index()
{
    $products = Product::query()
        ->orderBy('id')
        ->get();

    $tenant = tenant();
    $limitInfo = null;

    if ($tenant) {
        $limitInfo = $this->tenantLimitService->getDecision(
            $tenant->id,
            'max_products',
            $products->count()
        );
    }

    return view('automotive.admin.products.index', compact('products', 'limitInfo'));
}

public function create()
{
    return view('automotive.admin.products.create', [
        'product' => new Product(),
    ]);
}

public function store(Request $request)
{
    $tenant = tenant();

    if ($tenant) {
        $decision = $this->tenantLimitService->getDecision(
            $tenant->id,
            'max_products',
            Product::query()->count()
        );

        if (! $decision['allowed']) {
            return redirect()
                ->route('automotive.admin.products.index')
                ->withErrors([
                    'limit' => 'Your current plan product limit has been reached.',
                ]);
        }
    }

    $data = $this->validatedData($request);

    Product::query()->create($data);

    return redirect()
        ->route('automotive.admin.products.index')
        ->with('success', 'Product created successfully.');
}

public function edit(Product $product)
{
    return view('automotive.admin.products.edit', compact('product'));
}

public function update(Request $request, Product $product)
{
    $data = $this->validatedData($request, $product->id);

    $product->update($data);

    return redirect()
        ->route('automotive.admin.products.index')
        ->with('success', 'Product updated successfully.');
}

public function destroy(Product $product)
{
    $hasInventory = Inventory::query()
        ->where('product_id', $product->id)
        ->where('quantity', '>', 0)
        ->exists();

    if ($hasInventory) {
        return redirect()
            ->route('automotive.admin.products.index')
            ->withErrors([
                'delete' => 'Cannot delete a product that still has inventory quantity.',
            ]);
    }

    $hasTransfers = StockTransferItem::query()
        ->where('product_id', $product->id)
        ->exists();

    if ($hasTransfers) {
        return redirect()
            ->route('automotive.admin.products.index')
            ->withErrors([
                'delete' => 'Cannot delete a product that is already used in stock transfers.',
            ]);
    }

    $hasMovements = StockMovement::query()
        ->where('product_id', $product->id)
        ->exists();

    if ($hasMovements) {
        return redirect()
            ->route('automotive.admin.products.index')
            ->withErrors([
                'delete' => 'Cannot delete a product that already has stock movements.',
            ]);
    }

    $product->delete();

    return redirect()
        ->route('automotive.admin.products.index')
        ->with('success', 'Product deleted successfully.');
}

protected function validatedData(Request $request, ?int $productId = null): array
{
    return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'barcode')->ignore($productId),
            ],
            'unit' => ['required', 'string', 'max:50'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'min_stock_alert' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'min_stock_alert' => $request->input('min_stock_alert', 0) ?: 0,
        ];
}
}
