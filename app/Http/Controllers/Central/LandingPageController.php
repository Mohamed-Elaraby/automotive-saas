<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function __invoke(): View
    {
        return view('central.landing');
    }
}
