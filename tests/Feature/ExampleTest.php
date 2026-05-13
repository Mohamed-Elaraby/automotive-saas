<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_to_the_localized_marketing_homepage(): void
    {
        $response = $this->get('https://seven-scapital.com/');

        $response->assertRedirect(route('marketing.home', ['locale' => 'en']));
    }

    public function test_the_localized_marketing_homepage_returns_a_successful_response(): void
    {
        $response = $this->get('https://seven-scapital.com/en');

        $response->assertOk();
        $response->assertSee('Automotive Business Management Software', false);
    }
}
