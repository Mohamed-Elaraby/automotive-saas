<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductEnablementRequest;
use App\Models\TenantProductSubscription;
use App\Services\Tenancy\WorkspaceManifestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        protected WorkspaceManifestService $workspaceManifestService
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'is_active' => (string) $request->string('is_active'),
        ];

        $products = Product::query()
            ->withCount(['plans', 'tenantProductSubscriptions', 'capabilities'])
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

        return view('admin.products.index', [
            'products' => $products,
            'filters' => $filters,
        ]);
    }

    public function show(Product $product): View
    {
        $product->loadCount([
            'capabilities',
            'tenantProductSubscriptions',
            'enablementRequests',
            'plans',
            'plans as active_plans_count' => fn ($query) => $query->where('is_active', true),
            'plans as paid_plans_count' => fn ($query) => $query->where('is_active', true)->where('billing_period', '!=', 'trial'),
            'plans as trial_plans_count' => fn ($query) => $query->where('is_active', true)->where('billing_period', 'trial'),
        ]);

        $latestPlans = Plan::query()
            ->where('product_id', $product->id)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $manifestFamily = $this->resolveManifestFamily($product);
        $familyDefinition = $manifestFamily !== null
            ? $this->workspaceManifestService->familyDefinition($manifestFamily)
            : [];
        $builderChecklist = $this->builderChecklist($product, $manifestFamily);

        return view('admin.products.show', [
            'product' => $product,
            'latestPlans' => $latestPlans,
            'manifestFamily' => $manifestFamily,
            'familyDefinition' => $familyDefinition,
            'builderChecklist' => $builderChecklist,
            'builderCompletionPercent' => $this->builderCompletionPercent($builderChecklist),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product([
                'is_active' => true,
                'sort_order' => 0,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $product = Product::query()->create($this->validatedData($request));

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Product created successfully. Continue the lifecycle from the product builder below.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', [
            'product' => $product,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validatedData($request, $product->id));

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $hasPlans = Plan::query()->where('product_id', $product->id)->exists();
        $hasSubscriptions = TenantProductSubscription::query()->where('product_id', $product->id)->exists();
        $hasRequests = ProductEnablementRequest::query()->where('product_id', $product->id)->exists();
        $hasCapabilities = $product->capabilities()->exists();

        if ($hasPlans || $hasSubscriptions || $hasRequests || $hasCapabilities) {
            return redirect()
                ->route('admin.products.index')
                ->with('error', 'This product cannot be deleted because it is already used by plans, capabilities, subscriptions, or enablement requests.');
        }

        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    protected function validatedData(Request $request, ?int $productId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'code')->ignore($productId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $validated['code'] = Str::snake((string) $validated['code']);
        $validated['slug'] = Str::slug((string) $validated['slug']);

        return $validated;
    }

    protected function resolveManifestFamily(Product $product): ?string
    {
        foreach ($this->workspaceManifestService->familyKeys() as $family) {
            $definition = $this->workspaceManifestService->familyDefinition($family);
            $aliases = array_map('strval', (array) ($definition['aliases'] ?? []));
            $needles = array_unique(array_filter(array_merge(
                [$family],
                $aliases,
                [(string) $product->code, (string) $product->slug]
            )));

            foreach ($needles as $needle) {
                if (
                    strtolower($needle) === strtolower((string) $product->code)
                    || strtolower($needle) === strtolower((string) $product->slug)
                ) {
                    return (string) $family;
                }
            }
        }

        return null;
    }

    protected function builderChecklist(Product $product, ?string $manifestFamily): array
    {
        return [
            [
                'label' => 'Base product record',
                'description' => 'Code, name, slug, description, status, and sort order are stored in the central catalog.',
                'completed' => filled($product->code) && filled($product->name) && filled($product->slug),
            ],
            [
                'label' => 'Portal-ready capabilities',
                'description' => 'Customer-facing capabilities exist for the product card and selection flow.',
                'completed' => (int) $product->capabilities_count > 0,
            ],
            [
                'label' => 'Billing plan catalog',
                'description' => 'At least one active plan exists so the portal can show paid or trial options.',
                'completed' => (int) $product->active_plans_count > 0,
            ],
            [
                'label' => 'Workspace family mapping',
                'description' => 'The product is represented in workspace manifest config so runtime/sidebar behavior can be resolved.',
                'completed' => $manifestFamily !== null,
            ],
            [
                'label' => 'Portal publication status',
                'description' => 'The product is active and has enough setup to appear logically inside the customer portal.',
                'completed' => $product->is_active
                    && (int) $product->capabilities_count > 0
                    && (int) $product->active_plans_count > 0,
            ],
        ];
    }

    protected function builderCompletionPercent(array $builderChecklist): int
    {
        $total = count($builderChecklist);

        if ($total === 0) {
            return 0;
        }

        $completed = collect($builderChecklist)
            ->filter(fn (array $item) => ! empty($item['completed']))
            ->count();

        return (int) floor(($completed / $total) * 100);
    }
}
