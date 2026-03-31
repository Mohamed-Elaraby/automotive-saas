<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
        {name : The admin display name}
        {email : The admin email address}
        {password : The admin password}';

    protected $description = 'Create a central admin account';

    public function handle(): int
    {
        $payload = [
            'name' => (string) $this->argument('name'),
            'email' => strtolower(trim((string) $this->argument('email'))),
            'password' => (string) $this->argument('password'),
        ];

        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = Admin::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
        ]);

        $this->info('Central admin created successfully.');
        $this->line('Admin ID: ' . $admin->id);
        $this->line('Email: ' . $admin->email);

        return self::SUCCESS;
    }
}
