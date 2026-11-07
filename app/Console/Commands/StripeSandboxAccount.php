<?php

namespace App\Console\Commands;

use App\Models\Organizer;
use App\Payments\StripeConnect;
use Illuminate\Console\Command;
use Stripe\StripeClient;

/**
 * A connected account that can take card payments at once, for simulating registrations locally.
 * A real club gets a full Stripe account through Stripe's own onboarding (StripeConnect), which even a sandbox
 * cannot skip; here the platform fills in Stripe's test values itself, so only ever with a test key.
 */
class StripeSandboxAccount extends Command
{
    protected $signature = 'stripe:sandbox-account {organizer : The organizer slug}';

    protected $description = 'Connect an organizer to a ready Stripe sandbox account, for trying registration with test cards';

    public function handle(): int
    {
        $key = (string) config('services.stripe.secret');
        if (! str_starts_with($key, 'sk_test_')) {
            $this->error('Bara med en testnyckel (sk_test_…) i STRIPE_SECRET.');

            return self::FAILURE;
        }

        $organizer = Organizer::where('slug', $this->argument('organizer'))->firstOrFail();
        $stripe = new StripeClient($key);
        $address = ['line1' => 'address_full_match', 'city' => 'Löttorp', 'postal_code' => '38774', 'country' => $organizer->country];

        // Stripe answers v1 account creation with a notice in sandboxes, which Laravel would turn into an error.
        set_error_handler(fn () => true, E_USER_WARNING);
        try {
            $account = $stripe->accounts->create([
                'type' => 'custom',
                'country' => $organizer->country,
                'business_type' => 'individual',
                // Stripe's test values: this birth date, address and document pass verification.
                'individual' => [
                    'first_name' => 'Test', 'last_name' => $organizer->name, 'email' => 'test@example.com', 'phone' => '+46700000000',
                    'dob' => ['day' => 1, 'month' => 1, 'year' => 1901], 'address' => $address,
                    'verification' => ['document' => ['front' => 'file_identity_document_success']],
                ],
                'business_profile' => ['name' => $organizer->name, 'mcc' => '7941', 'url' => 'https://example.com', 'product_description' => 'Anmälan till lopp'],
                'capabilities' => ['card_payments' => ['requested' => true], 'transfers' => ['requested' => true]],
                'external_account' => ['object' => 'bank_account', 'country' => 'SE', 'currency' => 'sek', 'account_number' => 'SE3550000000054910000003'],
                'tos_acceptance' => ['date' => time(), 'ip' => '127.0.0.1'],
                'metadata' => ['organizer_id' => (string) $organizer->id, 'sandbox' => 'local simulation'],
            ]);
        } finally {
            restore_error_handler();
        }

        $this->info("Konto {$account->id} skapat. Stripe kontrollerar testuppgifterna, det tar ungefär en minut …");
        $organizer->update(['stripe_account_id' => $account->id]);

        for ($tries = 30; $tries > 0; $tries--) {
            sleep(4);
            app(StripeConnect::class)->sync($organizer);
            if ($organizer->refresh()->canTakePayments()) {
                $this->info("{$organizer->name} kan ta betalt. Betala med kortet 4242 4242 4242 4242.");

                return self::SUCCESS;
            }
        }

        $this->warn('Kontot är inte klart än. Statusen uppdateras när Stripe är klar (webhooken account.updated).');

        return self::SUCCESS;
    }
}
