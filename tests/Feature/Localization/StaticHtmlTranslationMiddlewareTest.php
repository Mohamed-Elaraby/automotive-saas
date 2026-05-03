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
        $response->assertSee('ملخص مدفوعات العملاء', false);
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
        $response->assertSee('alt="صورة المستخدم"', false);
    }

    public function test_arabic_html_response_keeps_javascript_template_literals_raw(): void
    {
        Route::middleware('web')->get('/static-translation-script-template-ar', function () {
            app()->setLocale('ar');

            return response(
                '<html><body><script>list.innerHTML = items.map((item) => `<div class="portal-notification-open-link">${escapeHtml(item.title || notificationFallbackLabel)}</div>`).join("");</script><p>This is the accounting runtime entry point for ledgers, journals, and future finance modules inside the shared tenant workspace.</p></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        });

        $response = $this->followingRedirects()->get('/static-translation-script-template-ar');

        $response->assertOk();
        $response->assertSee('<script>list.innerHTML = items.map((item) => `<div class="portal-notification-open-link">${escapeHtml(item.title || notificationFallbackLabel)}</div>`).join("");</script>', false);
        $response->assertDontSee('<p><script>', false);
        $response->assertDontSee('</script></p>', false);
        $response->assertSee('هذه هي نقطة تشغيل المحاسبة لدفاتر الأستاذ والقيود ووحدات التمويل المستقبلية داخل مساحة عمل العميل المشتركة.', false);
    }

    public function test_arabic_html_response_keeps_head_assets_out_of_paragraphs(): void
    {
        Route::middleware('web')->get('/static-translation-head-assets-ar', function () {
            app()->setLocale('ar');

            return response(
                '<!DOCTYPE html><html lang="ar" dir="rtl"><head><style>.language-switcher{display:flex}</style><link rel="stylesheet" href="/theme/css/style.css"></head><body><p>Save Changes</p></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        });

        $response = $this->followingRedirects()->get('/static-translation-head-assets-ar');

        $response->assertOk();
        $response->assertSee('<head><style>.language-switcher{display:flex}</style><link rel="stylesheet" href="/theme/css/style.css"></head>', false);
        $response->assertDontSee('<p><style>', false);
        $response->assertDontSee('<p><link', false);
        $response->assertDontSee('</style><link rel="stylesheet" href="/theme/css/style.css"></p>', false);
        $response->assertSee('<p>حفظ التغييرات</p>', false);
    }

    public function test_arabic_html_response_translates_known_workspace_catalog_phrases_inside_mixed_lines(): void
    {
        Route::middleware('web')->get('/static-translation-workspace-catalog-ar', function () {
            app()->setLocale('ar');

            return response(
                '<html><body><p>Accounting Focus</p><p>Finance workspace foundation</p><p>Shared modules stay global across the tenant. Accounting contributes only its own finance modules, such as the general ledger.</p><p>Accounting System Starter · متصل بمساحة العمل</p><p>active</p></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        });

        $response = $this->followingRedirects()->get('/static-translation-workspace-catalog-ar');

        $response->assertOk();
        $response->assertSee('تركيز المحاسبة', false);
        $response->assertSee('أساس مساحة عمل التمويل', false);
        $response->assertSee('تبقى الوحدات المشتركة عامة على مستوى العميل. تضيف المحاسبة وحداتها المالية فقط، مثل دفتر الأستاذ العام.', false);
        $response->assertSee('بداية النظام المحاسبي · متصل بمساحة العمل', false);
        $response->assertSee('نشط', false);
        $response->assertDontSee('Accounting Focus', false);
        $response->assertDontSee('Finance workspace foundation', false);
    }

    public function test_arabic_html_response_translates_general_ledger_accounting_review_copy(): void
    {
        Route::middleware('web')->get('/static-translation-general-ledger-ar', function () {
            app()->setLocale('ar');

            return response(
                '<html><body><h5>Accountant Review Pack</h5><table><thead><tr><th>Section</th><th>Metric</th><th>Value</th><th>Evidence Source</th></tr></thead><tbody><tr><td>Ledger Control</td><td>Accounting source of truth</td><td>journal_entries + journal_entry_lines</td><td>ledger_policy</td></tr><tr><td>Approvals</td><td>Pending manual journal approvals</td><td>0</td><td>journal_entries</td></tr></tbody></table><div>Accounting source of truth: journal + entries + and + journal + entry + lines</div><h5>Financial Statement Builder</h5><p>Profit And Loss</p><p>Journal-driven operating view.</p><p>Service Labor Revenue · Service revenue</p><p>Multi-Currency And FX Revaluation</p><p>Base currency: USD. Revaluation journals remain the accounting source of truth for unrealized FX.</p><p>Recent FX Revaluations</p><p>No FX revaluations have been posted yet.</p></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        });

        $response = $this->followingRedirects()->get('/static-translation-general-ledger-ar');

        $response->assertOk();
        $response->assertSee('حزمة مراجعة المحاسب', false);
        $response->assertSee('المؤشر', false);
        $response->assertSee('مصدر الدليل', false);
        $response->assertSee('رقابة دفتر الأستاذ', false);
        $response->assertSee('موافقات القيود اليدوية المعلقة', false);
        $response->assertSee('مصدر الحقيقة المحاسبي: قيود اليومية وبنود القيود', false);
        $response->assertSee('منشئ القوائم المالية', false);
        $response->assertSee('إيراد عمالة الخدمة · إيراد خدمة', false);
        $response->assertSee('تعدد العملات وإعادة تقييم فروق العملة', false);
        $response->assertDontSee('Accountant Review Pack', false);
        $response->assertDontSee('Financial Statement Builder', false);
    }
}
