<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\Series;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Brings arrangemang, races, classes, prices and paid registrations over from Högby IF's old system (#14),
 * from the JSON that database/import/flexit.sql exports. Runs again without making doubles.
 * Registrations are set paid directly: markPaid() would mail a receipt to every runner.
 */
class ImportFlexit extends Command
{
    protected $signature = 'import:flexit {organizer : The organizer slug} {file : JSON from database/import/flexit.sql} {--dry-run : Show the report and roll back}';

    protected $description = 'Import arrangemang and paid registrations from the old registration system (Flexit)';

    // The old system has no start times; the club sets them afterwards.
    private const PLACEHOLDER_START = '12:00';

    private array $counts = [];

    private array $needsStartTime = [];

    private array $split = [];

    private array $unpaid = [];

    private array $skipped = [];

    private array $twice = [];

    private array $moved = [];

    private array $notRun = [];

    // Registrations made by this run, to tell a runner who paid twice from one imported before.
    private array $created = [];

    private int $noBirthDate = 0;

    public function handle(): int
    {
        $organizer = Organizer::where('slug', $this->argument('organizer'))->firstOrFail();
        $data = json_decode(file_get_contents($this->argument('file')), true, flags: JSON_THROW_ON_ERROR);

        $classes = collect($data['classes'] ?? [])->groupBy('competition');
        $prices = collect($data['prices'] ?? [])->groupBy('class');
        $registrations = collect($data['registrations'] ?? [])->groupBy('class');

        DB::beginTransaction();

        $competitions = $this->byOrderDate(collect($data['competitions'] ?? []), $classes, $prices, $registrations);

        foreach ($this->onePerRaceAndDay($competitions, $registrations, $classes)->groupBy(fn ($c) => Series::nameFrom($c['arrangemang'])."\0".$c['date']) as $competitions) {
            // An upplaga that has been run without anybody was not run (Ölands Kyrkstafett 2025 has only an empty competition).
            $runners = $competitions->sum(fn ($c) => collect($classes->get($c['id'], []))->sum(fn ($class) => count($registrations->get($class['id'], []))));
            if (! $runners && $competitions->first()['date'] < now($organizer->timezone)->toDateString()) {
                $this->notRun[] = Series::nameFrom($competitions->first()['arrangemang'])." {$competitions->first()['date']}";

                continue;
            }

            $event = $this->event($organizer, $competitions->first());

            foreach ($competitions as $competition) {
                $raceClasses = collect($classes->get($competition['id'], []))->sortBy('sort');

                foreach ($this->byPrice($raceClasses, $prices, $registrations, trim($competition['race'])) as $name => ['classes' => $group, 'steps' => $steps]) {
                    $race = $this->firstOrCreate($event->races(), ['name' => $name], [], 'lopp');
                    $this->prices($race, $steps);

                    foreach ($group as $class) {
                        $raceClass = $this->raceClass($event, $race, $class);
                        foreach ($registrations->get($class['id'], []) as $registration) {
                            $this->registration($organizer, $raceClass, $registration);
                        }
                    }
                }
            }
        }

        $this->report();

        if ($this->option('dry-run')) {
            DB::rollBack();
            $this->warn('Provkörning: ingenting sparades.');
        } else {
            DB::commit();
        }

        return self::SUCCESS;
    }

