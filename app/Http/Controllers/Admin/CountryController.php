<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CountryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'currency_code' => strtoupper(trim((string) $request->string('currency_code'))),
            'is_active' => (string) $request->string('is_active'),
        ];

        $query = Country::query()->with('currency');

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('iso2', 'like', '%' . $search . '%')
                    ->orWhere('iso3', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('native_name', 'like', '%' . $search . '%')
                    ->orWhere('capital', 'like', '%' . $search . '%');
            });
        }

        if ($filters['currency_code'] !== '') {
            $query->where('currency_code', $filters['currency_code']);
        }

        if ($filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $countries = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reference-data.countries.index', [
            'countries' => $countries,
            'filters' => $filters,
            'currencies' => Currency::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.reference-data.countries.create', [
            'country' => new Country(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        Country::query()->create($validated);

        return redirect()
            ->route('admin.reference-data.countries.index')
            ->with('success', 'Country created successfully.');
    }

    public function edit(Country $country): View
    {
        return view('admin.reference-data.countries.edit', [
            'country' => $country,
            'currencies' => Currency::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Country $country): RedirectResponse
    {
        $validated = $this->validateRequest($request, $country->id);

        $country->update($validated);

        return redirect()
            ->route('admin.reference-data.countries.index')
            ->with('success', 'Country updated successfully.');
    }

    protected function validateRequest(Request $request, ?int $countryId = null): array
    {
        $validated = $request->validate([
            'iso2' => [
                'required',
                'string',
                'size:2',
                Rule::unique('countries', 'iso2')->ignore($countryId),
            ],
            'iso3' => [
                'required',
                'string',
                'size:3',
                Rule::unique('countries', 'iso3')->ignore($countryId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'native_name' => ['nullable', 'string', 'max:255'],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'capital' => ['nullable', 'string', 'max:255'],
            'currency_code' => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $validated['iso2'] = strtoupper((string) $validated['iso2']);
        $validated['iso3'] = strtoupper((string) $validated['iso3']);

        if (! empty($validated['currency_code'])) {
            $validated['currency_code'] = strtoupper((string) $validated['currency_code']);
        }

        return $validated;
    }
}
