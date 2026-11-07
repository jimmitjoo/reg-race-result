<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A company's or club's way into an upplaga (#85): its own price per race, a place cap, a shared code
 * or single-use codes, and possibly an invoice instead of a card. The code sets the price, never the
 * payment method, so it works the same for every way of paying.
 */
class Campaign extends Model
{
    /** An unpaid registration holds its campaign place and its code this long, like a Checkout session. */
    public const HOLD_MINUTES = 30;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['single_use' => 'boolean', 'invoice' => 'boolean', 'prices' => 'array', 'invoice_paid_at' => 'immutable_datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function codes(): HasMany
    {
        return $this->hasMany(CampaignCode::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public static function normalize(?string $code): string
    {
        return Str::upper(preg_replace('/\s+/', '', (string) $code));
    }

    /**
     * What a code gives a class: the price and the campaign (and the single-use code), or why it does not.
     *
     * @return array{price: ?int, campaign: ?Campaign, code: ?CampaignCode, error: ?string}
     */
    public static function resolve(Event $event, RaceClass $class, ?string $code, int $alsoInOrder = 0): array
    {
        $ordinary = $class->race?->priceAt(now());
        $code = self::normalize($code);
        $result = fn (?string $error, ?Campaign $campaign = null, ?CampaignCode $row = null) => [
            'price' => $error || ! $campaign || $ordinary === null ? $ordinary : ($campaign->prices[$class->race_id] ?? $ordinary),
            'campaign' => $error ? null : $campaign,
            'code' => $error ? null : $row,
            'error' => $error,
        ];

        if ($code === '') {
            return $result(null);
        }

        $campaign = $event->campaigns()->where('code', $code)->first();
        $row = $campaign ? null : CampaignCode::where('code', $code)->whereHas('campaign', fn ($q) => $q->where('event_id', $event->id))->with('campaign')->first();
        $campaign ??= $row?->campaign;

        $today = now($event->timezone)->toDateString();

        return match (true) {
            ! $campaign => $result('code_unknown'),
            $row && $row->valid_until && $row->valid_until->toDateString() < $today => $result('code_expired'),
            $row && ! CampaignCode::whereKey($row->id)->free()->exists() => $result('code_used'),
            $row && $row->applies_to === 'individual' && $class->team_size => $result('code_individual'),
            $row && $row->applies_to === 'team' && ! $class->team_size => $result('code_team'),
            $campaign->cap !== null && $campaign->taken() + $alsoInOrder >= $campaign->cap => $result('campaign_full'),
            default => $result(null, $campaign, $row),
        };
    }

    /**
     * Places taken: participants (paid or invoiced) and registrations still on their way to paying.
     * ponytail: two orders at the same moment can both take the last place; a lock per campaign if that ever matters.
     */
    public function taken(): int
    {
        return $this->registrations()->whereNull('refunded_at')
            ->where(fn (Builder $q) => $q->whereNotNull('paid_at')->orWhere('payment_method', 'invoice')
                ->orWhere('created_at', '>', now()->subMinutes(self::HOLD_MINUTES)))
            ->count();
    }

    /** A campaign as it was, for next year's upplaga (#84): prices follow the races by name, codes stay behind. */
    public function copyTo(Event $event, array $raceIds): void
    {
        $event->campaigns()->create($this->only(['name', 'code', 'single_use', 'cap', 'invoice', 'invoice_name', 'invoice_org_number', 'invoice_reference', 'invoice_email']) + [
            'prices' => collect($this->prices ?? [])->mapWithKeys(fn ($amount, $raceId) => isset($raceIds[$raceId]) ? [$raceIds[$raceId] => $amount] : [])->all() ?: null,
        ]);
    }
}
