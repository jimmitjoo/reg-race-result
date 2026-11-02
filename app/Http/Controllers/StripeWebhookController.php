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

        if ($event->type === 'account.updated' && $account) {
            $organizer = Organizer::where('stripe_account_id', $account)->first();
            $organizer && StripeConnect::apply($organizer, $object);
        }

        if (in_array($event->type, self::PAID_EVENTS, true) && $object->payment_status === 'paid') {
            $registration = Registration::with('raceClass.event.organizer')
                ->where('id', $object->metadata['registration_id'] ?? null)
                ->where('stripe_checkout_session_id', $object->id)
                ->first();

            // The payment must have been made on the account of the registration's own organizer.
            if ($registration && $registration->raceClass->event->organizer->stripe_account_id === $account) {
                $registration->markPaid('stripe');
            }
        }

        return response('ok');
    }
}
