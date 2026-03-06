<?php

namespace App\Http\Controllers\Automotive;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartTrialRequest;
use App\Services\Automotive\StartTrialService;

class TrialSignupController extends Controller
{
    public function __invoke(StartTrialRequest $request, StartTrialService $service)
    {
        $result = $service->start($request->validated());

        if (!($result['ok'] ?? false)) {
            return response()->json([
                'message' => $result['message'] ?? 'Error',
                'errors' => $result['errors'] ?? [],
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'ok' => true,
            'tenant_id' => $result['tenant_id'],
            'domain' => $result['domain'],
            'login_url' => $result['login_url'],
        ], 201);
    }
}
