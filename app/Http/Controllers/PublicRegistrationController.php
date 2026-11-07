<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Registration;
use App\Payments\StripeCheckout;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicRegistrationController extends Controller
{
    public function show(string $organizer, string $event, Registration $registration): View
    {
        $registrationEvent = $this->event($organizer, $event, $registration);

        // Back from Stripe before the webhook arrived: ask Stripe directly.
        if (! $registration->paid_at && $registration->stripe_checkout_session_id && app(StripeCheckout::class)->isPaid($registration)) {
            $registration->markPaid('stripe');
        }

        // What was filled in stays until the registration is paid, so a runner who left Stripe finds the form as it was (#118).
        if ($registration->paid_at) {
            session()->forget("registration.{$registrationEvent->id}");
        }

        return view('public.registration', ['registration' => $registration, 'event' => $registrationEvent]);
    }

    /** The race in the runner's calendar. */
    public function calendar(string $organizer, string $event, Registration $registration): Response
    {
        $registrationEvent = $this->event($organizer, $event, $registration);
        $class = $registration->raceClass;
        $start = $class->start_at->utc();
        $escape = fn (?string $text) => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], (string) $text);
        $url = route('public.event', ['organizer' => $organizer, 'event' => $registrationEvent->series?->slug ?? $registrationEvent->slug]);

        $ics = implode("\r\n", array_filter([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Löpning.se//Anmälan//SV',
            'BEGIN:VEVENT',
            "UID:registration-{$registration->id}@".parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$start->format('Ymd\THis\Z'),
            'DTEND:'.$start->addHours(2)->format('Ymd\THis\Z'),
            'SUMMARY:'.$escape("{$registrationEvent->name} – {$class->name}"),
            $registrationEvent->city ? 'LOCATION:'.$escape($registrationEvent->city) : null,
            'URL:'.$url,
            'END:VEVENT',
            'END:VCALENDAR',
        ]))."\r\n";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.str($registrationEvent->name)->slug().'.ics"',
        ]);
    }

    private function event(string $organizer, string $event, Registration $registration): Event
    {
        $registration->load('raceClass.event.organizer', 'raceClass.race');
        $registrationEvent = $registration->raceClass->event;
        abort_unless($registrationEvent->slug === $event && $registrationEvent->organizer->slug === $organizer, 404);

        return $registrationEvent;
    }
}
