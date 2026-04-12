<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Product;
use App\Services\Admin\AppSettingsService;
use App\Services\Admin\ProductLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductPortalPublicationController extends Controller
{
    public function __construct(
        protected AppSettingsService $settingsService,
        protected ProductLifecycleService $lifecycleService
    ) {
    }

    public function show(Product $product): View
    {
        $product->loadCount([
            'capabilities',
            'plans as active_plans_count' => fn ($query) => $query->where('is_active', true),
        ]);

        $experienceDraft = $this->experienceDraft($product);
        $blockers = $this->lifecycleService->publicationBlockers($product);

        return view('admin.products.portal-publication', [
            'product' => $product,
            'experienceDraft' => $experienceDraft,
            'blockers' => $blockers,
            'readyForPublication' => count($blockers) === 0,
            'previewCapabilities' => $product->capabilities()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->limit(5)->get(),
            'previewPlans' => Plan::query()->where('product_id', $product->id)->where('is_active', true)->orderBy('sort_order')->limit(3)->get(),
        ]);
    }

    public function publish(Product $product): RedirectResponse
    {
        $product->loadCount([
            'capabilities',
            'plans as active_plans_count' => fn ($query) => $query->where('is_active', true),
        ]);

        $blockers = $this->lifecycleService->publicationBlockers($product);

        if ($blockers !== []) {
            return redirect()
                ->route('admin.products.portal-publication.show', $product)
                ->with('error', 'This product is not ready for portal publication yet.');
        }

        $product->update(['is_active' => true]);
        $this->lifecycleService->appendAudit($product, 'portal.published', [
            'active_plans_count' => (int) $product->active_plans_count,
            'capabilities_count' => (int) $product->capabilities_count,
        ]);

        return redirect()
            ->route('admin.products.portal-publication.show', $product)
            ->with('success', 'Product is now live in the customer portal.');
    }

    public function hide(Product $product): RedirectResponse
    {
        $product->update(['is_active' => false]);
        $this->lifecycleService->appendAudit($product, 'portal.hidden');

        return redirect()
            ->route('admin.products.portal-publication.show', $product)
            ->with('success', 'Product has been hidden from the customer portal.');
    }

    protected function experienceDraft(Product $product): array
    {
        return (array) $this->settingsService->get('workspace_products.experience.' . $product->code, []);
    }
}
