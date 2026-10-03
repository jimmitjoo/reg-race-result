<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    private const PAID_EVENTS = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];

    public function __invoke(Request $request): Response
    {
        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), (string) config('services.stripe.webhook_secret'));
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response('Invalid signature', 400);
        }

        $session = $event->data->object;

        if (in_array($event->type, self::PAID_EVENTS, true) && $session->payment_status === 'paid') {
            Registration::where('id', $session->metadata['registration_id'] ?? null)
                ->where('stripe_checkout_session_id', $session->id)
                ->first()
                ?->markPaid();
        }

        return response('ok');
    }
}
