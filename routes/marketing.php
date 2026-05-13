<?php

declare(strict_types=1);

use App\Http\Controllers\Marketing\LeadController;
use App\Http\Controllers\Marketing\MarketingPageController;
use App\Http\Controllers\Marketing\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing routes (Seven S Automotive public website)
|--------------------------------------------------------------------------
|
| These routes are loaded from routes/web.php BEFORE the LaravelLocalization
| group so that explicit /en and /ar URLs match here first. Marketing routes
| do NOT use the LaravelLocalization middleware — locale handling is done by
| App\Http\Middleware\SetMarketingLocale, which validates {locale} ∈ {en, ar}
| and calls App::setLocale().
|
| Tenant subdomains never reach these routes (Stancl tenancy isolates
| InitializeTenancyByDomain + PreventAccessFromCentralDomains).
*/

Route::get('/', [MarketingPageController::class, 'rootRedirect'])
    ->name('marketing.root');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])
    ->name('marketing.sitemap');

Route::prefix('{locale}')
    ->where(['locale' => 'en|ar'])
    ->middleware(['marketing.locale'])
    ->name('marketing.')
    ->group(function (): void {
        Route::get('/', [MarketingPageController::class, 'home'])->name('home');

        Route::prefix('products')->name('products.')->group(function (): void {
            Route::get('/', [MarketingPageController::class, 'productsIndex'])->name('index');
            Route::get('/workshop-management-software', [MarketingPageController::class, 'productWorkshop'])->name('workshop');
            Route::get('/spare-parts-inventory-management-software', [MarketingPageController::class, 'productSpareParts'])->name('spare-parts');
            Route::get('/automotive-accounting-software', [MarketingPageController::class, 'productAccounting'])->name('accounting');
        });

        Route::get('/pricing', [MarketingPageController::class, 'pricing'])->name('pricing');
        Route::get('/security', [MarketingPageController::class, 'security'])->name('security');
        Route::get('/privacy-policy', [MarketingPageController::class, 'privacyPolicy'])->name('privacy');
        Route::get('/terms-of-service', [MarketingPageController::class, 'termsOfService'])->name('terms');

        // Lead pages
        Route::get('/book-demo', [LeadController::class, 'showBookDemo'])->name('book-demo');
        Route::post('/book-demo', [LeadController::class, 'submitBookDemo'])->name('book-demo.submit');

        Route::get('/start-trial', [LeadController::class, 'showStartTrial'])->name('start-trial');
        Route::post('/start-trial', [LeadController::class, 'submitStartTrial'])->name('start-trial.submit');

        Route::get('/contact', [LeadController::class, 'showContact'])->name('contact');
        Route::post('/contact', [LeadController::class, 'submitContact'])->name('contact.submit');

        Route::get('/thank-you/{kind}', [LeadController::class, 'thankYou'])
            ->where('kind', 'demo|trial|contact')
            ->name('thank-you');
    });
