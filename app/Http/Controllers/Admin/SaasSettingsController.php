<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AppSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SaasSettingsController extends Controller
{
    public function __construct(
        protected AppSettingsService $settingsService
    ) {
    }

public function edit(): View
{
    return view('admin.settings.general', [
        'freeTrialEnabled' => $this->settingsService->freeTrialEnabled(),
    ]);
}

public function update(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'free_trial_enabled' => ['nullable', 'boolean'],
    ]);

    $this->settingsService->setFreeTrialEnabled(
        (bool) ($validated['free_trial_enabled'] ?? false)
    );

    return redirect()
        ->route('admin.settings.general.edit')
        ->with('success', 'SaaS onboarding settings updated successfully.');
}
}
