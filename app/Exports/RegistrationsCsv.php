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
        return $event->allRegistrations()->with('raceClass')
            ->when($status === 'paid', fn ($q) => $q->whereNotNull('registrations.paid_at')->whereNull('registrations.refunded_at'))
            ->when($status === 'unpaid', fn ($q) => $q->whereNull('registrations.paid_at'))
            ->when($raceId, fn ($q) => $q->where('race_classes.race_id', $raceId))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('bib', $search)))
            ->orderBy('bib')->orderBy('last_name')->orderBy('registrations.id');
    }

    /** @return list<string> */
    public static function columns(): array
    {
        return [
            __('registration.class'), __('results.bib'), __('registration.first_name'), __('registration.last_name'),
            __('registration.birth_date'), __('registration.gender'), __('registration.club'), __('registration.email'),
            __('registration.phone'), __('registrations.price'), __('registrations.payment_method'), __('registrations.paid_at'),
            __('registrations.created_at'),
        ];
    }

    /** @return list<?string> */
    public static function row(Registration $r, string $timezone): array
    {
        return [
            $r->raceClass->name, $r->bib === null ? null : (string) $r->bib, $r->first_name, $r->last_name,
            $r->birth_date?->toDateString(), $r->gender, $r->club, $r->email,
            $r->phone, $r->price === null ? null : number_format($r->price / 100, 2, ',', ''), $r->payment_method ? __("registrations.method.{$r->payment_method}") : null,
            $r->refunded_at ? __('registrations.refunded') : $r->paid_at?->setTimezone($timezone)->format('Y-m-d H:i'), $r->created_at->setTimezone($timezone)->format('Y-m-d H:i'),
        ];
    }

    /** UTF-8 with BOM, semicolons and CRLF, like SfifExport, so that Swedish Excel opens it right. */
    public static function csv(Event $event, string $status = 'paid', ?int $raceId = null, string $search = ''): string
    {
        $line = fn (array $cells) => implode(';', array_map(fn ($cell) => str_replace([';', "\r", "\n"], ' ', (string) $cell), $cells));
        $lines = [$line(self::columns())];
        foreach (self::query($event, $status, $raceId, $search)->get() as $registration) {
            $lines[] = $line(self::row($registration, $event->timezone));
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n";
    }

    public static function filename(Event $event): string
    {
        return __('registrations.filename', ['event' => $event->name]);
    }
}
