<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\LeadFormRequest;
use App\Models\Marketing\MarketingLead;
use App\Services\Marketing\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function __construct(protected LeadService $leadService)
    {
    }

    public function showBookDemo(string $locale): View
    {
        return $this->renderForm('book-demo', $locale, MarketingLead::KIND_BOOK_DEMO);
    }

    public function submitBookDemo(LeadFormRequest $request, string $locale): RedirectResponse
    {
        return $this->handleSubmission($request, $locale, MarketingLead::KIND_BOOK_DEMO, 'demo');
    }

    public function showStartTrial(string $locale): View
    {
        return $this->renderForm('start-trial', $locale, MarketingLead::KIND_START_TRIAL);
    }

    public function submitStartTrial(LeadFormRequest $request, string $locale): RedirectResponse
    {
        return $this->handleSubmission($request, $locale, MarketingLead::KIND_START_TRIAL, 'trial');
    }

    public function showContact(string $locale): View
    {
        return $this->renderForm('contact', $locale, MarketingLead::KIND_CONTACT);
    }

    public function submitContact(LeadFormRequest $request, string $locale): RedirectResponse
    {
        return $this->handleSubmission($request, $locale, MarketingLead::KIND_CONTACT, 'contact');
    }

    public function thankYou(string $locale, string $kind): View
    {
        return view('marketing.pages.lead-success', [
            'locale' => $locale,
            'kind'   => $kind,
            'seo'    => [
                'title'        => __("marketing.thank_you.{$kind}.meta_title"),
                'description'  => __("marketing.thank_you.{$kind}.meta_description"),
                'canonical'    => route('marketing.thank-you', ['locale' => $locale, 'kind' => $kind]),
                'route_name'   => 'marketing.thank-you',
                'route_params' => ['kind' => $kind],
                'og_image'     => asset(config('seo.defaults.og_image')),
                'og_type'      => 'website',
                'jsonld'       => [],
                'page_key'     => 'thank_you',
                'no_index'     => true,
            ],
        ]);
    }

    protected function renderForm(string $page, string $locale, string $kind): View
    {
        $pageKey = match ($page) {
            'book-demo'   => 'book_demo',
            'start-trial' => 'start_trial',
            'contact'     => 'contact',
            default       => 'contact',
        };

        $routeName = "marketing.{$page}";

        return view("marketing.pages.{$page}", [
            'locale' => $locale,
            'kind'   => $kind,
            'seo'    => [
                'title'        => __("marketing.{$pageKey}.meta_title"),
                'description'  => __("marketing.{$pageKey}.meta_description"),
                'canonical'    => route($routeName, ['locale' => $locale]),
                'route_name'   => $routeName,
                'route_params' => [],
                'og_image'     => asset(config('seo.defaults.og_image')),
                'og_type'      => 'website',
                'jsonld'       => $page === 'contact' ? ['contact-page', 'breadcrumb-list'] : ['breadcrumb-list'],
                'page_key'     => $pageKey,
            ],
            'breadcrumbs' => [
                ['title' => __('marketing.nav.home'), 'url' => route('marketing.home', ['locale' => $locale])],
                ['title' => __("marketing.{$pageKey}.crumb"), 'url' => null],
            ],
            'businessTypes'      => config('marketing.business_types', []),
            'interestedSystems'  => config('marketing.interested_systems', []),
            'preferredLanguages' => config('marketing.preferred_languages', ['en', 'ar']),
            'countries'          => config('marketing.countries', []),
        ]);
    }

    protected function handleSubmission(Request $request, string $locale, string $kind, string $thankYouSlug): RedirectResponse
    {
        $payload = array_merge($request->validated(), ['locale' => $locale]);
        $this->leadService->record($kind, $payload, $request);

        return redirect()
            ->route('marketing.thank-you', ['locale' => $locale, 'kind' => $thankYouSlug])
            ->with('marketing_lead_submitted', true);
    }
}