    /**
     * The old system kept registrations under wrong dates both ways: it moved last year's competition a year on
     * and left an empty one at last year's date ("Ekerumsloppet fel" on 2026-10-03 holds 2025's runners), kept last
     * year's runners in this year's (Lilla Böda Beachrun), and gave this year's an old date (Hagbloms Nattloppet
     * 2025's 825 runners are on 2023-11-07). So a registration goes to the arrangemang's first race day on or after
     * the day it was ordered: in a copy of its competition at that date, with the price dates moved as many days,
     * where it meets that day's races and classes. A competition left with nobody, at a date that is no race day, goes.
     */
    private function byOrderDate(Collection $competitions, Collection $classes, Collection $prices, Collection $registrations): Collection
    {
        $out = collect();
        foreach ($competitions as $competition) {
            $days = self::raceDays($competition['dates'] ?? []);
            $own = collect($classes->get($competition['id'], []));
            $target = function (array $r) use ($days, $competition) {
                $ordered = substr($r['ordered_at'] ?? '', 0, 10);
                $day = collect($days)->first(fn ($d) => $d >= $ordered && $d <= CarbonImmutable::parse($ordered)->addDays(400)->toDateString());

                return $day ?? $competition['date'];
            };
            $all = $own->mapWithKeys(fn ($class) => [$class['id'] => collect($registrations->get($class['id'], []))->groupBy($target)]);
            $dates = $all->flatMap(fn ($byDate) => $byDate->keys())->unique()->reject(fn ($d) => $d === $competition['date']);

            foreach ($dates as $date) {
                $copy = "{$competition['id']}@{$date}";
                $shift = (int) CarbonImmutable::parse($competition['date'])->diffInDays(CarbonImmutable::parse($date), false);
                $classes[$copy] = $own->map(fn ($class) => ['id' => "{$class['id']}@{$date}", 'competition' => $copy] + $class);
                foreach ($own as $class) {
                    $registrations["{$class['id']}@{$date}"] = $all[$class['id']]->get($date, collect())->values();
                    $prices["{$class['id']}@{$date}"] = collect($prices->get($class['id'], []))
                        ->map(fn ($step) => ['until' => CarbonImmutable::parse($step['until'])->addDays($shift)->toDateString()] + $step);
                }
                $out[] = ['id' => $copy, 'date' => $date, 'moved' => true] + $competition;
                $moved = $all->sum(fn ($byDate) => $byDate->get($date, collect())->count());
                $this->moved[] = ($competition['name'] ?? $competition['race'])." ({$competition['date']}): {$moved} anmälningar till upplagan {$date}";
            }

            $left = 0;
            foreach ($own as $class) {
                $registrations[$class['id']] = $all[$class['id']]->get($competition['date'], collect())->values();
                $left += $registrations[$class['id']]->count();
            }
            $hadRunners = $all->sum(fn ($byDate) => $byDate->flatten(1)->count()) > 0;
            if ($left || (! $hadRunners && (! $days || in_array($competition['date'], $days, true)))) {
                $out[] = $competition;
            }
        }

        return $out;
    }

    /**
     * The dates an arrangemang is really run. Those more than 45 days from the time of year it usually is are wrong
     * ("Hagbloms Nattloppet" on 2023-11-07); of dates within 30 days of each other ("Böda Beachrun kopia" the day
     * before the race) the one with an active competition is kept, else the one whose runners ordered in the year before it.
     *
     * @param  list<array{d: string, active?: int, n?: int, ok?: int}>  $dates
     * @return list<string>
     */
    private static function raceDays(array $dates): array
    {
        $dates = collect($dates)->keyBy('d')->sortKeys();
        if ($dates->isEmpty()) {
            return [];
        }
        $dayOfYear = fn (string $d) => CarbonImmutable::parse($d)->dayOfYear;
        $usual = $dates->keys()->map($dayOfYear)->median();
        $apart = fn (string $d) => min(abs($dayOfYear($d) - $usual), 365 - abs($dayOfYear($d) - $usual));

        $days = [];
        foreach ($dates->filter(fn ($date, $d) => $apart($d) <= 45) as $d => $date) {
            $last = array_key_last($days);
            if ($last !== null && CarbonImmutable::parse($days[$last]['d'])->diffInDays(CarbonImmutable::parse($d)) <= 30) {
                $better = [$date['active'] ?? 0, $date['ok'] ?? 0, $date['n'] ?? 0] > [$days[$last]['active'] ?? 0, $days[$last]['ok'] ?? 0, $days[$last]['n'] ?? 0];
                $days[$last] = $better ? $date : $days[$last];
            } else {
                $days[] = $date;
            }
        }

        return array_column($days, 'd');
    }

