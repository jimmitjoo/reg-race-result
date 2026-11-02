<?php

namespace App\Payments;

use App\Models\Organizer;
use Stripe\StripeClient;

/**
 * Stripe Connect with Standard accounts: the club gets its own Stripe account and login,
 * payments go straight to it, and the club handles refunds and disputes itself.
 */
class StripeConnect
{
    /** Creates the connected account the first time only, so a second click does not make another one. */
    public function onboardingUrl(Organizer $organizer, string $refreshUrl, string $returnUrl): string
    {
        if (! $organizer->stripe_account_id) {
            $account = $this->client()->accounts->create(['type' => 'standard', 'country' => $organizer->country, 'metadata' => ['organizer_id' => (string) $organizer->id]]);
            $organizer->update(['stripe_account_id' => $account->id]);
        }

        return $this->client()->accountLinks->create([
            'account' => $organizer->stripe_account_id,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ])->url;
    }

    /** Asks Stripe instead of trusting that the user came back from onboarding. */
    public function sync(Organizer $organizer): void
    {
        if ($organizer->stripe_account_id) {
            self::apply($organizer, $this->client()->accounts->retrieve($organizer->stripe_account_id));
        }
    }

    public static function apply(Organizer $organizer, object $account): void
    {
        $organizer->update([
            'stripe_details_submitted' => (bool) $account->details_submitted,
            'stripe_charges_enabled' => (bool) $account->charges_enabled,
        ]);
    }

    private function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}
