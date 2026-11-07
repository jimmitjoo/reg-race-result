<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Mail\RegistrationReceipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
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

    /**
     * Marks the whole order paid (#109), with the payment intent on every registration so that a refund finds them all.
     * Webhook, reconciliation and the confirmation page can race; only the one that marks it paid sends the one receipt.
     */
    public function markPaid(string $method, ?string $paymentIntent = null, bool $notify = true): void
    {
        // The order's receipt number (#132) comes from the organizer's counter in the same transaction, so numbers have no gaps.
        $marked = DB::transaction(function () use ($method, $paymentIntent) {
            $marked = $this->orderQuery()->whereNull('paid_at')
                ->update(['paid_at' => now(), 'payment_method' => $method, 'stripe_payment_intent_id' => $paymentIntent ?? $this->stripe_payment_intent_id]);

            if ($marked) {
                $organizer = Organizer::whereKey($this->raceClass->event->organizer_id)->lockForUpdate()->firstOrFail();
                $organizer->increment('receipt_counter');
                $this->orderQuery()->update(['receipt_number' => $organizer->receipt_counter]);
                // The order's single-use codes (#85) are used now, whatever the way of paying.
                CampaignCode::whereIn('registration_id', $this->orderQuery()->select('id'))->whereNull('used_at')->update(['used_at' => now()]);
            }

            return $marked;
        });
        $this->refresh();

        // An invoice campaign marked paid by the club (#85) sends nothing: the invoice was the paper.
        if ($marked && $notify && $this->email) {
            Mail::to($this->email)->send(new RegistrationReceipt($this));
        }
    }

    /** The registrations made and paid together with this one, in the order they were filled in. */
    public function order(): Collection
    {
        return $this->orderQuery()->with('raceClass.race')->orderBy('id')->get();
    }

    private function orderQuery(): Builder
    {
        return $this->order_ref ? static::where('order_ref', $this->order_ref) : static::whereKey($this->id);
    }

    public function confirmationUrl(): string
    {
        $event = $this->raceClass->event;

        return URL::signedRoute('public.registration', ['organizer' => $event->organizer->slug, 'event' => $event->slug, 'registration' => $this]);
    }

    /**
     * The ones who take part: paid, or registered through an invoice campaign (#85) that the club bills itself,
     * and not refunded. Other unpaid registrations do not count (#64).
     *
     * @template T
     *
     * @param  T  $query
     * @return T
     */
    public static function participating($query)
    {
        return $query->where(fn ($q) => $q->whereNotNull('registrations.paid_at')->orWhere('registrations.payment_method', 'invoice'))
            ->whereNull('registrations.refunded_at');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
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
