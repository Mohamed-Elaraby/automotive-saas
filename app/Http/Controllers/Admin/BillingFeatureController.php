<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BillingFeatureController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->string('q')),
            'status' => (string) $request->string('status'),
            'usage' => (string) $request->string('usage'),
        ];

        $features = BillingFeature::query()
            ->withCount('plans')
            ->with([
                'plans' => fn ($query) => $query
                    ->select('plans.id', 'plans.name')
                    ->orderBy('plans.name'),
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $search = $filters['q'];

                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(in_array($filters['status'], ['active', 'inactive'], true), function ($query) use ($filters) {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->when(in_array($filters['usage'], ['assigned', 'unassigned'], true), function ($query) use ($filters) {
                if ($filters['usage'] === 'assigned') {
                    $query->has('plans');

                    return;
                }

                $query->doesntHave('plans');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.billing-features.index', compact('features', 'filters'));
    }

    public function create()
    {
        return view('admin.billing-features.create', [
            'feature' => new BillingFeature([
                'is_active' => true,
                'sort_order' => 0,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $feature = BillingFeature::query()->create($this->validatedData($request));

        return redirect()
            ->route('admin.billing-features.index')
            ->with('success', 'Feature created successfully.');
    }

    public function edit(BillingFeature $billingFeature)
    {
        return view('admin.billing-features.edit', [
            'feature' => $billingFeature,
        ]);
    }

    public function update(Request $request, BillingFeature $billingFeature)
    {
        $billingFeature->update($this->validatedData($request, $billingFeature->id));

        return redirect()
            ->route('admin.billing-features.index')
            ->with('success', 'Feature updated successfully.');
    }

    public function toggleActive(BillingFeature $billingFeature)
    {
        $billingFeature->update([
            'is_active' => ! $billingFeature->is_active,
        ]);

        return redirect()
            ->route('admin.billing-features.index')
            ->with('success', 'Feature status updated successfully.');
    }

    public function destroy(BillingFeature $billingFeature)
    {
        $plansCount = DB::table('billing_feature_plan')
            ->where('billing_feature_id', $billingFeature->id)
            ->count();

        if ($plansCount > 0) {
            return redirect()
                ->route('admin.billing-features.index')
                ->withErrors([
                    'delete' => 'Cannot delete a feature that is already assigned to one or more plans.',
                ]);
        }

        $billingFeature->delete();

        return redirect()
            ->route('admin.billing-features.index')
            ->with('success', 'Feature deleted successfully.');
    }

    protected function validatedData(Request $request, ?int $featureId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('billing_features', 'slug')->ignore($featureId),
            ],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = Str::slug((string) $data['slug']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
