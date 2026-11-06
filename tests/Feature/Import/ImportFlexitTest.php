<?php

use App\Console\Commands\ImportFlexit;
use App\Models\Club;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\Series;
use Illuminate\Support\Facades\Mail;

// #14: a made-up export in the shape database/import/flexit.sql gives.
function flexitFile(array $registrations): string
{
    $runner = fn (array $r) => $r + [
        'class' => 1, 'first_name' => 'Anna ', 'last_name' => 'Karlsson', 'birth' => '19910403', 'gender' => 'K', 'club' => 'högby if',
        'email' => 'anna@example.com', 'phone' => null, 'price' => 30000, 'method' => 'VI', 'paid_at' => '2026-10-01T10:00:00',
        'ordered_at' => '2026-10-01T09:58:00', 'campaign' => 'Standard', 'standard' => true,
    ];
    $path = tempnam(sys_get_temp_dir(), 'flexit');
    file_put_contents($path, json_encode([
        'competitions' => [
            ['id' => 10, 'arrangemang' => 'Sylvesterloppet ', 'race' => 'Sylvesterloppet', 'date' => '2026-12-31'],
            ['id' => 11, 'arrangemang' => 'Sylvesterloppet', 'race' => 'Lilla Sylvesterloppet', 'date' => '2026-12-31'],
        ],
        'classes' => [
            ['id' => 1, 'competition' => 10, 'name' => '10 km  Kvinnor ', 'sort' => 2],
            ['id' => 2, 'competition' => 10, 'name' => '10 km Män', 'sort' => 1],
            ['id' => 3, 'competition' => 11, 'name' => 'P/F7 1 km', 'sort' => 1],
            ['id' => 4, 'competition' => 11, 'name' => 'P 13 2 km', 'sort' => 2],
        ],
        'prices' => [
            ['class' => 1, 'until' => '2026-12-24', 'amount' => 30000], ['class' => 1, 'until' => '2026-12-29', 'amount' => 40000],
            ['class' => 2, 'until' => '2026-12-24', 'amount' => 30000], ['class' => 2, 'until' => '2026-12-29', 'amount' => 40000],
            ['class' => 3, 'until' => '2026-12-24', 'amount' => 15000], ['class' => 3, 'until' => '2026-12-29', 'amount' => 20000],
            ['class' => 4, 'until' => '2026-12-24', 'amount' => 15000], ['class' => 4, 'until' => '2026-12-29', 'amount' => 20000],
        ],
        'registrations' => array_map($runner, $registrations),
    ]));

    return $path;
}

it('brings over the arrangemang, races, classes and prices of an open upplaga', function () {
    Organizer::factory()->create(['slug' => 'hogby-if', 'timezone' => 'Europe/Stockholm']);

    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => flexitFile([])])->assertSuccessful();

    $event = Event::sole();
    expect(Series::sole()->name)->toBe('Sylvesterloppet')
        ->and($event)->name->toBe('Sylvesterloppet 2026')->date->toDateString()->toBe('2026-12-31')
        ->and($event->races()->orderBy('name')->pluck('name')->all())->toBe(['Lilla Sylvesterloppet', 'Sylvesterloppet'])
        ->and($event->raceClasses()->where('name', '10 km Kvinnor')->sole())->gender->toBe('K')->distance_meters->toBe(10000)->timed->toBeTrue()
        ->and($event->raceClasses()->where('name', 'P/F7 1 km')->sole()->gender)->toBeNull();

    $race = $event->races()->where('name', 'Sylvesterloppet')->sole();
    expect($race->priceSteps->map(fn ($s) => [$s->until->toDateString(), $s->amount])->all())->toBe([['2026-12-24', 30000], ['2026-12-29', 40000]])
        ->and($event->races()->where('name', 'Lilla Sylvesterloppet')->sole()->priceSteps->map(fn ($s) => $s->amount)->all())->toBe([15000, 20000]);
});

