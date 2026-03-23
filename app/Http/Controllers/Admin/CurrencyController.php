<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'is_active' => (string) $request->string('is_active'),
        ];

        $query = Currency::query();

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('code', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('symbol', 'like', '%' . $search . '%')
                    ->orWhere('native_symbol', 'like', '%' . $search . '%');
            });
        }

        if ($filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $currencies = $query
            ->orderBy('sort_order')
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reference-data.currencies.index', [
            'currencies' => $currencies,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.reference-data.currencies.create', [
            'currency' => new Currency(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        Currency::query()->create($validated);

        return redirect()
            ->route('admin.reference-data.currencies.index')
            ->with('success', 'Currency created successfully.');
    }

    public function edit(Currency $currency): View
    {
        return view('admin.reference-data.currencies.edit', [
            'currency' => $currency,
        ]);
    }

    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $validated = $this->validateRequest($request, $currency->id);

        $currency->update($validated);

        return redirect()
            ->route('admin.reference-data.currencies.index')
            ->with('success', 'Currency updated successfully.');
    }

    protected function validateRequest(Request $request, ?int $currencyId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'size:3',
                Rule::unique('currencies', 'code')->ignore($currencyId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'native_symbol' => ['nullable', 'string', 'max:20'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
            'thousands_separator' => ['required', 'string', 'max:5'],
            'decimal_separator' => ['required', 'string', 'max:5'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $validated['code'] = strtoupper((string) $validated['code']);

        return $validated;
    }
}
