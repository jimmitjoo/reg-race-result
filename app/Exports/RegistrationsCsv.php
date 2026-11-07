<?php

namespace App\Exports;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/** The participant list of an event, as shown on the Anmälningar page and downloaded for Excel or a timer. */
final class RegistrationsCsv
{
    public const STATUSES = ['paid', 'unpaid', 'all'];

    public static function query(Event $event, string $status = 'paid', ?int $raceId = null, string $search = ''): HasManyThrough
    {
        return $event->allRegistrations()->with(['raceClass.race', 'raceClass.event'])
            ->when($status === 'paid', fn ($q) => Registration::participating($q))
            ->when($status === 'unpaid', fn ($q) => $q->whereNull('registrations.paid_at'))
            ->when($raceId, fn ($q) => $q->where('race_classes.race_id', $raceId))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('team_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('bib', $search)))
            ->orderBy('bib')->orderBy('last_name')->orderBy('registrations.id');
    }

    /** Only an event with a race that has age groups gets the column (#92). */
    public static function hasAgeGroups(Event $event): bool
    {
        return $event->races()->where('age_groups', true)->exists();
    }

    /** Only an event with a team class gets the team columns (#31). */
    public static function hasTeams(Event $event): bool
    {
        return $event->raceClasses()->whereNotNull('team_size')->exists();
    }

    /** @return list<string> */
    public static function columns(bool $ageGroups = false, bool $teams = false): array
    {
        return [
            __('registration.class'), __('results.bib'), ...($teams ? [__('registration.team_name'), __('registration.team_members')] : []), __('registration.first_name'), __('registration.last_name'),
            __('registration.birth_date'), __('registration.gender'), ...($ageGroups ? [__('results.age_group')] : []), __('registration.club'), __('registration.email'),
            __('registration.phone'), __('registrations.price'), __('registrations.campaign'), __('registrations.payment_method'), __('registrations.paid_at'),
            __('registrations.created_at'),
        ];
    }

    /** @return list<?string> */
    public static function row(Registration $r, string $timezone, bool $ageGroups = false, bool $teams = false): array
    {
        return [
            $r->raceClass->name, $r->bib === null ? null : (string) $r->bib, ...($teams ? [$r->team_name, implode(', ', $r->team_members ?? [])] : []), $r->first_name, $r->last_name,
            $r->birth_date?->toDateString(), $r->gender, ...($ageGroups ? [$r->raceClass->ageGroupOf($r)] : []), $r->club, $r->email,
            $r->phone, $r->price === null ? null : number_format($r->price / 100, 2, ',', ''), $r->campaign_code, $r->payment_method ? __("registrations.method.{$r->payment_method}") : null,
            $r->refunded_at ? __('registrations.refunded') : $r->paid_at?->setTimezone($timezone)->format('Y-m-d H:i'), $r->created_at->setTimezone($timezone)->format('Y-m-d H:i'),
        ];
    }

    /** UTF-8 with BOM, semicolons and CRLF, like SfifExport, so that Swedish Excel opens it right. */
    public static function csv(Event $event, string $status = 'paid', ?int $raceId = null, string $search = ''): string
    {
        $line = fn (array $cells) => implode(';', array_map(fn ($cell) => str_replace([';', "\r", "\n"], ' ', (string) $cell), $cells));
        [$ageGroups, $teams] = [self::hasAgeGroups($event), self::hasTeams($event)];
        $lines = [$line(self::columns($ageGroups, $teams))];
        foreach (self::query($event, $status, $raceId, $search)->get() as $registration) {
            $lines[] = $line(self::row($registration, $event->timezone, $ageGroups, $teams));
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n";
    }

    public static function filename(Event $event): string
    {
        return __('registrations.filename', ['event' => $event->name]);
    }
}