it('imports paid registrations without mailing receipts, keeping only the birth date', function () {
    Mail::fake();
    Organizer::factory()->create(['slug' => 'hogby-if', 'timezone' => 'Europe/Stockholm']);
    Club::create(['name' => 'Högby IF', 'district' => 'Småland', 'federation' => 'SFIF', 'external_id' => '1234']);

    $file = flexitFile([
        [],
        ['first_name' => 'Bo', 'birth' => '20180365', 'gender' => 'M', 'class' => 4, 'price' => 0, 'method' => 'GO', 'paid_at' => null, 'campaign' => 'Högby IF', 'standard' => false],
        ['first_name' => 'Cilla', 'method' => 'PG', 'paid_at' => null],
    ]);

    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => $file])
        ->expectsOutputToContain('Cilla Karlsson (10 km Kvinnor, PG)')
        ->assertSuccessful();

    Mail::assertNothingSent();
    expect(Registration::count())->toBe(2);

    $anna = Registration::where('first_name', 'Anna')->sole();
    expect($anna)->birth_date->toDateString()->toBe('1991-04-03')->club->toBe('Högby IF')->club_id->not->toBeNull()
        ->price->toBe(30000)->payment_method->toBe('flexit')->note->toBeNull()->terms_accepted_at->toBeNull()
        ->and($anna->paid_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-01 08:00')
        ->and($anna->created_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-01 07:58');

    // A samordningsnummer has 60 added to the day.
    expect(Registration::where('first_name', 'Bo')->sole())->birth_date->toDateString()->toBe('2018-03-05')
        ->payment_method->toBe('free')->note->toBe('Kampanj: Högby IF')->and(Event::sole()->registrations()->count())->toBe(2);
});

it('imports a runner who paid twice in the same class once, and says so', function () {
    Organizer::factory()->create(['slug' => 'hogby-if']);

    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => flexitFile([[], []])])
        ->expectsOutputToContain('Anna Karlsson (10 km Kvinnor)')
        ->assertSuccessful();

    expect(Registration::count())->toBe(1);
});

it('adds nothing on a second run, and nothing at all on a dry run', function () {
    Organizer::factory()->create(['slug' => 'hogby-if']);
    $file = flexitFile([[]]);

    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => $file, '--dry-run' => true])->assertSuccessful();
    expect(Event::count())->toBe(0)->and(Registration::count())->toBe(0);

    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => $file])->assertSuccessful();
    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => $file])->assertSuccessful();
    expect(Series::count())->toBe(1)->and(Event::count())->toBe(1)->and(Event::sole()->races()->count())->toBe(2)
        ->and(Event::sole()->raceClasses()->count())->toBe(4)->and(Event::sole()->races()->first()->priceSteps()->count())->toBe(2)
        ->and(Registration::count())->toBe(1);
});

it('reads gender and distance from class names', function (string $name, ?string $gender, ?int $meters) {
    expect(ImportFlexit::genderFrom($name))->toBe($gender)->and(ImportFlexit::distanceFrom($name))->toBe($meters);
})->with([
    ['10 km Män', 'M', 10000], ['K 10 km', 'K', 10000], ['10km M', 'M', 10000], ['F13 2 km', 'K', 2000],
    ['P/F11 1,3 km', null, 1300], ['P5/F5 400m', null, 400], ['Promenad 5 km', null, 5000], ['M/K 100 miles', null, 160934],
]);

it('takes the active one when the old system has the same race twice on one day', function () {
    Organizer::factory()->create(['slug' => 'hogby-if']);
    $data = json_decode(file_get_contents(flexitFile([[], ['class' => 9, 'first_name' => 'Fel']])), true);
    $data['competitions'][] = ['id' => 9, 'arrangemang' => 'Sylvesterloppet', 'race' => 'Sylvesterloppet', 'date' => '2026-12-31', 'name' => 'Sylvesterloppet fel', 'active' => false];
    $data['classes'][] = ['id' => 9, 'competition' => 9, 'name' => '10 km Kvinnor', 'sort' => 1];
    $file = tempnam(sys_get_temp_dir(), 'flexit');
    file_put_contents($file, json_encode($data));

    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => $file])
        ->expectsOutputToContain('Sylvesterloppet fel (2026-12-31, 1 anmälningar, id 9)')
        ->assertSuccessful();

    expect(Registration::pluck('first_name')->all())->toBe(['Anna']);
});

