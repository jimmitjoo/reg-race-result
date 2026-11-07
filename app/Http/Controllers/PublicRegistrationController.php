<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Registration;
use App\Payments\StripeCheckout;
use App\Support\Receipt;
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

        return view('public.registration', ['registration' => $registration, 'order' => $registration->order(), 'event' => $registrationEvent]);
    }

    /** The receipt for the order, to print or save (#132); only once it is paid. */
    public function receipt(string $organizer, string $event, Registration $registration): View
    {
        $registrationEvent = $this->event($organizer, $event, $registration);
        abort_unless($registration->paid_at, 404);

        return view('public.receipt', ['receipt' => Receipt::for($registration), 'event' => $registrationEvent]);
    }

    /** The race in the runner's calendar, once per participant in the order. */
    public function calendar(string $organizer, string $event, Registration $registration): Response
    {
        $registrationEvent = $this->event($organizer, $event, $registration);
        $escape = fn (?string $text) => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], (string) $text);
        $order = $registration->order();
        $url = route('public.event', ['organizer' => $organizer, 'event' => $registrationEvent->series?->slug ?? $registrationEvent->slug]);

        $ics = implode("\r\n", array_filter([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Löpning.se//Anmälan//SV',
            ...$order->flatMap(function (Registration $entry) use ($registrationEvent, $escape, $url, $order) {
                $start = $entry->raceClass->start_at->utc();
                // Whose race it is, when the order has several.
                $name = $order->count() > 1 ? "{$entry->displayName()}: " : '';

                return [
                    'BEGIN:VEVENT',
                    "UID:registration-{$entry->id}@".parse_url(config('app.url'), PHP_URL_HOST),
                    'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
                    'DTSTART:'.$start->format('Ymd\THis\Z'),
                    'DTEND:'.$start->addHours(2)->format('Ymd\THis\Z'),
                    'SUMMARY:'.$escape("{$name}{$registrationEvent->name} – {$entry->raceClass->name}"),
                    $registrationEvent->city ? 'LOCATION:'.$escape($registrationEvent->city) : null,
                    'URL:'.$url,
                    'END:VEVENT',
                ];
            }),
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
