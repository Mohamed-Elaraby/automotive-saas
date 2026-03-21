<?php

namespace App\Console\Commands\Billing;

use App\Models\Subscription;
use App\Services\Billing\StripeInvoiceLedgerBackfillService;
use Illuminate\Console\Command;

class BackfillStripeInvoicesCommand extends Command
{
    protected $signature = 'billing:backfill-stripe-invoices {subscription_id?} {--limit=100}';

    protected $description = 'Backfill Stripe invoices into the local billing_invoices ledger';

    public function handle(StripeInvoiceLedgerBackfillService $service): int
    {
        $subscriptionId = $this->argument('subscription_id');
        $limit = (int) $this->option('limit');

        $query = Subscription::query()
            ->where('gateway', 'stripe')
            ->whereNotNull('gateway_customer_id');

        if (! empty($subscriptionId)) {
            $query->where('id', $subscriptionId);
        }

        $subscriptions = $query->get();

        if ($subscriptions->isEmpty()) {
            $this->warn('No Stripe-linked subscriptions found.');
            return self::SUCCESS;
        }

        foreach ($subscriptions as $subscription) {
            $result = $service->backfillForSubscription($subscription, $limit);

            if ($result['ok']) {
                $this->info("Subscription #{$subscription->id}: {$result['message']}");
            } else {
                $this->error("Subscription #{$subscription->id}: {$result['message']}");
            }
        }

        return self::SUCCESS;
    }
}
