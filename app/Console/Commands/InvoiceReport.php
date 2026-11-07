<?php

namespace App\Console\Commands;

use App\Models\Organizer;
use App\Models\Registration;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Invoiced registrations bypass Stripe, so the platform fee is settled at the end of each month (#85):
 * per organizer, what was invoiced, what was marked paid, and the fee on the paid part.
 */
class InvoiceReport extends Command
{
    protected $signature = 'invoices:report {month? : YYYY-MM, last month by default}';

    protected $description = 'Invoiced, paid and platform fee per organizer for a month';

    public function handle(): int
    {
        $month = $this->argument('month') ? CarbonImmutable::parse($this->argument('month').'-01') : now()->subMonthNoOverflow()->startOfMonth()->toImmutable();
        $range = [$month->startOfMonth(), $month->endOfMonth()];

        $rows = Organizer::orderBy('name')->get()->map(function (Organizer $organizer) use ($range) {
            $invoiced = fn () => Registration::where('payment_method', 'invoice')->whereNull('refunded_at')
                ->whereHas('raceClass.event', fn ($q) => $q->where('organizer_id', $organizer->id));
            $created = $invoiced()->whereBetween('created_at', $range)->pluck('price');
            $paid = $invoiced()->whereBetween('paid_at', $range)->pluck('price');

            return $created->isEmpty() && $paid->isEmpty() ? null : [
                $organizer->name,
                Money::format((int) $created->sum(), $organizer->currency),
                Money::format((int) $paid->sum(), $organizer->currency),
                Money::format($paid->sum(fn ($price) => $organizer->platformFee((int) $price)), $organizer->currency),
            ];
        })->filter()->values();

        $this->info($month->format('Y-m'));
        $this->table(['Arrangör', 'Fakturerat', 'Markerat betalt', 'Plattformsavgift'], $rows);

        return self::SUCCESS;
    }
}
