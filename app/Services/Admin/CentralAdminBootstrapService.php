<?php

namespace App\Services\Admin;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CentralAdminBootstrapService
{
    public function bootstrapFromEnv(): array
    {
        $connection = (new Admin())->getConnectionName() ?? config('database.default');

        if (! Schema::connection($connection)->hasTable('admins')) {
            return [
                'ok' => false,
                'message' => 'The admins table does not exist yet. Run migrations first.',
                'errors' => [],
            ];
        }

        if (Admin::query()->exists()) {
            return [
                'ok' => true,
                'created' => false,
                'message' => 'Central admin bootstrap skipped because admins already exist.',
                'admin' => null,
                'errors' => [],
            ];
        }

        $payload = [
            'name' => trim((string) env('CENTRAL_ADMIN_NAME')),
            'email' => strtolower(trim((string) env('CENTRAL_ADMIN_EMAIL'))),
            'password' => (string) env('CENTRAL_ADMIN_PASSWORD'),
        ];

        $hasAnyBootstrapValue = filled($payload['name'])
            || filled($payload['email'])
            || filled($payload['password']);

        if (! $hasAnyBootstrapValue) {
            return [
                'ok' => true,
                'created' => false,
                'message' => 'Central admin bootstrap skipped because CENTRAL_ADMIN_* values are not configured.',
                'admin' => null,
                'errors' => [],
            ];
        }

        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return [
                'ok' => false,
                'message' => 'Central admin bootstrap failed because the env configuration is incomplete or invalid.',
                'errors' => $validator->errors()->all(),
            ];
        }

        $admin = Admin::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
        ]);

        return [
            'ok' => true,
            'created' => true,
            'message' => 'Central admin bootstrap completed successfully.',
            'admin' => $admin,
            'errors' => [],
        ];
    }
}
