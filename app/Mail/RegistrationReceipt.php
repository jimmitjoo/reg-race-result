<?php

namespace App\Mail;

use App\Models\Registration;
use App\Models\User;
use App\Support\Format;
use App\Support\Receipt;
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
        $count = $registration->order()->count();

        return new Content(markdown: 'mail.receipt', with: [
            'event' => $event,
            'intro' => $count > 1
                ? __('registration.confirmation_text_many', ['count' => $count, 'event' => $event->name])
                : __('registration.confirmation_text', ['name' => $registration->displayName(), 'class' => $registration->raceClass->name, 'event' => $event->name]),
            'date' => Format::date($event->date),
            'receipt' => Receipt::for($registration),
            'url' => $registration->confirmationUrl(),
            'terms' => route('public.terms', $event->organizer->slug),
        ]);
    }
}