    /**
     * The same race twice on one day with runners in both is a mistake in the old system: the active one is kept,
     * else the newest, and the others are listed, never merged in. One without runners (the empty competition
     * last year's moved into) and the runners moved back go along: their classes meet the other's by name.
     */
    private function onePerRaceAndDay(Collection $competitions, Collection $registrations, Collection $classes): Collection
    {
        $runners = fn (array $c) => collect($classes->get($c['id'], []))->sum(fn ($class) => count($registrations->get($class['id'], [])));

        return $competitions->groupBy(fn ($c) => Series::nameFrom($c['arrangemang'])."\0".trim($c['race'])."\0{$c['date']}")
            ->flatMap(function (Collection $same) use ($runners) {
                [$empty, $full] = $same->partition(fn ($c) => ($c['moved'] ?? false) || $runners($c) === 0);
                $kept = $full->sortByDesc(fn ($c) => [$c['active'] ?? true, $c['id']])->first();
                foreach ($full->reject(fn ($c) => $c['id'] === $kept['id']) as $c) {
                    $this->skipped[] = ($c['name'] ?? $c['race'])." ({$c['date']}, {$runners($c)} anmälningar, id {$c['id']})";
                }

                return $kept ? $empty->push($kept) : $empty;
            })->values();
    }

    private function event(Organizer $organizer, array $competition): Event
    {
        $series = $this->firstOrCreate($organizer->series(), ['name' => Series::nameFrom($competition['arrangemang'])], [], 'arrangemang');
        $event = $series->events()->whereDate('date', $competition['date'])->first();
        $this->count('upplagor', $event ? 'fanns' : 'nya');

        return $event ?? $series->addEdition($competition['date']);
    }

    /**
     * One price per race here, one per class in the old system. Classes with a price list of their own
     * (children at 150 kr beside 6,2 km at 250) become a race of their own. The race keeps its name for
     * the classes most runners chose; the others are named by their distances, or by what their names share.
     *
     * @return Collection<string, array{classes: Collection, steps: list<array{string, int}>}>
     */
    private function byPrice(Collection $classes, Collection $prices, Collection $registrations, string $race): Collection
    {
        $curve = fn (array $class) => $prices->get($class['id'], collect())->sortBy('until')->map(fn ($s) => [$s['until'], $s['amount']])->values()->all();
        $groups = $classes->groupBy(fn ($class) => json_encode($curve($class)));
        $runners = fn (Collection $group) => $group->sum(fn ($class) => count($registrations->get($class['id'], [])));
        $main = $groups->sortByDesc($runners)->keys()->first();

        // Classes without a price go with the others rather than make a race nobody can register for.
        if ($groups->count() > 1 && $groups->has('[]')) {
            $unpriced = $groups->pull('[]');
            $main = $main === '[]' ? $groups->sortByDesc($runners)->keys()->first() : $main;
            $groups[$main] = $groups[$main]->concat($unpriced)->sortBy('sort');
        }

        $names = fn (Collection $group) => $group->map(fn ($class) => self::clean($class['name']));
        $out = collect();
        foreach ($groups->sortByDesc(fn ($group, $key) => $key === $main) as $key => $group) {
            $name = $key === $main ? $race : "{$race} ".self::suffix($names($group), $names($groups[$main]));
            $name = $out->has($name) ? "{$name} ".($out->count() + 1) : $name;
            if ($key !== $main) {
                $this->split[] = "{$name}: ".$names($group)->implode(', ');
            }
            $out[$name] = ['classes' => $group, 'steps' => json_decode($key, true)];
        }

        return $out;
    }

    /**
     * "2,9 km, 1,5 km och 700 m" for the distances the main race does not have. At the same distance, what is
     * left of the class name without it: all of it for one class ("13 år o yngre"), else the word they start with ("DM").
     */
    private static function suffix(Collection $names, Collection $mainNames): string
    {
        $distances = fn (Collection $names) => $names->map(fn ($name) => self::distanceFrom($name))->filter()->unique()->sortDesc()->values()->all();
        $own = $distances($names);
        if ($own && $own !== $distances($mainNames)) {
            return $names->filter(fn ($name) => self::distanceFrom($name))
                ->mapWithKeys(fn ($name) => [self::distanceFrom($name) => self::distanceLabel($name)])
                ->sortKeysDesc()->join(', ', ' och ');
        }
        $rest = $names->map(fn ($name) => self::clean(preg_replace('/\d+(?:[,.]\d+)?\s?(?:k?m|miles?)\b/iu', '', $name)))->filter()->values();
        if ($rest->count() === 1 && $names->count() === 1) {
            return $rest->first();
        }
        $first = $rest->map(fn ($name) => strtok($name, ' '))->unique();

        return $first->count() === 1 ? $first->first() : $names->first();
    }

