<?php

namespace App\Support;

use App\Models\Registration;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * What a receipt says (#132), the same in the mail and on the page: the organizer as seller with its
 * organisation number, the contact as buyer, a line per participant, VAT or exempt, number and date.
 */
final class Receipt
{
    public static function for(Registration $registration): array
    {
        $registration->loadMissing('raceClass.event.organizer');
        $event = $registration->raceClass->event;
        $organizer = $event->organizer;
        $currency = $organizer->currency;
        $order = $registration->order();
        $first = $order->first();
        $total = (int) $order->sum('price');
        $vat = $organizer->vat_percent ? (int) round($total * $organizer->vat_percent / (100 + $organizer->vat_percent)) : null;

        return [
            'number' => $first->receipt_number,
            'paidAt' => $first->paid_at ? Format::shortDate($first->paid_at->setTimezone($event->timezone)) : null,
            'refunded' => $order->every(fn ($entry) => $entry->refunded_at !== null),
            'seller' => array_filter([$organizer->name, $organizer->org_number ? __('receipt.org_number').' '.$organizer->org_number : null, $organizer->address]),
            'buyer' => array_filter([trim("{$first->first_name} {$first->last_name}"), $first->email]),
            'lines' => $order->map(fn (Registration $entry) => [
                'name' => $entry->displayName(),
                'what' => trim("{$event->name}, {$entry->raceClass->race?->name} – {$entry->raceClass->name}", ' –,'),
                'date' => Format::shortDate($event->date).', '.Format::time($entry->raceClass->start_at->setTimezone($event->timezone)),
                'price' => Money::format((int) $entry->price, $currency),
            ])->all(),
            'total' => Money::format($total, $currency),
            'vat' => $vat === null ? __('receipt.vat_exempt') : __('receipt.vat', ['percent' => $organizer->vat_percent, 'amount' => Money::format($vat, $currency)]),
            'method' => $first->payment_method ? Str::lcfirst(__("registrations.method.{$first->payment_method}")) : null,
            'via' => __('public.powered_by'),
            'url' => URL::signedRoute('public.receipt', ['organizer' => $organizer->slug, 'event' => $event->slug, 'registration' => $first]),
        ];
    }
}
