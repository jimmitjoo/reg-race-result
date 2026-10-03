<?php

namespace App\Payments;

use App\Models\Registration;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Stripe Checkout (Stripe's hosted payment page). Card details never touch our server.
 * When the organizer has a connected account the payment goes straight to it.
 */
class StripeCheckout
{
    /** @return array{id: string, url: string} */
    public function create(Registration $registration, string $successUrl, string $cancelUrl): array
    {
        $session = $this->client()->checkout->sessions->create(
            $this->params($registration, $successUrl, $cancelUrl),
            $this->options($registration),
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

        return $session->payment_status === 'paid';
    }

    public function params(Registration $registration, string $successUrl, string $cancelUrl): array
    {
        $class = $registration->raceClass;
        $event = $class->event;

        return [
            'mode' => 'payment',
            'customer_email' => $registration->email,
            'client_reference_id' => (string) $registration->id,
            'metadata' => ['registration_id' => (string) $registration->id],
            'locale' => app()->getLocale(),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($event->organizer->currency),
                    'unit_amount' => $registration->price,
                    'product_data' => ['name' => "{$event->name} – {$class->name}"],
                ],
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];
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
