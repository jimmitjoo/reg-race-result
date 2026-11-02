<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Payments\StripeCheckout;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Ask Stripe about unpaid checkouts from the last 24 hours, in case a webhook never arrived';

    public function handle(StripeCheckout $stripe): int
    {
        $count = 0;
        Registration::with('raceClass.event.organizer')
            ->whereNull('paid_at')
            ->whereNotNull('stripe_checkout_session_id')
            ->where('created_at', '>=', now()->subDay())
            ->get()
            ->each(function (Registration $registration) use ($stripe, &$count) {
                if ($stripe->isPaid($registration)) {
                    $registration->markPaid('stripe');
                    $count++;
                }
            });

        $this->info("{$count} registrations marked paid.");

        return self::SUCCESS;
    }
}
