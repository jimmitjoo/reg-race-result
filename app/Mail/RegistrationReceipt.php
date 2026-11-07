<?php

namespace App\Mail;

use App\Models\Registration;
use App\Models\User;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The proof of registration for a whole order (#109), sent once to its contact when it is paid. */
class RegistrationReceipt extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
        $this->locale($registration->raceClass->event->organizer->locale);
    }

    public function envelope(): Envelope
    {
        $event = $this->registration->raceClass->event;
        // Questions go to the event's contact, otherwise to someone at the club.
        $replyTo = $event->contact_email ?? User::where('organizer_id', $event->organizer_id)->orderBy('id')->value('email');

        return new Envelope(
            subject: __('receipt.subject', ['event' => $event->name]),
            replyTo: $replyTo ? [new Address($replyTo, $event->organizer->name)] : [],
        );
    }

    public function content(): Content
    {
        $registration = $this->registration->loadMissing('raceClass.race', 'raceClass.event.organizer');
        $event = $registration->raceClass->event;
        $currency = $event->organizer->currency;
        $order = $registration->order();

        return new Content(markdown: 'mail.receipt', with: [
            'registration' => $registration,
            'event' => $event,
            'lines' => $order->map(fn ($entry) => [
                'name' => $entry->displayName(),
                'class' => trim("{$entry->raceClass->race?->name} – {$entry->raceClass->name}", ' –'),
                'start' => Format::time($entry->raceClass->start_at->setTimezone($event->timezone)),
                'price' => Money::format((int) $entry->price, $currency),
            ]),
            'count' => $order->count(),
            'date' => Format::date($event->date),
            'total' => Money::format((int) $order->sum('price'), $currency),
            'method' => $registration->payment_method ? __("registrations.method.{$registration->payment_method}") : null,
            'url' => $registration->confirmationUrl(),
            'terms' => route('public.terms', $event->organizer->slug),
        ]);
    }
}
