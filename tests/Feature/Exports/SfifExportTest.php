<?php

use App\Exports\SfifExport;
use App\Models\Chip;
use App\Models\ChipRead;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;

function sfifEvent(): array
{
    $organizer = Organizer::factory()->create(['name' => 'Högby IF']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Sylvesterloppet 2026', 'city' => 'Kalmar', 'date' => '2026-12-31', 'timezone' => 'Europe/Stockholm']);
    $race = Race::factory()->for($event)->create(['name' => 'Sylvesterloppet', 'type' => 'Väg', 'course_measurer' => 'Carl-Gustaf Nilsson', 'measured_on' => '2024-10-11']);
    $women = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Kvinnor 10 km', 'gender' => 'K', 'distance_meters' => 10000, 'start_at' => '2026-12-31 10:00:00', 'min_time_seconds' => 600]);
    $men = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Män 10 km', 'gender' => 'M', 'distance_meters' => 10000, 'start_at' => '2026-12-31 10:00:00', 'min_time_seconds' => 600]);

    return [$event, $race, $women, $men];
}

function finisher(RaceClass $class, int $bib, ?string $finish, array $attributes = []): Registration
{
    Chip::create(['event_id' => $class->event_id, 'code' => "c{$bib}", 'bib' => $bib]);
    if ($finish) {
        ChipRead::create(['event_id' => $class->event_id, 'reader' => '240', 'chip' => "c{$bib}", 'read_at' => "2026-12-31 {$finish}", 'unit' => 1, 'antenna' => 1]);
    }

    return Registration::factory()->for($class)->create($attributes + ['bib' => $bib, 'gender' => $class->gender, 'club' => 'Högby IF', 'birth_date' => '1995-06-28']);
}

function sfifRows(Event $event): array
{
    $lines = explode("\r\n", rtrim(substr(SfifExport::csv($event), 3), "\r\n"));

    return array_map(fn ($line) => array_combine(SfifExport::COLUMNS, explode(';', $line)), array_slice($lines, 1));
}

it('writes the exact SFIF header, UTF-8 with BOM, semicolons and CRLF', function () {
    [$event] = sfifEvent();
    $csv = SfifExport::csv($event);

    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and(explode("\r\n", substr($csv, 3))[0])->toBe('type;race_name;city;date;organizer;distance;gender;course_measurer;date_of_measurement;placing;agegroup;agegroupplacing;extra;firstname;lastname;country;club;birthdate;yb;result;netto');
});

it('writes a finisher row', function () {
    [$event, , $women] = sfifEvent();
    finisher($women, 32, '10:38:33.100', ['first_name' => 'Beatrice', 'last_name' => 'Lejnegård', 'club' => 'Oskarshamn RC']);

    expect(sfifRows($event)[0])->toBe([
        'type' => 'Väg', 'race_name' => 'Sylvesterloppet', 'city' => 'Kalmar', 'date' => '2026-12-31', 'organizer' => 'Högby IF',
        'distance' => '10 km', 'gender' => 'K', 'course_measurer' => 'Carl-Gustaf Nilsson', 'date_of_measurement' => '241011',
        'placing' => '1', 'agegroup' => '', 'agegroupplacing' => '', 'extra' => '', 'firstname' => 'Beatrice', 'lastname' => 'Lejnegård',
        'country' => '', 'club' => 'Oskarshamn RC', 'birthdate' => '1995-06-28', 'yb' => '', 'result' => '38:34', 'netto' => '',
    ]);
});

it('places within gender per distance, women before men, sorted by time, and adds age groups with their own placing', function () {
    [$event, , $women, $men] = sfifEvent();
    finisher($men, 1, '10:31:00.000', ['birth_date' => '1992-01-01']);
    finisher($women, 2, '10:40:00.000', ['birth_date' => '1984-11-16']);
    finisher($women, 3, '10:38:00.000', ['birth_date' => '1995-01-01']);
    finisher($women, 4, '10:45:00.000', ['birth_date' => '1989-01-01']);
    finisher($women, 5, '10:50:00.000', ['birth_date' => '2010-05-05']);

    $rows = array_map(fn ($r) => [$r['gender'], $r['placing'], $r['agegroup'], $r['agegroupplacing'], $r['result']], sfifRows($event));

    expect($rows)->toBe([
        ['K', '1', '', '', '38:00'],
        ['K', '2', 'K40', '1', '40:00'],
        ['K', '3', 'K35', '1', '45:00'],
        ['K', '4', 'F16', '1', '50:00'],
        ['M', '1', '', '', '31:00'],
    ]);
});

it('writes DNF, DQ and DNS in result with no placing, after the finishers', function () {
    [$event, , $women] = sfifEvent();
    finisher($women, 1, '10:40:00.000');
    finisher($women, 2, null, ['status' => 'dnf']);
    finisher($women, 3, '10:41:00.000', ['status' => 'dq']);
    finisher($women, 4, null, ['status' => 'dns']);

    expect(array_map(fn ($r) => [$r['placing'], $r['result']], sfifRows($event)))->toBe([
        ['1', '40:00'], ['', 'DNF'], ['', 'DQ'], ['', 'DNS'],
    ]);
});

it('leaves out untimed classes and runners without a finish or status', function () {
    [$event, , $women] = sfifEvent();
    finisher($women, 1, '10:40:00.000');
    finisher($women, 2, null);
    $kids = RaceClass::factory()->create(['event_id' => $event->id, 'timed' => false]);
    Registration::factory()->for($kids)->create(['bib' => 100]);

    expect(sfifRows($event))->toHaveCount(1);
});

it('writes Ej kontrollmätt for a standard road distance without measurement, and nothing for other distances', function () {
    [$event, $race, $women] = sfifEvent();
    $race->update(['course_measurer' => null, 'measured_on' => null]);
    finisher($women, 1, '10:40:00.000');
    $short = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'gender' => 'K', 'distance_meters' => 4700, 'start_at' => '2026-12-31 10:00:00', 'min_time_seconds' => 600]);
    finisher($short, 2, '10:30:00.000');

    $rows = collect(sfifRows($event))->keyBy('distance');

    expect($rows['10 km']['course_measurer'])->toBe('Ej kontrollmätt')
        ->and($rows['10 km']['date_of_measurement'])->toBe('')
        ->and($rows['4.7 km']['course_measurer'])->toBe('');
});

it('formats distances in km with at most one decimal', function (int $meters, string $expected) {
    expect(SfifExport::distance($meters))->toBe($expected);
})->with([[10000, '10 km'], [4700, '4.7 km'], [21097, '21.1 km'], [42195, '42.2 km'], [800, '0.8 km'], [1300, '1.3 km']]);

it('names the file with date, city and race name', function () {
    [$event] = sfifEvent();

    expect(SfifExport::filename($event))->toBe('261231 Kalmar Sylvesterloppet.csv');
});

it('downloads the file for the organizer', function () {
    [$event, , $women] = sfifEvent();
    finisher($women, 1, '10:40:00.000');

    $this->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]))
        ->get(route('exports.sfif', $event))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('261231 Kalmar Sylvesterloppet.csv');
});
