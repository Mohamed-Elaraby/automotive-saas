<?php

namespace App\Console\Commands;

use App\Services\Admin\CentralAdminBootstrapService;
use Illuminate\Console\Command;

class BootstrapCentralAdminCommand extends Command
{
    protected $signature = 'admin:bootstrap';

    protected $description = 'Create the first central admin from environment variables if no admins exist';

    public function handle(CentralAdminBootstrapService $bootstrapService): int
    {
        $result = $bootstrapService->bootstrapFromEnv();

        if (! ($result['ok'] ?? false)) {
            $this->error((string) ($result['message'] ?? 'Central admin bootstrap failed.'));

            foreach (($result['errors'] ?? []) as $error) {
                $this->line('- ' . $error);
            }

            return self::FAILURE;
        }

        $this->info((string) ($result['message'] ?? 'Central admin bootstrap completed successfully.'));

        if (! empty($result['created']) && ! empty($result['admin'])) {
            $admin = $result['admin'];
            $this->line('Admin ID: ' . $admin->id);
            $this->line('Email: ' . $admin->email);
        }

        return self::SUCCESS;
    }
}
