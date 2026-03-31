<?php

namespace Database\Seeders;

use App\Services\Admin\CentralAdminBootstrapService;
use Illuminate\Database\Seeder;
use RuntimeException;

class CentralAdminSeeder extends Seeder
{
    public function run(): void
    {
        $result = app(CentralAdminBootstrapService::class)->bootstrapFromEnv();

        if (! ($result['ok'] ?? false)) {
            $message = (string) ($result['message'] ?? 'Central admin bootstrap failed.');
            $errors = $result['errors'] ?? [];

            if (! empty($errors)) {
                $message .= ' ' . implode(' | ', $errors);
            }

            throw new RuntimeException($message);
        }

        if ($this->command) {
            $this->command->info((string) ($result['message'] ?? 'Central admin bootstrap completed.'));
        }
    }
}
