<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Payments\StripeCheckout;
use Illuminate\View\View;

class PublicRegistrationController extends Controller
{
    public function show(string $organizer, string $event, Registration $registration): View
    {
        $registration->load('raceClass.event.organizer');
        $registrationEvent = $registration->raceClass->event;
        abort_unless($registrationEvent->slug === $event && $registrationEvent->organizer->slug === $organizer, 404);

        // Back from Stripe before the webhook arrived: ask Stripe directly.
        if (! $registration->paid_at && $registration->stripe_checkout_session_id && app(StripeCheckout::class)->isPaid($registration)) {
            $registration->markPaid('stripe');
        }

        return view('public.registration', ['registration' => $registration, 'event' => $registrationEvent]);
    }
}
