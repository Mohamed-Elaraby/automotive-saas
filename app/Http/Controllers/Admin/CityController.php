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

class CityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'country_id' => $request->filled('country_id') ? (int) $request->input('country_id') : null,
            'state_id' => $request->filled('state_id') ? (int) $request->input('state_id') : null,
            'is_active' => (string) $request->string('is_active'),
        ];

        $query = City::query()->with(['country', 'state']);

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('native_name', 'like', '%' . $search . '%')
                    ->orWhere('postal_code', 'like', '%' . $search . '%');
            });
        }

        if (! empty($filters['country_id'])) {
            $query->where('country_id', $filters['country_id']);
        }

        if (! empty($filters['state_id'])) {
            $query->where('state_id', $filters['state_id']);
        }

        if ($filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $cities = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reference-data.cities.index', [
            'cities' => $cities,
            'filters' => $filters,
            'countries' => Country::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'states' => State::query()->with('country')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.reference-data.cities.create', [
            'city' => new City(),
            'countries' => Country::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'states' => State::query()->with('country')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        $this->assertStateBelongsToCountry($validated['state_id'], $validated['country_id']);

        City::query()->create($validated);

        return redirect()
            ->route('admin.reference-data.cities.index')
            ->with('success', 'City created successfully.');
    }

    public function edit(City $city): View
    {
        return view('admin.reference-data.cities.edit', [
            'city' => $city,
            'countries' => Country::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'states' => State::query()->with('country')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, City $city): RedirectResponse
    {
        $validated = $this->validateRequest($request, $city->id);

        $this->assertStateBelongsToCountry($validated['state_id'], $validated['country_id']);

        $city->update($validated);

        return redirect()
            ->route('admin.reference-data.cities.index')
            ->with('success', 'City updated successfully.');
    }

    protected function validateRequest(Request $request, ?int $cityId = null): array
    {
        return $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'state_id' => ['required', 'integer', Rule::exists('states', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cities', 'name')
                    ->where(fn ($query) => $query->where('state_id', (int) $request->input('state_id')))
                    ->ignore($cityId),
            ],
            'native_name' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }

    protected function assertStateBelongsToCountry(int $stateId, int $countryId): void
    {
        $state = State::query()->findOrFail($stateId);

        abort_unless((int) $state->country_id === (int) $countryId, 422, 'Selected state does not belong to the selected country.');
    }
}
