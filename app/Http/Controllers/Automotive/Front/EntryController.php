<?php

namespace App\Http\Controllers\Automotive\Front;

use App\Http\Controllers\Controller;
use App\Services\Admin\AppSettingsService;
use Illuminate\Contracts\View\View;

class EntryController extends Controller
{
    public function __construct(
        protected AppSettingsService $settingsService
    ) {
    }

public function index(): View
{
    return view('automotive.front.entry', [
        'freeTrialEnabled' => $this->settingsService->freeTrialEnabled(),
    ]);
}
}
