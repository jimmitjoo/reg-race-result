<?php

namespace App\Mail;

use App\Models\Form;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** What someone sent with a form, to the club; a reply goes to the sender when the form asked for an email. */
class FormAnswered extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Form $form, public array $answers, public ?string $replyAddress = null)
    {
        $this->locale($form->organizer->locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('forms.mail_subject', ['form' => $this->form->name]),
            replyTo: $this->replyAddress ? [$this->replyAddress] : [],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.form-answered', with: [
            'form' => $this->form,
            'answers' => $this->answers,
            'stored' => $this->form->action !== 'email',
            'url' => route('forms.edit', $this->form),
        ]);
    }
}
