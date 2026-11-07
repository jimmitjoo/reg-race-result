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

/** The runner's proof of registration, sent once when it is paid. */
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
        $class = $registration->raceClass;
        $event = $class->event;

        return new Content(markdown: 'mail.receipt', with: [
            'registration' => $registration,
            'event' => $event,
            'race' => $class->race?->name,
            'class' => $class->name,
            'date' => Format::date($event->date),
            'start' => Format::time($class->start_at->setTimezone($event->timezone)),
            'price' => Money::format((int) $registration->price, $event->organizer->currency),
            'method' => $registration->payment_method ? __("registrations.method.{$registration->payment_method}") : null,
            'url' => $registration->confirmationUrl(),
            'terms' => route('public.terms', $event->organizer->slug),
        ]);
    }
}