it('puts classes with a price list of their own in a race of their own', function () {
    $this->travelTo('2026-01-01 12:00:00');
    Organizer::factory()->create(['slug' => 'hogby-if']);
    $step = fn (int $class, int $first, int $last) => [['class' => $class, 'until' => '2026-07-15', 'amount' => $first], ['class' => $class, 'until' => '2026-07-20', 'amount' => $last]];
    $file = tempnam(sys_get_temp_dir(), 'flexit');
    file_put_contents($file, json_encode([
        'competitions' => [
            ['id' => 1, 'arrangemang' => 'Löttorpsvarvet', 'race' => 'Löttorpsvarvet', 'date' => '2026-07-22', 'name' => 'Löttorpsvarvet', 'active' => true],
            ['id' => 2, 'arrangemang' => 'Atea Kalmarmilen', 'race' => 'Atea Kalmarmilen', 'date' => '2026-07-29', 'name' => 'Atea Kalmarmilen', 'active' => true],
        ],
        'classes' => [
            ['id' => 1, 'competition' => 1, 'name' => '6,2 km Män', 'sort' => 1], ['id' => 2, 'competition' => 1, 'name' => 'P13 1,4 km', 'sort' => 2],
            ['id' => 3, 'competition' => 1, 'name' => 'P7/F7 800 m', 'sort' => 3], ['id' => 4, 'competition' => 1, 'name' => 'Utan pris', 'sort' => 4],
            ['id' => 8, 'competition' => 1, 'name' => 'P15 2,9 km', 'sort' => 5],
            ['id' => 9, 'competition' => 2, 'name' => '10 km 13 år o yngre', 'sort' => 4],
            ['id' => 5, 'competition' => 2, 'name' => '10km M', 'sort' => 1], ['id' => 6, 'competition' => 2, 'name' => 'DM 10km M', 'sort' => 2], ['id' => 7, 'competition' => 2, 'name' => 'DM 10km K', 'sort' => 3],
        ],
        'prices' => [...$step(1, 25000, 30000), ...$step(2, 15000, 20000), ...$step(3, 15000, 20000), ...$step(8, 15000, 20000), ...$step(5, 35000, 45000), ...$step(9, 10000, 15000), ...$step(6, 30000, 60000), ...$step(7, 30000, 60000)],
        'registrations' => [],
    ]));

    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => $file])
        ->expectsOutputToContain('Löttorpsvarvet 2,9 km, 1,4 km och 800 m: P13 1,4 km, P7/F7 800 m, P15 2,9 km')
        ->assertSuccessful();

    $race = fn (string $name) => Race::where('name', $name)->sole();
    $prices = fn (string $name) => $race($name)->priceSteps->map(fn ($s) => $s->amount)->all();
    expect($prices('Löttorpsvarvet'))->toBe([25000, 30000])
        ->and($race('Löttorpsvarvet')->raceClasses()->pluck('name')->all())->toBe(['6,2 km Män', 'Utan pris'])
        ->and($prices('Löttorpsvarvet 2,9 km, 1,4 km och 800 m'))->toBe([15000, 20000])
        // Same distance as the main race: named by the word the classes start with.
        ->and($prices('Atea Kalmarmilen'))->toBe([35000, 45000])
        ->and($prices('Atea Kalmarmilen DM'))->toBe([30000, 60000])
        ->and($race('Atea Kalmarmilen DM')->raceClasses()->count())->toBe(2)
        // One class at the same distance: the rest of its name, not "10".
        ->and($prices('Atea Kalmarmilen 13 år o yngre'))->toBe([10000, 15000]);
});

