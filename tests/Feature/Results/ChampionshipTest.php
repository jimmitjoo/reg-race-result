<?php

use App\Exports\ResultsPdf;
use App\Exports\SfifExport;
use App\Models\Chip;
use App\Models\ChipRead;
use App\Models\Club;
use App\Models\Event;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use App\Results\ResultList;
use App\Results\ResultRow;
use Livewire\Volt\Volt;

function championshipEvent(array $race = []): array
{
    $event = Event::factory()->create(['date' => '2026-07-29', 'timezone' => 'Europe/Stockholm', 'city' => 'Kalmar']);
    $race = Race::factory()->for($event)->create(['name' => 'Kalmarmilen'] + $race);
    $class = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Kvinnor 10 km', 'gender' => 'K', 'distance_meters' => 10000, 'start_at' => '2026-07-29 17:10:00', 'min_time_seconds' => 600]);
    $clubs = [
        'Högby IF' => Club::create(['federation' => 'SFIF', 'external_id' => '506', 'name' => 'Högby IF', 'district' => 'Småland']),
        'IK Akele' => Club::create(['federation' => 'SFIF', 'external_id' => '1', 'name' => 'IK Akele', 'district' => 'Östergötland']),
        'Hälle IF' => Club::create(['federation' => 'SFIF', 'external_id' => '2', 'name' => 'Hälle IF', 'district' => 'Bohuslän-Dal']),
    ];

    return [$event, $race, $class, $clubs];
}

function champRunner(RaceClass $class, int $bib, string $finish, ?Club $club, string $born = '1995-01-01'): Registration
{
    Chip::create(['event_id' => $class->event_id, 'code' => "c{$bib}", 'bib' => $bib]);
    ChipRead::create(['event_id' => $class->event_id, 'reader' => '240', 'chip' => "c{$bib}", 'read_at' => "2026-07-29 {$finish}", 'unit' => 1, 'antenna' => 1]);

    return Registration::factory()->for($class)->create(['bib' => $bib, 'gender' => 'K', 'birth_date' => $born, 'club' => $club?->name ?? 'Stockholm', 'club_id' => $club?->id]);
}

function placings(Event $event, RaceClass $class): array
{
    return array_map(fn (ResultRow $r) => [$r->bib, $r->placing, $r->championshipPlacing, $r->veteranGroup, $r->veteranPlacing], ResultList::for($event)[$class->id]->rows);
}

it('places DM among runners from clubs in the chosen districts, and VDM per veteran group', function () {
    [$event, , $class, $clubs] = championshipEvent(['championship' => 'DM', 'championship_districts' => ['Småland', 'Blekinge', 'Östergötland'], 'championship_veterans' => true]);
    champRunner($class, 170, '17:43:38.000', $clubs['Hälle IF']);
    champRunner($class, 60, '17:46:09.000', $clubs['Högby IF']);
    champRunner($class, 177, '17:46:53.000', null);
    champRunner($class, 46, '17:47:00.000', $clubs['Högby IF'], '1991-06-28');
    champRunner($class, 29, '17:49:12.000', $clubs['IK Akele'], '1984-11-16');

    expect(placings($event, $class))->toBe([
        [170, 1, null, null, null],
        [60, 2, 1, null, null],
        [177, 3, null, null, null],
        [46, 4, 2, 'K35', 1],
        [29, 5, 3, 'K40', 1],
    ]);
});

it('places SM among everyone with a federation club, regardless of district', function () {
    [$event, , $class, $clubs] = championshipEvent(['championship' => 'SM']);
    champRunner($class, 170, '17:43:38.000', $clubs['Hälle IF']);
    champRunner($class, 177, '17:46:53.000', null);
    champRunner($class, 60, '17:47:09.000', $clubs['Högby IF']);

    expect(array_column(placings($event, $class), 2))->toBe([1, null, 2]);
});

it('shows age groups with their placing for races that have them', function () {
    [$event, $race, $class, $clubs] = championshipEvent(['age_groups' => true]);
    champRunner($class, 1, '17:40:00.000', null, '1984-01-01');
    champRunner($class, 2, '17:41:00.000', null, '1995-01-01');
    champRunner($class, 3, '17:42:00.000', null, '1982-01-01');

    $rows = ResultList::for($event)[$class->id]->rows;

    expect(array_map(fn ($r) => [$r->ageGroup, $r->ageGroupPlacing], $rows))->toBe([['K40', 1], [null, null], ['K40', 2]]);
});

it('writes DM placings in the extra column of the federation file', function () {
    [$event, , $class, $clubs] = championshipEvent(['championship' => 'DM', 'championship_districts' => ['Småland']]);
    champRunner($class, 60, '17:46:09.000', $clubs['Högby IF']);
    champRunner($class, 177, '17:46:53.000', null);

    $lines = explode("\r\n", rtrim(substr(SfifExport::csv($event), 3)));
    $extra = array_map(fn ($line) => explode(';', $line)[12], array_slice($lines, 1));

    expect($extra)->toBe(['DM 1', '']);
});

it('saves the championship settings of a race', function () {
    [$event, $race] = championshipEvent();
    test()->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    Volt::test('events.show', ['event' => $event])
        ->call('editRace', $race->id)
        ->set('championship', 'DM')
        ->set('championshipDistricts', ['Småland', 'Blekinge', 'Östergötland'])
        ->set('championshipVeterans', true)
        ->call('saveRace')
        ->assertHasNoErrors();

    expect($race->fresh())
        ->championship->toBe('DM')
        ->championship_districts->toBe(['Småland', 'Blekinge', 'Östergötland'])
        ->championship_veterans->toBeTrue();
});

it('shows DM and VDM columns on the result page and in the PDF only for championship races', function () {
    [$event, , $class, $clubs] = championshipEvent(['championship' => 'DM', 'championship_districts' => ['Småland'], 'championship_veterans' => true]);
    champRunner($class, 46, '17:47:00.000', $clubs['Högby IF'], '1991-06-28');
    $plain = RaceClass::factory()->create(['event_id' => $event->id, 'name' => 'Motion 5 km']);
    test()->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    $this->get(route('events.results', $event))->assertSee(__('results.championship_placing', ['name' => 'DM']))->assertSee(__('results.championship_placing', ['name' => 'VDM']))->assertSee('1 K35');
    expect(ResultsPdf::html($event))->toContain('DM plac')->toContain('1 K35');
});
