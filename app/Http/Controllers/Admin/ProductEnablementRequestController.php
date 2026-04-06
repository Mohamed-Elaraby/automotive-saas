<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductEnablementRequest;
use Illuminate\Http\Request;

class ProductEnablementRequestController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->string('q')),
            'status' => trim((string) $request->string('status')),
            'product_id' => $request->filled('product_id') ? (int) $request->input('product_id') : null,
        ];

        $requests = ProductEnablementRequest::query()
            ->leftJoin('products', 'products.id', '=', 'product_enablement_requests.product_id')
            ->leftJoin('users', 'users.id', '=', 'product_enablement_requests.user_id')
            ->select([
                'product_enablement_requests.*',
                'products.name as product_name',
                'products.slug as product_slug',
                'products.code as product_code',
                'users.name as user_name',
                'users.email as user_email',
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $search = $filters['q'];

                $query->where(function ($nested) use ($search) {
                    $nested->where('product_enablement_requests.tenant_id', 'like', "%{$search}%")
                        ->orWhere('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('products.name', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] !== '', fn ($query) => $query->where('product_enablement_requests.status', $filters['status']))
            ->when(! empty($filters['product_id']), fn ($query) => $query->where('product_enablement_requests.product_id', $filters['product_id']))
            ->orderByDesc('product_enablement_requests.requested_at')
            ->orderByDesc('product_enablement_requests.id')
            ->paginate(20)
            ->withQueryString();

        $products = Product::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('admin.product-enablement-requests.index', [
            'requests' => $requests,
            'filters' => $filters,
            'products' => $products,
            'statusOptions' => ['pending', 'approved', 'rejected'],
        ]);
    }
}