it('moves registrations to the first race day after they were ordered, also from a copy at the wrong date', function () {
    Organizer::factory()->create(['slug' => 'hogby-if']);
    $data = json_decode(file_get_contents(flexitFile([
        ['class' => 1, 'first_name' => 'Ny', 'ordered_at' => '2026-12-01T10:00:00', 'paid_at' => '2026-12-01T10:00:00'],
        ['class' => 1, 'first_name' => 'Gammal', 'ordered_at' => '2025-12-30T10:00:00', 'paid_at' => '2025-12-30T10:00:00'],
        ['class' => 9, 'first_name' => 'Kopia', 'ordered_at' => '2025-12-20T10:00:00', 'paid_at' => '2025-12-20T10:00:00'],
    ])), true);
    // The copy on 2026-12-30 is no race day: the active competition the day after wins.
    $dates = [['d' => '2025-12-31', 'active' => 0, 'n' => 1, 'ok' => 0], ['d' => '2026-12-30', 'active' => 0, 'n' => 1, 'ok' => 0], ['d' => '2026-12-31', 'active' => 1, 'n' => 2, 'ok' => 1]];
    $data['competitions'] = array_map(fn ($c) => $c + ['dates' => $dates], $data['competitions']);
    // The empty competition at last year's date, which last year's runners were moved out of.
    $data['competitions'][] = ['id' => 8, 'arrangemang' => 'Sylvesterloppet', 'race' => 'Sylvesterloppet', 'date' => '2025-12-31', 'name' => 'Sylvesterloppet', 'active' => false, 'dates' => $dates];
    $data['classes'][] = ['id' => 8, 'competition' => 8, 'name' => '10 km Kvinnor', 'sort' => 1];
    $data['competitions'][] = ['id' => 9, 'arrangemang' => 'Sylvesterloppet', 'race' => 'Sylvesterloppet', 'date' => '2026-12-30', 'name' => 'Sylvesterloppet kopia', 'active' => false, 'dates' => $dates];
    $data['classes'][] = ['id' => 9, 'competition' => 9, 'name' => '10 km Kvinnor', 'sort' => 1];
    $file = tempnam(sys_get_temp_dir(), 'flexit');
    file_put_contents($file, json_encode($data));

    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => $file])
        ->expectsOutputToContain('Sylvesterloppet kopia (2026-12-30): 1 anmälningar till upplagan 2025-12-31')
        ->assertSuccessful();

    $runners = fn (string $date) => Event::whereDate('date', $date)->sole()->registrations()->orderBy('first_name')->pluck('first_name')->all();
    expect(Event::count())->toBe(2)
        ->and($runners('2026-12-31'))->toBe(['Ny'])
        ->and($runners('2025-12-31'))->toBe(['Gammal', 'Kopia'])
        // One class, where the empty competition and the moved runners meet; prices moved back a year.
        ->and(Event::whereDate('date', '2025-12-31')->sole()->raceClasses()->where('name', '10 km Kvinnor')->count())->toBe(1)
        ->and(Race::whereHas('event', fn ($q) => $q->whereDate('date', '2025-12-31'))->where('name', 'Sylvesterloppet')->sole()->priceSteps->first()->until->toDateString())->toBe('2025-12-24');
});

it('names a race in miles as the old system did', function () {
    $this->travelTo('2026-01-01 12:00:00');
    Organizer::factory()->create(['slug' => 'hogby-if']);
    $file = tempnam(sys_get_temp_dir(), 'flexit');
    file_put_contents($file, json_encode([
        'competitions' => [['id' => 1, 'arrangemang' => 'Öland Lighthouse Challenge', 'race' => 'Öland Lighthouse Challenge', 'date' => '2026-05-29', 'name' => 'Öland Lighthouse Challenge', 'active' => true]],
        'classes' => [['id' => 1, 'competition' => 1, 'name' => 'M/K 100 miles', 'sort' => 1], ['id' => 2, 'competition' => 1, 'name' => 'M/K 50 miles', 'sort' => 2], ['id' => 3, 'competition' => 1, 'name' => 'M/K 50 km', 'sort' => 3]],
        'prices' => [['class' => 1, 'until' => '2026-05-22', 'amount' => 149000], ['class' => 2, 'until' => '2026-05-22', 'amount' => 99000], ['class' => 3, 'until' => '2026-05-22', 'amount' => 59000]],
        'registrations' => [],
    ]));

    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => $file])->assertSuccessful();

    expect(Race::orderBy('name')->pluck('name')->all())->toBe(['Öland Lighthouse Challenge', 'Öland Lighthouse Challenge 50 km', 'Öland Lighthouse Challenge 50 miles'])
        ->and(RaceClass::where('name', 'M/K 50 miles')->sole()->distance_meters)->toBe(80467);
});

