<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetMarketingLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $accept = strtolower((string) $request->header('Accept-Language', ''));
        $locale = SetMarketingLocale::DEFAULT_LOCALE;

        if (str_starts_with($accept, 'ar') || str_contains($accept, ',ar') || str_contains($accept, ';ar')) {
            $locale = 'ar';
        }

        return redirect()->route('marketing.home', ['locale' => $locale]);
    }
}
