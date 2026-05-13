<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\MarketingLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingSiteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider marketingPageProvider
     */
    public function test_marketing_pages_render(string $path, string $expectedText): void
    {
        $response = $this->get("https://seven-scapital.com{$path}");

        $response->assertOk();
        $response->assertSee($expectedText, false);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function marketingPageProvider(): array
    {
        return [
            'home' => ['/en', 'Automotive Business Management Software'],
            'products' => ['/en/products', 'Three integrated systems'],
            'workshop' => ['/en/products/workshop-management-software', 'Workshop Management Software'],
            'spare parts' => ['/en/products/spare-parts-inventory-management-software', 'Spare Parts Inventory Management Software'],
            'accounting' => ['/en/products/automotive-accounting-software', 'Automotive Accounting Software'],
            'pricing' => ['/en/pricing', 'Plans built for automotive businesses'],
            'security' => ['/en/security', 'Your business data, protected by design'],
            'privacy' => ['/en/privacy-policy', 'Privacy Policy'],
            'terms' => ['/en/terms-of-service', 'Terms of Service'],
            'book demo' => ['/en/book-demo', 'Book a personal demo'],
            'start trial' => ['/en/start-trial', 'Start your free trial'],
            'contact' => ['/en/contact', 'Contact our sales team'],
        ];
    }

    public function test_sitemap_renders_localized_marketing_urls(): void
    {
        $response = $this->get('https://seven-scapital.com/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<loc>https://seven-scapital.com/en</loc>', false);
        $response->assertSee('<xhtml:link rel="alternate" hreflang="ar"', false);
    }

    public function test_marketing_lead_submission_is_persisted(): void
    {
        $response = $this->post('https://seven-scapital.com/en/book-demo', [
            'full_name' => 'Test Customer',
            'company_name' => 'Test Workshop',
            'business_type' => 'auto_repair_workshop',
            'country' => 'AE',
            'phone' => '+971500000000',
            'email' => 'customer@example.com',
            'branches_count' => 2,
            'interested_system' => 'workshop_management',
            'preferred_language' => 'en',
            'message' => 'I want a demo.',
            'website' => '',
        ]);

        $response->assertRedirect('https://seven-scapital.com/en/thank-you/demo');

        $this->assertDatabaseHas('marketing_leads', [
            'kind' => MarketingLead::KIND_BOOK_DEMO,
            'locale' => 'en',
            'full_name' => 'Test Customer',
            'email' => 'customer@example.com',
            'status' => MarketingLead::STATUS_NEW,
        ]);
    }
}
