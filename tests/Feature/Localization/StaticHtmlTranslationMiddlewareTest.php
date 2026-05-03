<?php

namespace Tests\Feature\Localization;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StaticHtmlTranslationMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    public function test_arabic_html_response_translates_known_static_text_without_touching_scripts(): void
    {
        Route::middleware('web')->get('/static-translation-ar', function () {
            app()->setLocale('ar');

            return response(
                '<html><body><form onsubmit="return confirm(\'Delete this notification?\');"><button title="Save Changes">Save Changes</button><input type="submit" value="Cancel"><span>Customer Payment Summary</span></form><script>window.label = "Save Changes";</script></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        });

        $response = $this->followingRedirects()->get('/static-translation-ar');

        $response->assertOk();
        $response->assertSee('حفظ التغييرات', false);
        $response->assertSee('title="حفظ التغييرات"', false);
        $response->assertSee('value="إلغاء"', false);
        $response->assertSee('عميل دفعة ملخص', false);
        $response->assertSee('confirm(\'حذف هذا الإشعار؟\')', false);
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

        $response = $this->followingRedirects()->get('/static-translation-en');

        $response->assertOk();
        $response->assertSee('Save Changes', false);
        $response->assertDontSee('حفظ التغييرات', false);
    }

    public function test_arabic_html_response_translates_long_mixed_case_static_sentences(): void
    {
        Route::middleware('web')->get('/static-translation-long-ar', function () {
            app()->setLocale('ar');

            return response(
                '<html><body><p>This product is active and ready in your workspace.</p><input placeholder="Describe how this product should appear in the customer portal."><img alt="User Img"></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        });

        $response = $this->followingRedirects()->get('/static-translation-long-ar');

        $response->assertOk();
        $response->assertSee('هذا المنتج نشط وجاهز في مساحة العمل الخاصة بك.', false);
        $response->assertSee('placeholder="صف كيف يجب أن يظهر هذا المنتج في بوابة العميل."', false);
        $response->assertSee('alt="مستخدم صورة"', false);
    }
}
