<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCapability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductCapabilityController extends Controller
{
    public function index(Product $product, Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'is_active' => (string) $request->string('is_active'),
        ];

        $capabilities = ProductCapability::query()
            ->where('product_id', $product->id)
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($builder) use ($search) {
                    $builder->where('code', 'like', '%' . $search . '%')
                        ->orWhere('name', 'like', '%' . $search . '%')
                        ->orWhere('slug', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->when($filters['is_active'] !== '', fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.product-capabilities.index', [
            'product' => $product,
            'capabilities' => $capabilities,
            'filters' => $filters,
        ]);
    }

    public function create(Product $product): View
    {
        return view('admin.product-capabilities.create', [
            'product' => $product,
            'capability' => new ProductCapability([
                'is_active' => true,
                'sort_order' => 0,
            ]),
        ]);
    }

    public function store(Product $product, Request $request): RedirectResponse
    {
        ProductCapability::query()->create($this->validatedData($request, $product));

        return redirect()
            ->route('admin.products.capabilities.index', $product)
            ->with('success', 'Product capability created successfully.');
    }

    public function edit(Product $product, ProductCapability $capability): View
    {
        abort_unless((int) $capability->product_id === (int) $product->id, 404);

        return view('admin.product-capabilities.edit', [
            'product' => $product,
            'capability' => $capability,
        ]);
    }

    public function update(Product $product, ProductCapability $capability, Request $request): RedirectResponse
    {
        abort_unless((int) $capability->product_id === (int) $product->id, 404);

        $capability->update($this->validatedData($request, $product, $capability->id));

        return redirect()
            ->route('admin.products.capabilities.index', $product)
            ->with('success', 'Product capability updated successfully.');
    }

    public function destroy(Product $product, ProductCapability $capability): RedirectResponse
    {
        abort_unless((int) $capability->product_id === (int) $product->id, 404);

        $capability->delete();

        return redirect()
            ->route('admin.products.capabilities.index', $product)
            ->with('success', 'Product capability deleted successfully.');
    }

    protected function validatedData(Request $request, Product $product, ?int $capabilityId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_capabilities', 'code')
                    ->where(fn ($query) => $query->where('product_id', $product->id))
                    ->ignore($capabilityId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_capabilities', 'slug')
                    ->where(fn ($query) => $query->where('product_id', $product->id))
                    ->ignore($capabilityId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        return [
            'product_id' => $product->id,
            'code' => Str::snake((string) $validated['code']),
            'name' => (string) $validated['name'],
            'slug' => Str::slug((string) $validated['slug']),
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) $validated['is_active'],
            'sort_order' => (int) $validated['sort_order'],
        ];
    }
}
