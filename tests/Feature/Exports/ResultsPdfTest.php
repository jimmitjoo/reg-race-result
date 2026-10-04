<?php

use App\Exports\ResultsPdf;
use App\Models\Chip;
use App\Models\ChipRead;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use Livewire\Volt\Volt;

function pdfEvent(): array
{
    $organizer = Organizer::factory()->create(['name' => 'Högby IF']);
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id, 'name' => 'Sylvesterloppet 2026', 'city' => 'Kalmar', 'date' => '2026-12-31',
        'timezone' => 'Europe/Stockholm', 'race_director' => 'Carl-Gustaf Nilsson', 'weather' => '3 grader och mulet',
        'contact_email' => 'friidrott@hogbyif.se',
    ]);
    $race = Race::factory()->for($event)->create(['name' => 'Sylvesterloppet', 'course_measurer' => 'Carl-Gustaf Nilsson', 'measured_on' => '2024-10-11']);
    $women = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Kvinnor 10 km', 'start_at' => '2026-12-31 10:00:00', 'min_time_seconds' => 600]);

    return [$event, $women];
}

it('renders the header like today\'s result lists', function () {
    [$event, $women] = pdfEvent();
    Chip::create(['event_id' => $event->id, 'code' => 'c729', 'bib' => 729]);
    ChipRead::create(['event_id' => $event->id, 'reader' => '240', 'chip' => 'c729', 'read_at' => '2026-12-31 10:36:14', 'unit' => 1, 'antenna' => 1]);
    Registration::factory()->for($women)->create(['bib' => 729, 'first_name' => 'Ida', 'last_name' => 'Nilsson', 'birth_date' => '1991-01-01', 'club' => 'Högby IF']);
    Registration::factory()->for($women)->create(['bib' => 730, 'status' => 'dns']);

    $html = ResultsPdf::html($event);

    expect($html)
        ->toContain('Resultat Sylvesterloppet 2026')
        ->toContain('2026-12-31')
        ->toContain('Kalmar')
        ->toContain('Högby IF')
        ->toContain('Carl-Gustaf Nilsson')
        ->toContain('3 grader och mulet')
        ->toContain(__('pdf.registered_finished', ['registered' => 2, 'finished' => 1]))
        ->toContain(__('pdf.measured', ['date' => '2024-10-11', 'name' => 'Carl-Gustaf Nilsson']))
        ->toContain('friidrott@hogbyif.se');
});

it('lists each class with its start time and the result columns', function () {
    [$event, $women] = pdfEvent();
    Chip::create(['event_id' => $event->id, 'code' => 'c729', 'bib' => 729]);
    ChipRead::create(['event_id' => $event->id, 'reader' => '240', 'chip' => 'c729', 'read_at' => '2026-12-31 10:36:14', 'unit' => 1, 'antenna' => 1]);
    Registration::factory()->for($women)->create(['bib' => 729, 'first_name' => 'Ida', 'last_name' => 'Nilsson', 'birth_date' => '1991-01-01', 'club' => 'Högby IF', 'country' => 'SUI']);
    $kids = RaceClass::factory()->create(['event_id' => $event->id, 'name' => 'F11 1.3 km', 'timed' => false, 'start_at' => '2026-12-31 09:30:00']);
    Registration::factory()->for($kids)->create(['bib' => 102, 'first_name' => 'Liten', 'last_name' => 'Löpare']);

    $html = ResultsPdf::html($event);

    expect($html)
        ->toContain('Kvinnor 10 km')->toContain('11:00:00')
        ->toContain('Ida')->toContain('Nilsson')->toContain('>91<')->toContain('36:14')->toContain('729')->toContain('SUI')
        ->toContain('F11 1.3 km')->toContain(__('results.untimed'))->toContain('Liten');
});

it('downloads a PDF named after the event', function () {
    [$event] = pdfEvent();

    $response = $this->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]))
        ->get(route('events.exports.pdf', $event));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('Sylvesterloppet 2026 Resultat.pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('edits the result list details of an event', function () {
    [$event] = pdfEvent();
    $this->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    Volt::test('events.show', ['event' => $event])
        ->set('city', 'Fredriksskans, Kalmar')
        ->set('raceDirector', 'Annan Person')
        ->set('weather', '13 grader och sol')
        ->set('contactEmail', 'resultat@example.com')
        ->call('saveDetails')
        ->assertHasNoErrors();

    expect($event->fresh())
        ->city->toBe('Fredriksskans, Kalmar')
        ->race_director->toBe('Annan Person')
        ->weather->toBe('13 grader och sol')
        ->contact_email->toBe('resultat@example.com');
});