    /** @param  list<array{string, int}>  $steps */
    private function prices(Race $race, array $steps): void
    {
        if (! $steps || $race->priceSteps()->exists()) {
            return;
        }

        foreach ($steps as [$until, $amount]) {
            $race->priceSteps()->create(['until' => $until, 'amount' => $amount]);
        }
        $this->count('prislistor', 'nya');
    }

    private static function clean(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim($name));
    }

    private function raceClass(Event $event, Race $race, array $class): RaceClass
    {
        $name = self::clean($class['name']);
        $existing = $event->raceClasses()->where('race_id', $race->id)->where('name', $name)->first();
        $this->count('klasser', $existing ? 'fanns' : 'nya');

        if ($existing) {
            return $existing;
        }

        $this->needsStartTime[] = "{$race->name}: {$name}";

        return $event->raceClasses()->create([
            'race_id' => $race->id,
            'name' => $name,
            'gender' => self::genderFrom($name),
            'distance_meters' => self::distanceFrom($name),
            'team_size' => $class['team_size'] ?? null,
            'timed' => true,
            'start_at' => CarbonImmutable::parse("{$event->date->toDateString()} ".self::PLACEHOLDER_START, $event->timezone),
        ]);
    }

    private function registration(Organizer $organizer, RaceClass $class, array $row): void
    {
        $name = trim($row['first_name']).' '.trim($row['last_name']);

        // Gratis order (GO) has no payment date. Unpaid card orders were abandoned; bankgiro may still come.
        if (! $row['paid_at'] && $row['method'] !== 'GO') {
            $this->unpaid[] = "{$name} ({$class->name}, ".($row['method'] ?? '–').')';

            return;
        }

        $birthDate = self::birthDate($row['birth']);
        $this->noBirthDate += $birthDate === null ? 1 : 0;

        // One contact often registers several teams in a class, so a team is told apart by its name.
        $team = $class->team_size ? (trim($row['team'] ?? '') ?: null) : null;
        $attributes = ['race_class_id' => $class->id, 'first_name' => trim($row['first_name']), 'last_name' => trim($row['last_name']), 'team_name' => $team, 'birth_date' => $birthDate];
        // The date is stored with a time, so it is compared as a date.
        $same = Registration::where(collect($attributes)->except(['birth_date', 'team_name'])->all())
            ->when($team, fn ($q) => $q->where('team_name', $team), fn ($q) => $q->whereNull('team_name'))
            ->when($birthDate, fn ($q) => $q->whereDate('birth_date', $birthDate), fn ($q) => $q->whereNull('birth_date'));
        if ($existing = $same->first()) {
            if (in_array($existing->id, $this->created, true)) {
                $this->twice[] = ($team ? "{$team}, " : '')."{$name} ({$class->name})";
                $this->count('anmälningar', 'dubbla');
            } else {
                $this->count('anmälningar', 'fanns');
            }

            return;
        }

        $club = Club::match($row['club'] ?? '');
        $price = (int) ($row['price'] ?? 0);
        $registration = new Registration($attributes + [
            'gender' => self::gender($row['gender']),
            'club' => $club?->name ?? ($row['club'] ?: null),
            'club_id' => $club?->id,
            'email' => $row['email'] ?: null,
            'phone' => $row['phone'] ?: null,
            'price' => $price,
            'payment_method' => $price > 0 ? 'flexit' : 'free',
            'paid_at' => self::utc($row['paid_at'] ?? $row['ordered_at'], $organizer),
            'note' => $row['standard'] ? null : "Kampanj: {$row['campaign']}",
            'status' => 'registered',
            'team_members' => $team ? collect($row['members'] ?? [])->pluck('name')->map(fn ($n) => trim((string) $n))->filter()->values()->all() : null,
        ]);
        $registration->created_at = self::utc($row['ordered_at'], $organizer);
        $registration->save();
        $this->created[] = $registration->id;
        $this->count('anmälningar', 'nya');
    }

    private function firstOrCreate(HasMany $relation, array $match, array $values, string $label): Model
    {
        $model = $relation->where($match)->first();
        $this->count($label, $model ? 'fanns' : 'nya');

        return $model ?? $relation->create($match + $values);
    }

    private function count(string $label, string $kind): void
    {
        $this->counts[$label][$kind] = ($this->counts[$label][$kind] ?? 0) + 1;
    }

    private function report(): void
    {
        $this->table(['', 'nya', 'fanns', 'dubbla'], collect($this->counts)->map(fn ($c, $label) => [$label, $c['nya'] ?? 0, $c['fanns'] ?? 0, $c['dubbla'] ?? 0])->values());

        $this->list('Klasser utan starttid i gamla systemet, satta till '.self::PLACEHOLDER_START.'. Ändra dem i admin:', $this->needsStartTime);
        $this->list('Klasser med ett eget pris i gamla systemet, nu i ett eget lopp (byt namn i admin om du vill):', $this->split);
        $this->list('Anmälningar under fel datum i gamla systemet, flyttade till upplagan efter beställningsdagen:', $this->moved);
        $this->list('Upplagor som har gått utan några anmälningar i gamla systemet, inte skapade:', $this->notRun);
        $this->list('Samma lopp flera gånger samma dag i gamla systemet. Bara den aktiva är importerad; kontrollera de här och deras datum:', $this->skipped);
        $this->list('Betalda två gånger i samma klass (samma namn och födelsedatum). En är importerad; betala tillbaka den andra i gamla systemet:', $this->twice);
        $this->list('Obetalda anmälningar, inte importerade (kort som avbröts, bankgiro som kanske kommer):', $this->unpaid);
        if ($this->noBirthDate) {
            $this->warn("{$this->noBirthDate} anmälningar saknar ett giltigt födelsedatum.");
        }
    }

    private function list(string $heading, array $lines): void
    {
        if ($lines) {
            $this->newLine();
            $this->warn($heading);
            foreach ($lines as $line) {
                $this->line("  {$line}");
            }
        }
    }

    /** The old system wrote local time; Eloquent stores a datetime as it is formatted, so it goes in as UTC. */
    private static function utc(string $local, Organizer $organizer): CarbonImmutable
    {
        return CarbonImmutable::parse($local, $organizer->timezone)->utc();
    }

    /** YYYYMMDD; a samordningsnummer has 60 added to the day. */
    public static function birthDate(?string $digits): ?string
    {
        if (! $digits || ! preg_match('/^(\d{4})(\d{2})(\d{2})$/', $digits, $m)) {
            return null;
        }
        [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3] > 60 ? (int) $m[3] - 60 : (int) $m[3]];

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    private static function gender(?string $gender): ?string
    {
        return match ($gender) {
            'M' => 'M',
            'K', 'D', 'F' => 'K',
            default => null,
        };
    }

    /** "10 km Kvinnor", "M 10 km", "P 13 2 km" → M/K; "P/F7" and "Promenad" are open to both. */
    public static function genderFrom(string $name): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(P\d*\/F|M\/K)/u', $name) => null,
            (bool) preg_match('/\b(Män|Herrar|Pojkar)\b|^(M|P)\s?\d|^(M|P)\b|\s(M|P)$/u', $name) => 'M',
            (bool) preg_match('/\b(Kvinnor|Damer|Flickor)\b|^(K|F)\s?\d|^(K|F)\b|\s(K|F)$/u', $name) => 'K',
            default => null,
        };
    }

    /** How a race name says the distance: "50 miles" as written, else "1,4 km" or "800 m". */
    private static function distanceLabel(string $name): string
    {
        if (preg_match('/(\d+(?:[,.]\d+)?)\s?miles?\b/iu', $name, $m)) {
            return "{$m[1]} miles";
        }
        $meters = self::distanceFrom($name);

        return $meters >= 1000 ? str_replace('.', ',', rtrim(rtrim(number_format($meters / 1000, 1, '.', ''), '0'), '.')).' km' : "{$meters} m";
    }

    /** "10 km", "1,3 km", "10km", "400m", "50 miles" → meters. */
    public static function distanceFrom(string $name): ?int
    {
        // Öland Lighthouse Challenge runs 100 and 50 miles.
        if (preg_match('/(\d+(?:[,.]\d+)?)\s?miles?\b/iu', $name, $m)) {
            return (int) round((float) str_replace(',', '.', $m[1]) * 1609.344);
        }
        if (preg_match('/(\d+(?:[,.]\d+)?)\s?km\b/iu', $name, $m)) {
            return (int) round((float) str_replace(',', '.', $m[1]) * 1000);
        }

        return preg_match('/(\d+)\s?m\b/u', $name, $m) ? (int) $m[1] : null;
    }
}