it('imports a team class with its size, and two teams from the same contact (#31)', function () {
    Organizer::factory()->create(['slug' => 'hogby-if']);
    $team = fn (string $name, array $members) => ['class' => 1, 'team' => $name, 'members' => array_map(fn ($m) => ['name' => $m], $members)];
    $data = json_decode(file_get_contents(flexitFile([$team('Kyrkans Löpare', ['A', 'B', 'C']), $team('Kyrkans Gamlingar', ['D', 'E', ' '])])), true);
    $data['classes'][0] += ['team_size' => 3];
    $file = tempnam(sys_get_temp_dir(), 'flexit');
    file_put_contents($file, json_encode($data));

    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => $file])->assertSuccessful();
    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => $file])->assertSuccessful();

    expect(RaceClass::where('name', '10 km Kvinnor')->sole()->team_size)->toBe(3)
        ->and(Registration::orderBy('team_name')->get()->map(fn ($r) => [$r->team_name, $r->team_members])->all())
        ->toBe([['Kyrkans Gamlingar', ['D', 'E']], ['Kyrkans Löpare', ['A', 'B', 'C']]]);
});

it('moves this year\'s registrations out of a competition with an old date', function () {
    Organizer::factory()->create(['slug' => 'hogby-if']);
    $dates = [['d' => '2023-08-25', 'ok' => 5], ['d' => '2023-11-07', 'ok' => 0], ['d' => '2024-02-06', 'ok' => 0], ['d' => '2024-08-23', 'ok' => 5], ['d' => '2025-08-22', 'ok' => 0]];
    $file = tempnam(sys_get_temp_dir(), 'flexit');
    file_put_contents($file, json_encode([
        'competitions' => [
            // Hagbloms Nattloppet 2025's runners, under 2023-11-07; 2025-08-22 is the empty competition they belong in.
            ['id' => 1, 'arrangemang' => 'Hagbloms Nattloppet', 'race' => 'Hagbloms Nattloppet', 'date' => '2023-11-07', 'name' => 'Hagbloms Nattloppet', 'active' => false, 'dates' => $dates],
            ['id' => 2, 'arrangemang' => 'Hagbloms Nattloppet', 'race' => 'Hagbloms Nattloppet', 'date' => '2025-08-22', 'name' => 'Hagbloms Nattloppet', 'active' => false, 'dates' => $dates],
            // An empty copy at a date that is no race day makes no upplaga.
            ['id' => 3, 'arrangemang' => 'Hagbloms Nattloppet', 'race' => 'Hagbloms Nattloppet', 'date' => '2024-02-06', 'name' => 'Kopia av Hagbloms Nattloppet', 'active' => false, 'dates' => $dates],
        ],
        'classes' => [['id' => 1, 'competition' => 1, 'name' => '10 km Män', 'sort' => 1], ['id' => 2, 'competition' => 2, 'name' => '10 km Män', 'sort' => 1], ['id' => 3, 'competition' => 3, 'name' => '10 km Män', 'sort' => 1]],
        'prices' => [['class' => 1, 'until' => '2023-10-31', 'amount' => 35000], ['class' => 2, 'until' => '2025-08-15', 'amount' => 35000]],
        'registrations' => [[
            'class' => 1, 'first_name' => 'Erik', 'last_name' => 'Ek', 'birth' => '19800101', 'gender' => 'M', 'club' => null, 'email' => 'erik@example.com', 'phone' => null,
            'price' => 35000, 'method' => 'VI', 'paid_at' => '2025-03-01T10:00:00', 'ordered_at' => '2025-03-01T10:00:00', 'campaign' => 'Standard', 'standard' => true,
        ]],
    ]));

    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => $file])
        ->expectsOutputToContain('Hagbloms Nattloppet (2023-11-07): 1 anmälningar till upplagan 2025-08-22')
        ->assertSuccessful();

    expect(Event::pluck('date')->map->toDateString()->all())->toBe(['2025-08-22'])
        ->and(Event::sole()->registrations()->sole()->first_name)->toBe('Erik')
        ->and(Event::sole()->raceClasses()->count())->toBe(1);
});

