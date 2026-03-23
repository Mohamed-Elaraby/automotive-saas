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
        Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'native_symbol' => '$',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        Currency::query()->updateOrCreate(
            ['code' => 'AED'],
            [
                'name' => 'UAE Dirham',
                'symbol' => 'AED',
                'native_symbol' => 'د.إ',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        Currency::query()->updateOrCreate(
            ['code' => 'EGP'],
            [
                'name' => 'Egyptian Pound',
                'symbol' => 'EGP',
                'native_symbol' => 'ج.م',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        Currency::query()->updateOrCreate(
            ['code' => 'SAR'],
            [
                'name' => 'Saudi Riyal',
                'symbol' => 'SAR',
                'native_symbol' => 'ر.س',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'is_active' => true,
                'sort_order' => 4,
            ]
        );

        $uae = Country::query()->updateOrCreate(
            ['iso2' => 'AE'],
            [
                'iso3' => 'ARE',
                'name' => 'United Arab Emirates',
                'native_name' => 'الإمارات العربية المتحدة',
                'phone_code' => '+971',
                'capital' => 'Abu Dhabi',
                'currency_code' => 'AED',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $egypt = Country::query()->updateOrCreate(
            ['iso2' => 'EG'],
            [
                'iso3' => 'EGY',
                'name' => 'Egypt',
                'native_name' => 'مصر',
                'phone_code' => '+20',
                'capital' => 'Cairo',
                'currency_code' => 'EGP',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $saudi = Country::query()->updateOrCreate(
            ['iso2' => 'SA'],
            [
                'iso3' => 'SAU',
                'name' => 'Saudi Arabia',
                'native_name' => 'المملكة العربية السعودية',
                'phone_code' => '+966',
                'capital' => 'Riyadh',
                'currency_code' => 'SAR',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        $abuDhabi = State::query()->updateOrCreate(
            ['country_id' => $uae->id, 'name' => 'Abu Dhabi'],
            [
                'code' => 'AZ',
                'native_name' => 'أبوظبي',
                'type' => 'emirate',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $dubai = State::query()->updateOrCreate(
            ['country_id' => $uae->id, 'name' => 'Dubai'],
            [
                'code' => 'DU',
                'native_name' => 'دبي',
                'type' => 'emirate',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $sharjah = State::query()->updateOrCreate(
            ['country_id' => $uae->id, 'name' => 'Sharjah'],
            [
                'code' => 'SH',
                'native_name' => 'الشارقة',
                'type' => 'emirate',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        $ajman = State::query()->updateOrCreate(
            ['country_id' => $uae->id, 'name' => 'Ajman'],
            [
                'code' => 'AJ',
                'native_name' => 'عجمان',
                'type' => 'emirate',
                'is_active' => true,
                'sort_order' => 4,
            ]
        );

        $ummAlQuwain = State::query()->updateOrCreate(
            ['country_id' => $uae->id, 'name' => 'Umm Al Quwain'],
            [
                'code' => 'UQ',
                'native_name' => 'أم القيوين',
                'type' => 'emirate',
                'is_active' => true,
                'sort_order' => 5,
            ]
        );

        $rasAlKhaimah = State::query()->updateOrCreate(
            ['country_id' => $uae->id, 'name' => 'Ras Al Khaimah'],
            [
                'code' => 'RK',
                'native_name' => 'رأس الخيمة',
                'type' => 'emirate',
                'is_active' => true,
                'sort_order' => 6,
            ]
        );

        $fujairah = State::query()->updateOrCreate(
            ['country_id' => $uae->id, 'name' => 'Fujairah'],
            [
                'code' => 'FU',
                'native_name' => 'الفجيرة',
                'type' => 'emirate',
                'is_active' => true,
                'sort_order' => 7,
            ]
        );

        $cairo = State::query()->updateOrCreate(
            ['country_id' => $egypt->id, 'name' => 'Cairo'],
            [
                'code' => 'C',
                'native_name' => 'القاهرة',
                'type' => 'governorate',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $giza = State::query()->updateOrCreate(
            ['country_id' => $egypt->id, 'name' => 'Giza'],
            [
                'code' => 'GZ',
                'native_name' => 'الجيزة',
                'type' => 'governorate',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $alexandria = State::query()->updateOrCreate(
            ['country_id' => $egypt->id, 'name' => 'Alexandria'],
            [
                'code' => 'ALX',
                'native_name' => 'الإسكندرية',
                'type' => 'governorate',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        $riyadh = State::query()->updateOrCreate(
            ['country_id' => $saudi->id, 'name' => 'Riyadh'],
            [
                'code' => 'RIY',
                'native_name' => 'الرياض',
                'type' => 'region',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $makkah = State::query()->updateOrCreate(
            ['country_id' => $saudi->id, 'name' => 'Makkah'],
            [
                'code' => 'MKK',
                'native_name' => 'مكة المكرمة',
                'type' => 'region',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $easternProvince = State::query()->updateOrCreate(
            ['country_id' => $saudi->id, 'name' => 'Eastern Province'],
            [
                'code' => 'EST',
                'native_name' => 'المنطقة الشرقية',
                'type' => 'region',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $abuDhabi->id, 'name' => 'Abu Dhabi'],
            [
                'country_id' => $uae->id,
                'native_name' => 'أبوظبي',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $abuDhabi->id, 'name' => 'Al Ain'],
            [
                'country_id' => $uae->id,
                'native_name' => 'العين',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $dubai->id, 'name' => 'Dubai'],
            [
                'country_id' => $uae->id,
                'native_name' => 'دبي',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $sharjah->id, 'name' => 'Sharjah'],
            [
                'country_id' => $uae->id,
                'native_name' => 'الشارقة',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $ajman->id, 'name' => 'Ajman'],
            [
                'country_id' => $uae->id,
                'native_name' => 'عجمان',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $ummAlQuwain->id, 'name' => 'Umm Al Quwain'],
            [
                'country_id' => $uae->id,
                'native_name' => 'أم القيوين',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $rasAlKhaimah->id, 'name' => 'Ras Al Khaimah'],
            [
                'country_id' => $uae->id,
                'native_name' => 'رأس الخيمة',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $fujairah->id, 'name' => 'Fujairah'],
            [
                'country_id' => $uae->id,
                'native_name' => 'الفجيرة',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $cairo->id, 'name' => 'Cairo'],
            [
                'country_id' => $egypt->id,
                'native_name' => 'القاهرة',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $cairo->id, 'name' => 'New Cairo'],
            [
                'country_id' => $egypt->id,
                'native_name' => 'القاهرة الجديدة',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $cairo->id, 'name' => 'Nasr City'],
            [
                'country_id' => $egypt->id,
                'native_name' => 'مدينة نصر',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $giza->id, 'name' => 'Giza'],
            [
                'country_id' => $egypt->id,
                'native_name' => 'الجيزة',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $giza->id, 'name' => '6th of October'],
            [
                'country_id' => $egypt->id,
                'native_name' => '6 أكتوبر',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $giza->id, 'name' => 'Sheikh Zayed'],
            [
                'country_id' => $egypt->id,
                'native_name' => 'الشيخ زايد',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $alexandria->id, 'name' => 'Alexandria'],
            [
                'country_id' => $egypt->id,
                'native_name' => 'الإسكندرية',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $riyadh->id, 'name' => 'Riyadh'],
            [
                'country_id' => $saudi->id,
                'native_name' => 'الرياض',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $makkah->id, 'name' => 'Jeddah'],
            [
                'country_id' => $saudi->id,
                'native_name' => 'جدة',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $makkah->id, 'name' => 'Makkah'],
            [
                'country_id' => $saudi->id,
                'native_name' => 'مكة',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $easternProvince->id, 'name' => 'Dammam'],
            [
                'country_id' => $saudi->id,
                'native_name' => 'الدمام',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        City::query()->updateOrCreate(
            ['state_id' => $easternProvince->id, 'name' => 'Khobar'],
            [
                'country_id' => $saudi->id,
                'native_name' => 'الخبر',
                'postal_code' => null,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );
    }
}
