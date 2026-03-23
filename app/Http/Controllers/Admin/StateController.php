<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StateController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'country_id' => $request->filled('country_id') ? (int) $request->input('country_id') : null,
            'type' => trim((string) $request->string('type')),
            'is_active' => (string) $request->string('is_active'),
        ];

        $query = State::query()->with('country');

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('native_name', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        if (! empty($filters['country_id'])) {
            $query->where('country_id', $filters['country_id']);
        }

        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        if ($filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $states = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reference-data.states.index', [
            'states' => $states,
            'filters' => $filters,
            'countries' => Country::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'types' => State::query()
                ->whereNotNull('type')
                ->where('type', '!=', '')
                ->distinct()
                ->orderBy('type')
                ->pluck('type'),
        ]);
    }

    public function create(): View
    {
        return view('admin.reference-data.states.create', [
            'state' => new State(),
            'countries' => Country::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        State::query()->create($validated);

        return redirect()
            ->route('admin.reference-data.states.index')
            ->with('success', 'State created successfully.');
    }

    public function edit(State $state): View
    {
        return view('admin.reference-data.states.edit', [
            'state' => $state,
            'countries' => Country::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, State $state): RedirectResponse
    {
        $validated = $this->validateRequest($request, $state->id);

        $state->update($validated);

        return redirect()
            ->route('admin.reference-data.states.index')
            ->with('success', 'State updated successfully.');
    }

    public function destroy(State $state): RedirectResponse
    {
        $hasCities = City::query()->where('state_id', $state->id)->exists();

        if ($hasCities) {
            return redirect()
                ->route('admin.reference-data.states.index')
                ->with('error', 'This state cannot be deleted because it has related cities.');
        }

        $state->delete();

        return redirect()
            ->route('admin.reference-data.states.index')
            ->with('success', 'State deleted successfully.');
    }

    protected function validateRequest(Request $request, ?int $stateId = null): array
    {
        return $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'code' => ['nullable', 'string', 'max:20'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('states', 'name')
                    ->where(fn ($query) => $query->where('country_id', (int) $request->input('country_id')))
                    ->ignore($stateId),
            ],
            'native_name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }
}
