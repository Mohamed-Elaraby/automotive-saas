<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\SubscriptionLifecycleNormalizationService;
use Illuminate\Console\Command;

class NormalizeSubscriptionLifecycleCommand extends Command
{
    protected $signature = 'billing:normalize-lifecycle
                            {--subscription-id= : Normalize only one subscription id}
                            {--apply : Apply the updates instead of preview only}';

    protected $description = 'Preview or apply lifecycle field normalization for central subscriptions.';

    public function handle(SubscriptionLifecycleNormalizationService $normalizer): int
    {
        $subscriptionId = $this->option('subscription-id');
        $apply = (bool) $this->option('apply');

        if ($subscriptionId) {
            $result = $normalizer->normalizeOne($subscriptionId, $apply);

            if (! ($result['ok'] ?? false)) {
                $this->error($result['message'] ?? 'Normalization failed.');

                return self::FAILURE;
            }

            $this->info($result['message'] ?? 'Done.');
            $this->line('Subscription ID: ' . ($result['subscription_id'] ?? '-'));
            $this->line('Status: ' . ($result['status'] ?? '-'));

            if (empty($result['changes'])) {
                $this->line('No changes needed.');

                return self::SUCCESS;
            }

            $this->table(
                ['Field', 'New Value'],
                collect($result['changes'])->map(fn ($value, $field) => [
                    'field' => $field,
                    'value' => is_null($value) ? 'NULL' : (string) $value,
                ])->values()->all()
            );

            return self::SUCCESS;
        }

        $result = $normalizer->normalizeAll($apply);

        $this->info($result['message'] ?? 'Done.');
        $this->line('Processed: ' . (int) ($result['processed'] ?? 0));
        $this->line('Changed: ' . (int) ($result['changed'] ?? 0));

        return self::SUCCESS;
    }
}
