<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapCentralAdminCommand extends Command
{
    protected $signature = 'admin:bootstrap';

    protected $description = 'Create the first central admin from environment variables if no admins exist';

    public function handle(): int
    {
        $connection = (new Admin())->getConnectionName() ?? config('database.default');

        if (! Schema::connection($connection)->hasTable('admins')) {
            $this->error('The admins table does not exist yet. Run migrations first.');

            return self::FAILURE;
        }

        if (Admin::query()->exists()) {
            $this->info('Central admin bootstrap skipped because admins already exist.');

            return self::SUCCESS;
        }

        $payload = [
            'name' => trim((string) env('CENTRAL_ADMIN_NAME')),
            'email' => strtolower(trim((string) env('CENTRAL_ADMIN_EMAIL'))),
            'password' => (string) env('CENTRAL_ADMIN_PASSWORD'),
        ];

        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        if ($validator->fails()) {
            $this->error('Central admin bootstrap failed because the env configuration is incomplete or invalid.');

            foreach ($validator->errors()->all() as $error) {
                $this->line('- ' . $error);
            }

            return self::FAILURE;
        }

        $admin = Admin::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
        ]);

        $this->info('Central admin bootstrap completed successfully.');
        $this->line('Admin ID: ' . $admin->id);
        $this->line('Email: ' . $admin->email);

        return self::SUCCESS;
    }
}