it('makes no upplaga of a competition that has been run without anybody', function () {
    $this->travelTo('2026-03-01 12:00:00');
    Organizer::factory()->create(['slug' => 'hogby-if']);
    $file = tempnam(sys_get_temp_dir(), 'flexit');
    file_put_contents($file, json_encode([
        'competitions' => [
            ['id' => 1, 'arrangemang' => 'Ölands Kyrkstafett', 'race' => 'Ölands Kyrkstafett, långa', 'date' => '2025-05-03', 'name' => 'Ölands Kyrkstafett, långa', 'active' => false],
            // Ahead, with nobody yet: registration may just have opened.
            ['id' => 2, 'arrangemang' => 'Ölands Kyrkstafett', 'race' => 'Ölands Kyrkstafett, långa', 'date' => '2026-05-09', 'name' => 'Ölands Kyrkstafett, långa', 'active' => true],
        ],
        'classes' => [['id' => 1, 'competition' => 1, 'name' => 'Öppen klass', 'sort' => 1], ['id' => 2, 'competition' => 2, 'name' => 'Öppen klass', 'sort' => 1]],
        'prices' => [],
        'registrations' => [],
    ]));

    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => $file])->expectsOutputToContain('Ölands Kyrkstafett 2025-05-03')->assertSuccessful();

    expect(Event::pluck('date')->map->toDateString()->all())->toBe(['2026-05-09']);
});

it('takes a first upplaga at another time of year for a race day', function () {
    Organizer::factory()->create(['slug' => 'hogby-if']);
    // Öland Lighthouse Challenge started in August 2022 and has been run in late May since.
    $dates = [['d' => '2022-08-13'], ['d' => '2023-06-03'], ['d' => '2024-05-31'], ['d' => '2025-05-31']];
    $runner = fn (string $name, string $ordered) => [
        'class' => 1, 'first_name' => $name, 'last_name' => 'Ek', 'birth' => '19800101', 'gender' => 'M', 'club' => null, 'email' => 'a@example.com', 'phone' => null,
        'price' => 99000, 'method' => 'VI', 'paid_at' => "{$ordered}T10:00:00", 'ordered_at' => "{$ordered}T10:00:00", 'campaign' => 'Standard', 'standard' => true,
    ];
    $file = tempnam(sys_get_temp_dir(), 'flexit');
    file_put_contents($file, json_encode([
        'competitions' => [['id' => 1, 'arrangemang' => 'Öland Lighthouse Challenge', 'race' => 'Öland Lighthouse Challenge', 'date' => '2022-08-13', 'name' => 'Öland Lighthouse Challenge', 'active' => false, 'dates' => $dates]],
        'classes' => [['id' => 1, 'competition' => 1, 'name' => 'M/K 100 miles', 'sort' => 1]],
        'prices' => [],
        'registrations' => [$runner('Juni', '2022-06-15'), $runner('Augusti', '2022-08-10')],
    ]));

    $this->artisan('import:flexit', ['organizer' => 'hogby-if', 'file' => $file])->assertSuccessful();

    expect(Event::pluck('date')->map->toDateString()->all())->toBe(['2022-08-13'])
        ->and(Event::sole()->registrations()->count())->toBe(2);
});
