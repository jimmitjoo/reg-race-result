<?php

namespace App\Http\Controllers;

use App\Exports\RegistrationsCsv;
use App\Exports\ResultsPdf;
use App\Exports\SfifExport;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExportController extends Controller
{
    public function sfif(Event $event): Response
    {
        return response(SfifExport::csv($event), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.SfifExport::filename($event).'"',
        ]);
    }

    public function registrations(Request $request, Event $event): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(RegistrationsCsv::STATUSES)],
            'race' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return response(RegistrationsCsv::csv($event, $filters['status'] ?? 'paid', $filters['race'] ?? null, trim($filters['search'] ?? '')), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.RegistrationsCsv::filename($event).'"',
        ]);
    }

    /** A campaign's single-use codes (#85), as a list for the company or club to hand out. */
    public function campaignCodes(Event $event, int $campaign): Response
    {
        $campaign = $event->campaigns()->findOrFail($campaign);
        $rows = $campaign->codes()->orderBy('id')->get()->map(fn ($code) => [
            $code->code, $code->valid_until?->toDateString(), __("campaigns.applies.{$code->applies_to}"),
            $code->used_at ? __('campaigns.used') : ($code->registration_id && $code->reserved_at > now()->subMinutes(Campaign::HOLD_MINUTES) ? __('campaigns.reserved') : ''),
        ]);

        return $this->csv([[__('campaigns.code'), __('campaigns.valid_until'), __('campaigns.applies_to'), __('campaigns.status')], ...$rows], str("{$event->name} {$campaign->name} koder")->slug().'.csv');
    }

    /** What the club bills a campaign's company or school (#85), printed or as CSV for its own invoicing system. */
    public function invoiceBasis(Request $request, Event $event, int $campaign): Response|View
    {
        $campaign = $event->campaigns()->where('invoice', true)->findOrFail($campaign);
        $registrations = Registration::participating($campaign->registrations())->with('raceClass.race')->orderBy('last_name')->orderBy('first_name')->get();
        $currency = $event->organizer->currency;

        if ($request->query('format') === 'csv') {
            $money = fn (int $amount) => number_format($amount / 100, 2, ',', '');

            return $this->csv([
                [__('campaigns.invoice_name'), $campaign->invoice_name],
                [__('receipt.org_number_label'), $campaign->invoice_org_number],
                [__('campaigns.invoice_reference'), $campaign->invoice_reference],
                [__('campaigns.invoice_email'), $campaign->invoice_email],
                [],
                [__('receipt.participant'), __('registration.class'), __('campaigns.code'), __('receipt.price')],
                ...$registrations->map(fn ($r) => [$r->displayName(), trim("{$r->raceClass->race?->name} – {$r->raceClass->name}", ' –'), $r->campaign_code, $money((int) $r->price)]),
                [__('registration.total'), '', '', $money((int) $registrations->sum('price'))],
            ], str("{$event->name} {$campaign->name} fakturaunderlag")->slug().'.csv');
        }

        return view('pdf.invoice-basis', ['event' => $event, 'campaign' => $campaign, 'registrations' => $registrations, 'currency' => $currency]);
    }

    /** UTF-8 with BOM, semicolons and CRLF, so that Swedish Excel opens it right. */
    private function csv(iterable $rows, string $filename): Response
    {
        $lines = collect($rows)->map(fn ($cells) => implode(';', array_map(fn ($cell) => str_replace([';', "\r", "\n"], ' ', (string) $cell), $cells)));

        return response("\u{FEFF}".$lines->implode("\r\n")."\r\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function pdf(Event $event): Response
    {
        return ResultsPdf::download($event);
    }
}
