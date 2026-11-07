<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Race;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** schema.org for an upplaga's page (#117), built from the same data the page shows so the two cannot drift apart. */
final class SchemaOrg
{
    /** @param  Collection<int, array{race: Race, price: ?int, soon: bool}>  $races */
    public static function event(Event $event, Collection $races): array
    {
        $organizer = $event->organizer;
        $first = $event->raceClasses()->orderBy('start_at')->value('start_at');
        $start = $first ? CarbonImmutable::parse($first, 'UTC')->setTimezone($event->timezone)->toIso8601String() : $event->date->toDateString();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => $event->name,
            'url' => $event->publicUrl(),
            'startDate' => $start,
            'eventStatus' => 'https://schema.org/'.match ($event->status()) {
                'cancelled' => 'EventCancelled',
                'moved' => 'EventRescheduled',
                default => 'EventScheduled',
            },
            'previousStartDate' => $event->status() === 'moved' ? $event->moved_from->toDateString() : null,
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => $event->city ? ['@type' => 'Place', 'name' => $event->city, 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $event->city, 'addressCountry' => $organizer->country]] : null,
            'organizer' => ['@type' => 'SportsOrganization', 'name' => $organizer->name, 'url' => route('public.organizer', $organizer->slug)],
            'offers' => $races->filter(fn ($row) => $row['price'] !== null)->map(fn ($row) => [
                '@type' => 'Offer',
                'name' => $row['race']->name,
                'price' => number_format($row['price'] / 100, 2, '.', ''),
                'priceCurrency' => $organizer->currency,
                'availability' => 'https://schema.org/'.($row['soon'] ? 'PreOrder' : 'InStock'),
                'url' => $event->publicUrl(),
            ])->values()->all() ?: null,
        ], fn ($value) => $value !== null);
    }
}
