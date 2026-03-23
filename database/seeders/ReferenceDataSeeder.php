<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\State;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCurrencies();
        $this->seedCountriesStatesAndCities();
    }

    protected function seedCurrencies(): void
    {
        $currencies = [
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'native_symbol' => '$',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'AED',
                'name' => 'UAE Dirham',
                'symbol' => 'AED',
                'native_symbol' => 'د.إ',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'EGP',
                'name' => 'Egyptian Pound',
                'symbol' => 'EGP',
                'native_symbol' => 'ج.م',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'SAR',
                'name' => 'Saudi Riyal',
                'symbol' => 'SAR',
                'native_symbol' => 'ر.س',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }

    protected function seedCountriesStatesAndCities(): void
    {
        $data = [
            [
                'country' => [
                    'iso2' => 'AE',
                    'iso3' => 'ARE',
                    'name' => 'United Arab Emirates',
                    'native_name' => 'الإمارات العربية المتحدة',
                    'phone_code' => '+971',
                    'capital' => 'Abu Dhabi',
                    'currency_code' => 'AED',
                    'is_active' => true,
                    'sort_order' => 1,
                ],
                'states' => [
                    [
                        'code' => 'AZ',
                        'name' => 'Abu Dhabi',
                        'native_name' => 'أبوظبي',
                        'type' => 'emirate',
                        'sort_order' => 1,
                        'cities' => [
                            ['name' => 'Abu Dhabi', 'native_name' => 'أبوظبي', 'sort_order' => 1],
                            ['name' => 'Al Ain', 'native_name' => 'العين', 'sort_order' => 2],
                        ],
                    ],
                    [
                        'code' => 'DU',
                        'name' => 'Dubai',
                        'native_name' => 'دبي',
                        'type' => 'emirate',
                        'sort_order' => 2,
                        'cities' => [
                            ['name' => 'Dubai', 'native_name' => 'دبي', 'sort_order' => 1],
                        ],
                    ],
                    [
                        'code' => 'SH',
                        'name' => 'Sharjah',
                        'native_name' => 'الشارقة',
                        'type' => 'emirate',
                        'sort_order' => 3,
                        'cities' => [
                            ['name' => 'Sharjah', 'native_name' => 'الشارقة', 'sort_order' => 1],
                        ],
                    ],
                    [
                        'code' => 'AJ',
                        'name' => 'Ajman',
                        'native_name' => 'عجمان',
                        'type' => 'emirate',
                        'sort_order' => 4,
                        'cities' => [
                            ['name' => 'Ajman', 'native_name' => 'عجمان', 'sort_order' => 1],
                        ],
                    ],
                    [
                        'code' => 'UQ',
                        'name' => 'Umm Al Quwain',
                        'native_name' => 'أم القيوين',
                        'type' => 'emirate',
                        'sort_order' => 5,
                        'cities' => [
                            ['name' => 'Umm Al Quwain', 'native_name' => 'أم القيوين', 'sort_order' => 1],
                        ],
                    ],
                    [
                        'code' => 'RK',
                        'name' => 'Ras Al Khaimah',
                        'native_name' => 'رأس الخيمة',
                        'type' => 'emirate',
                        'sort_order' => 6,
                        'cities' => [
                            ['name' => 'Ras Al Khaimah', 'native_name' => 'رأس الخيمة', 'sort_order' => 1],
                        ],
                    ],
                    [
                        'code' => 'FU',
                        'name' => 'Fujairah',
                        'native_name' => 'الفجيرة',
                        'type' => 'emirate',
                        'sort_order' => 7,
                        'cities' => [
                            ['name' => 'Fujairah', 'native_name' => 'الفجيرة', 'sort_order' => 1],
                        ],
                    ],
                ],
            ],
            [
                'country' => [
                    'iso2' => 'EG',
                    'iso3' => 'EGY',
                    'name' => 'Egypt',
                    'native_name' => 'مصر',
                    'phone_code' => '+20',
                    'capital' => 'Cairo',
                    'currency_code' => 'EGP',
                    'is_active' => true,
                    'sort_order' => 2,
                ],
                'states' => [
                    [
                        'code' => 'C',
                        'name' => 'Cairo',
                        'native_name' => 'القاهرة',
                        'type' => 'governorate',
                        'sort_order' => 1,
                        'cities' => [
                            ['name' => 'Cairo', 'native_name' => 'القاهرة', 'sort_order' => 1],
                            ['name' => 'New Cairo', 'native_name' => 'القاهرة الجديدة', 'sort_order' => 2],
                            ['name' => 'Nasr City', 'native_name' => 'مدينة نصر', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'code' => 'GZ',
                        'name' => 'Giza',
                        'native_name' => 'الجيزة',
                        'type' => 'governorate',
                        'sort_order' => 2,
                        'cities' => [
                            ['name' => 'Giza', 'native_name' => 'الجيزة', 'sort_order' => 1],
                            ['name' => '6th of October', 'native_name' => '6 أكتوبر', 'sort_order' => 2],
                            ['name' => 'Sheikh Zayed', 'native_name' => 'الشيخ زايد', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'code' => 'ALX',
                        'name' => 'Alexandria',
                        'native_name' => 'الإسكندرية',
                        'type' => 'governorate',
                        'sort_order' => 3,
                        'cities' => [
                            ['name' => 'Alexandria', 'native_name' => 'الإسكندرية', 'sort_order' => 1],
                        ],
                    ],
                ],
            ],
            [
                'country' => [
                    'iso2' => 'SA',
                    'iso3' => 'SAU',
                    'name' => 'Saudi Arabia',
                    'native_name' => 'المملكة العربية السعودية',
                    'phone_code' => '+966',
                    'capital' => 'Riyadh',
                    'currency_code' => 'SAR',
                    'is_active' => true,
                    'sort_order' => 3,
                ],
                'states' => [
                    [
                        'code' => 'RIY',
                        'name' => 'Riyadh',
                        'native_name' => 'الرياض',
                        'type' => 'region',
                        'sort_order' => 1,
                        'cities' => [
                            ['name' => 'Riyadh', 'native_name' => 'الرياض', 'sort_order' => 1],
                        ],
                    ],
                    [
                        'code' => 'MKK',
                        'name' => 'Makkah',
                        'native_name' => 'مكة المكرمة',
                        'type' => 'region',
                        'sort_order' => 2,
                        'cities' => [
                            ['name' => 'Jeddah', 'native_name' => 'جدة', 'sort_order' => 1],
                            ['name' => 'Makkah', 'native_name' => 'مكة', 'sort_order' => 2],
                        ],
                    ],
                    [
                        'code' => 'EST',
                        'name' => 'Eastern Province',
                        'native_name' => 'المنطقة الشرقية',
                        'type' => 'region',
                        'sort_order' => 3,
                        'cities' => [
                            ['name' => 'Dammam', 'native_name' => 'الدمام', 'sort_order' => 1],
                            ['name' => 'Khobar', 'native_name' => 'الخبر', 'sort_order' => 2],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($data as $countryData) {
            $country = Country::query()->updateOrCreate(
                ['iso2' => $countryData['country']['iso2']],
                $countryData['country']
            );

            foreach ($countryData['states'] as $stateData) {
                $cities = $stateData['cities'] ?? [];
                unset($stateData['cities']);

                $state = State::query()->updateOrCreate(
                    [
                        'country_id' => $country->id,
                        'name' => $stateData['name'],
                    ],
                    array_merge($stateData, [
                        'country_id' => $country->id,
                        'is_active' => true,
                    ])
                );

                foreach ($cities as $cityData) {
                    City::query()->updateOrCreate(
                        [
                            'state_id' => $state->id,
                            'name' => $cityData['name'],
                        ],
                        [
                            'country_id' => $country->id,
                            'state_id' => $state->id,
                            'name' => $cityData['name'],
                            'native_name' => $cityData['native_name'] ?? null,
                            'postal_code' => $cityData['postal_code'] ?? null,
                            'is_active' => true,
                            'sort_order' => $cityData['sort_order'] ?? 0,
                        ]
                    );
                }
            }
        }
    }
}
