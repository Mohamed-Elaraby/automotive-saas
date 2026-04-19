<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockMovementReportController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = $request->input('branch_id');
        $productId = $request->input('product_id');
        $type = $request->input('type');
        $search = trim((string) $request->input('search'));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $products = StockItem::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $types = [
            'opening',
            'adjustment_in',
            'adjustment_out',
            'transfer_in',
            'transfer_out',
        ];

        $query = StockMovement::query()
            ->with(['branch', 'product', 'creator']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('sku', 'like', '%' . $search . '%')
                        ->orWhere('barcode', 'like', '%' . $search . '%');
                })->orWhereHas('branch', function ($branchQuery) use ($search) {
                    $branchQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%');
                })->orWhere('notes', 'like', '%' . $search . '%')
                    ->orWhere('type', 'like', '%' . $search . '%');
            });
        }

        $movements = $query
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('automotive.admin.stock-movements.index', compact(
            'movements',
            'branches',
            'products',
            'types',
            'branchId',
            'productId',
            'type',
            'search',
            'dateFrom',
            'dateTo'
        ));
    }
}
