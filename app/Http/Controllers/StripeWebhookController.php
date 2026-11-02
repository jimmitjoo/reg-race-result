<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use App\Models\Registration;
use App\Payments\StripeConnect;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Two endpoints in Stripe: one for the platform account, one for events on connected accounts
 * (a direct charge's checkout.session.completed only reaches the Connect endpoint).
 */
class StripeWebhookController extends Controller
{
    private const PAID_EVENTS = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];

    public function platform(Request $request): Response
    {
        return $this->handle($request, (string) config('services.stripe.webhook_secret'));
    }

    public function connect(Request $request): Response
    {
        return $this->handle($request, (string) config('services.stripe.connect_webhook_secret'));
    }

    private function handle(Request $request, string $secret): Response
    {
        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response('Invalid signature', 400);
        }

        $object = $event->data->object;
        $account = $event->account ?? null;

        $organizer = $account ? Organizer::where('stripe_account_id', $account)->first() : null;

        if ($event->type === 'account.updated' && $organizer) {
            StripeConnect::apply($organizer, $object);
        }

        // The club disconnected the platform in Stripe: no more online payments until it connects again.
        if ($event->type === 'account.application.deauthorized' && $organizer) {
            $organizer->update(['stripe_account_id' => null, 'stripe_details_submitted' => false, 'stripe_charges_enabled' => false]);
        }

        // Refunded in full in the club's dashboard; a partial refund leaves the runner in.
        if ($event->type === 'charge.refunded' && $organizer && $object->refunded && $object->payment_intent) {
            Registration::where('stripe_payment_intent_id', $object->payment_intent)
                ->whereNull('refunded_at')
                ->whereHas('raceClass.event', fn ($q) => $q->where('organizer_id', $organizer->id))
                ->update(['refunded_at' => now()]);
        }

        if (in_array($event->type, self::PAID_EVENTS, true) && $object->payment_status === 'paid') {
            $registration = Registration::with('raceClass.event.organizer')
                ->where('id', $object->metadata['registration_id'] ?? null)
                ->where('stripe_checkout_session_id', $object->id)
                ->first();

            // The payment must have been made on the account of the registration's own organizer.
            if ($registration && $registration->raceClass->event->organizer->stripe_account_id === $account) {
                $registration->markPaid('stripe', $object->payment_intent ?? null);
            }
        }

        return response('ok');
    }
}
