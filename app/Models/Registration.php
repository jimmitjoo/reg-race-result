<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Mail\RegistrationReceipt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class Registration extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['birth_date' => 'immutable_date', 'paid_at' => 'immutable_datetime', 'refunded_at' => 'immutable_datetime', 'manual_finish_at' => UtcDateTime::class.':ms', 'terms_accepted_at' => 'immutable_datetime', 'hidden_at' => 'immutable_datetime', 'team_members' => 'array'];
    }

    /** The team's name for a team (#31), else the runner's. */
    public function displayName(): string
    {
        return $this->team_name ?? trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * The name on public lists. A runner who asked not to be shown loses it (GDPR, #13); a team keeps its name,
     * which is not the contact's personal data.
     */
    public function publicName(): string
    {
        return $this->team_name ?? ($this->hidden_at ? __('privacy.anonymous') : $this->displayName());
    }

    /** Webhook, reconciliation and the confirmation page can race; only the one that marks it paid sends the receipt. */
    public function markPaid(string $method, ?string $paymentIntent = null): void
    {
        $marked = static::whereKey($this->id)->whereNull('paid_at')
            ->update(['paid_at' => now(), 'payment_method' => $method, 'stripe_payment_intent_id' => $paymentIntent ?? $this->stripe_payment_intent_id]);
        $this->refresh();

        if ($marked && $this->email) {
            Mail::to($this->email)->send(new RegistrationReceipt($this));
        }
    }

    public function confirmationUrl(): string
    {
        $event = $this->raceClass->event;

        return URL::signedRoute('public.registration', ['organizer' => $event->organizer->slug, 'event' => $event->slug, 'registration' => $this]);
    }

    public function clubRecord(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function raceClass(): BelongsTo
    {
        return $this->belongsTo(RaceClass::class);
    }
}
