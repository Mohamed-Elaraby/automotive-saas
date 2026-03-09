<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.create', [
            'plan' => new Plan(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $data['features'] = $this->normalizeFeatures($request->input('features_json'));
        $data['slug'] = Str::slug($data['slug']);

        Plan::create($data);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan created successfully.');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $this->validatedData($request, $plan->id);

        $data['features'] = $this->normalizeFeatures($request->input('features_json'));
        $data['slug'] = Str::slug($data['slug']);

        $plan->update($data);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan updated successfully.');
    }

    public function toggleActive(Plan $plan)
    {
        $plan->update([
            'is_active' => ! $plan->is_active,
        ]);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan status updated successfully.');
    }

    protected function validatedData(Request $request, ?int $planId = null): array
    {
        return $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'slug' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('plans', 'slug')->ignore($planId),
                ],
                'price' => ['required', 'numeric', 'min:0'],
                'currency' => ['required', 'string', 'size:3'],
                'billing_period' => ['required', 'string', Rule::in(['trial', 'monthly', 'yearly', 'one_time'])],
                'is_active' => ['nullable', 'boolean'],
                'sort_order' => ['required', 'integer', 'min:0'],
                'max_users' => ['nullable', 'integer', 'min:1'],
                'max_branches' => ['nullable', 'integer', 'min:1'],
                'max_products' => ['nullable', 'integer', 'min:1'],
                'max_storage_mb' => ['nullable', 'integer', 'min:1'],
                'description' => ['nullable', 'string'],
            ]) + [
                'is_active' => $request->boolean('is_active'),
            ];
    }

    protected function normalizeFeatures(?string $featuresJson): ?array
    {
        if (blank($featuresJson)) {
            return null;
        }

        $decoded = json_decode($featuresJson, true);

        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    public function destroy(Plan $plan)
    {
        $subscriptionsCount = \Illuminate\Support\Facades\DB::table('subscriptions')
            ->where('plan_id', $plan->id)
            ->count();

        if ($subscriptionsCount > 0) {
            return redirect()
                ->route('admin.plans.index')
                ->withErrors([
                    'delete' => 'Cannot delete a plan that is already used by subscriptions.',
                ]);
        }

        $plan->delete();

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan deleted successfully.');
    }
}
