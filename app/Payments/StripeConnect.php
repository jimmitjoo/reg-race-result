<?php

namespace App\Payments;

use App\Models\Organizer;
use Stripe\StripeClient;

/**
 * Stripe Connect with full Stripe accounts (what v1 called Standard): the club gets its own Stripe account
 * and login, payments go straight to it, and the club handles refunds, disputes and losses itself.
 */
class StripeConnect
{
    /**
     * Creates the connected account the first time only, so a second click does not make another one.
     * Accounts v2, which Stripe asks for; v1 accounts->create answers with a notice that Laravel turns into an error.
     */
    public function onboardingUrl(Organizer $organizer, string $refreshUrl, string $returnUrl): string
    {
        if (! $organizer->stripe_account_id) {
            $account = $this->client()->v2->core->accounts->create([
                'display_name' => $organizer->name,
                'dashboard' => 'full',
                'identity' => ['country' => strtolower($organizer->country)],
                'configuration' => ['merchant' => ['capabilities' => ['card_payments' => ['requested' => true]]]],
                'defaults' => ['responsibilities' => ['fees_collector' => 'stripe', 'losses_collector' => 'stripe']],
                'metadata' => ['organizer_id' => (string) $organizer->id],
            ]);
            $organizer->update(['stripe_account_id' => $account->id]);
        }

        return $this->client()->v2->core->accountLinks->create([
            'account' => $organizer->stripe_account_id,
            'use_case' => ['type' => 'account_onboarding', 'account_onboarding' => ['refresh_url' => $refreshUrl, 'return_url' => $returnUrl]],
        ])->url;
    }

    /** Asks Stripe instead of trusting that the user came back from onboarding. v1 still reads a v2 account, without a notice. */
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
