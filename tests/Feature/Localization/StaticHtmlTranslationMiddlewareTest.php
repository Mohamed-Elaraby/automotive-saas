<?php

namespace Tests\Feature\Localization;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StaticHtmlTranslationMiddlewareTest extends TestCase
{
    public function test_arabic_html_response_translates_known_static_text_without_touching_scripts(): void
    {
        Route::middleware('web')->get('/static-translation-ar', function () {
            app()->setLocale('ar');

            return response(
                '<html><body><button title="Save Changes">Save Changes</button><input type="submit" value="Cancel"><span>Customer Payment Summary</span><script>window.label = "Save Changes";</script></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        });

        $response = $this->get('/static-translation-ar');

        $response->assertOk();
        $response->assertSee('حفظ التغييرات', false);
        $response->assertSee('title="حفظ التغييرات"', false);
        $response->assertSee('value="إلغاء"', false);
        $response->assertSee('عميل دفعة ملخص', false);
        $response->assertSee('window.label = "Save Changes";', false);
    }

    public function test_english_html_response_is_not_auto_translated(): void
    {
        Route::middleware('web')->get('/static-translation-en', function () {
            app()->setLocale('en');

            return response(
                '<html><body><button title="Save Changes">Save Changes</button></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        });

        $response = $this->get('/static-translation-en');

        $response->assertOk();
        $response->assertSee('Save Changes', false);
        $response->assertDontSee('حفظ التغييرات', false);
    }
}
