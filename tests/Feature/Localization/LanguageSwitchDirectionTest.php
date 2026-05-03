<?php

namespace Tests\Feature\Localization;

use Tests\TestCase;

class LanguageSwitchDirectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    public function test_arabic_uses_kanakku_rtl_body_class_and_rtl_bootstrap(): void
    {
        $response = $this->followingRedirects()->get('/ar/admin/login');

        $response->assertOk();
        $response->assertSee('lang="ar"', false);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('layout-mode-rtl', false);
        $response->assertSee('theme/css/bootstrap.rtl.min.css', false);
    }

    public function test_english_switch_clears_arabic_session_and_returns_ltr_theme(): void
    {
        $this->followingRedirects()->get('/ar/admin/login')->assertOk();

        $response = $this->followingRedirects()->get('/admin/login');

        $response->assertOk();
        $response->assertSee('lang="en"', false);
        $response->assertSee('dir="ltr"', false);
        $response->assertSee('theme/css/bootstrap.min.css', false);
        $response->assertDontSee('layout-mode-rtl', false);
        $response->assertDontSee('theme/css/bootstrap.rtl.min.css', false);
    }

    public function test_english_switch_from_arabic_prefixed_url_returns_ltr_theme(): void
    {
        $response = $this->followingRedirects()->get('/ar/admin/login?_locale=en');

        $response->assertOk();
        $response->assertSee('lang="en"', false);
        $response->assertSee('dir="ltr"', false);
        $response->assertSee('theme/css/bootstrap.min.css', false);
        $response->assertDontSee('layout-mode-rtl', false);
        $response->assertDontSee('theme/css/bootstrap.rtl.min.css', false);
    }

    public function test_product_layouts_do_not_load_kanakku_demo_customizer(): void
    {
        $layoutHeads = [
            resource_path('views/automotive/admin/layouts/adminLayout/partials/head.blade.php'),
            resource_path('views/automotive/portal/layouts/portalLayout/partials/head.blade.php'),
        ];

        foreach ($layoutHeads as $layoutHead) {
            $contents = file_get_contents($layoutHead);

            $this->assertStringNotContainsString('theme/js/theme-script.js', $contents);
            $this->assertStringNotContainsString('body.layout-mode-rtl .two-col-sidebar', $contents);
            $this->assertStringContainsString('.sidebar-themesettings', $contents);
        }
    }
}
