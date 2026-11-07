<?php

namespace App\Payments;

use App\Models\Registration;
use Illuminate\Support\Collection;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Stripe Checkout (Stripe's hosted payment page). Card details never touch our server.
 * When the organizer has a connected account the payment goes straight to it (direct charge), minus the platform fee.
 */
class StripeCheckout
{
    /**
     * One Checkout for an order (#109): a line per participant who pays.
     *
     * @param  Collection<int, Registration>  $order
     * @return array{id: string, url: string}
     */
    public function create(Collection $order, string $successUrl, string $cancelUrl): array
    {
        $session = $this->client()->checkout->sessions->create(
            $this->params($order, $successUrl, $cancelUrl),
            $this->options($order->first()),
        );

        return ['id' => $session->id, 'url' => $session->url];
    }

    public function isPaid(Registration $registration): bool
    {
        try {
            $session = $this->client()->checkout->sessions->retrieve($registration->stripe_checkout_session_id, [], $this->options($registration));
        } catch (ApiErrorException) {
            return false;
        }

        if ($session->payment_status !== 'paid') {
            return false;
        }
        // Remembered so that a refund in the club's Stripe dashboard finds the order; markPaid() gives it to every registration in it.
        $registration->stripe_payment_intent_id = $session->payment_intent;

        return true;
    }

    /** @param  Collection<int, Registration>  $order */
    public function params(Collection $order, string $successUrl, string $cancelUrl): array
    {
        $first = $order->first();
        $event = $first->raceClass->event;
        $organizer = $event->organizer;
        // A free participant in the order is no line in Stripe; they are marked paid with the rest.
        $paying = $order->filter(fn (Registration $registration) => $registration->price > 0);
        $fee = $organizer->stripe_account_id ? $paying->sum(fn (Registration $registration) => $organizer->platformFee($registration->price)) : 0;

        return array_filter([
            'mode' => 'payment',
            'customer_email' => $first->email,
            'client_reference_id' => (string) $first->id,
            'metadata' => array_filter(['registration_id' => (string) $first->id, 'order_ref' => $first->order_ref]),
            'locale' => app()->getLocale(),
            'line_items' => $paying->map(fn (Registration $registration) => [
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($organizer->currency),
                    'unit_amount' => $registration->price,
                    'product_data' => ['name' => "{$registration->displayName()}, {$event->name} – {$registration->raceClass->name}"],
                ],
            ])->values()->all(),
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'payment_intent_data' => $fee ? ['application_fee_amount' => $fee] : null,
        ], fn ($value) => $value !== null);
    }

    private function options(Registration $registration): array
    {
        $account = $registration->raceClass->event->organizer->stripe_account_id;

        return $account ? ['stripe_account' => $account] : [];
    }

    private function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}
