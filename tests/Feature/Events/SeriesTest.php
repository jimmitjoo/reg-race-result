<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Prize;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\Series;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;

function seriesUser(): User
{
    return User::factory()->create(['organizer_id' => Organizer::factory()->create(['timezone' => 'Europe/Stockholm'])->id]);
}

it('makes an arrangemang and its first upplaga, named with the year', function () {
    $this->actingAs($user = seriesUser());

    Volt::test('events.index')->set('name', 'Sylvesterloppet 2026')->set('date', '2026-12-31')->set('city', 'Kalmar')->call('create')->assertHasNoErrors();

    $event = Event::sole();
    expect($event)->name->toBe('Sylvesterloppet 2026')->slug->toBe('sylvesterloppet-2026')->city->toBe('Kalmar')
        ->and($event->series)->name->toBe('Sylvesterloppet')->organizer_id->toBe($user->organizer_id);
});

it('sets up a new upplaga like the latest one, with dates moved and local start times kept', function () {
    $this->actingAs($user = seriesUser());
    $series = Series::factory()->create(['organizer_id' => $user->organizer_id, 'name' => 'Sylvesterloppet']);
    $old = $series->addEdition('2025-12-31', ['city' => 'Kalmar', 'race_director' => 'Anna', 'weather' => 'Snö']);
    $race = $old->races()->create(['name' => 'Sylvesterloppet', 'onsite_price' => 35000, 'championship' => 'DM', 'championship_districts' => ['Småland']]);
    $race->priceSteps()->create(['until' => '2025-11-30', 'amount' => 25000]);
    // 13:00 in winter is 12:00 UTC; the new day keeps 13:00 local.
    $class = RaceClass::factory()->create(['event_id' => $old->id, 'race_id' => $race->id, 'name' => '10 km Kvinnor', 'start_at' => '2025-12-31 12:00:00', 'min_time_seconds' => 1200]);
    Registration::factory()->for($class)->create();
    Prize::factory()->create(['event_id' => $old->id, 'name' => 'Snabbaste dam']);

    Volt::test('events.index')->set("editionDate.{$series->id}", '2026-12-31')->call('newEdition', $series->id)->assertHasNoErrors();

    $new = $series->events()->latest('date')->first();
    expect($new)->name->toBe('Sylvesterloppet 2026')->slug->toBe('sylvesterloppet-2026')->city->toBe('Kalmar')->race_director->toBe('Anna')->weather->toBeNull()
        ->and($new->races()->sole())->onsite_price->toBe(35000)->championship_districts->toBe(['Småland'])
        ->and($new->races()->sole()->priceSteps()->sole())->amount->toBe(25000)->until->toDateString()->toBe('2026-11-30')
        ->and($new->raceClasses()->sole())->name->toBe('10 km Kvinnor')->min_time_seconds->toBe(1200)->race_id->toBe($new->races()->sole()->id)
        ->and($new->raceClasses()->sole()->start_at->setTimezone('Europe/Stockholm')->format('Y-m-d H:i'))->toBe('2026-12-31 13:00')
        ->and($new->prizes()->sole())->name->toBe('Snabbaste dam')->registration_id->toBeNull()
        ->and($new->allRegistrations()->count())->toBe(0);
});

it('makes the next upplaga when the name of an existing arrangemang is typed', function () {
    $this->actingAs($user = seriesUser());
    $series = Series::factory()->create(['organizer_id' => $user->organizer_id, 'name' => 'Kalmarmilen']);
    $series->addEdition('2026-05-01')->races()->create(['name' => 'Kalmarmilen']);

    Volt::test('events.index')->set('name', 'Kalmarmilen')->set('date', '2027-05-01')->call('create')->assertHasNoErrors();

    expect(Series::count())->toBe(1)->and($series->events()->count())->toBe(2)
        ->and($series->events()->latest('date')->first()->races()->sole()->name)->toBe('Kalmarmilen');
});

it('cannot make an upplaga of another organizer\'s arrangemang', function () {
    $this->actingAs(seriesUser());
    $foreign = Event::factory()->create();

    Volt::test('events.index')->set("editionDate.{$foreign->series_id}", '2027-01-01')->call('newEdition', $foreign->series_id)->assertNotFound();
});

it('gathers the existing events of an organizer into arrangemang by name without the year', function () {
    $organizer = Organizer::factory()->create();
    Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
    foreach (['Sylvesterloppet 2025', 'Sylvesterloppet 2026', 'Kalmarmilen'] as $name) {
        DB::table('events')->insert(['organizer_id' => $organizer->id, 'name' => $name, 'slug' => Str::slug($name), 'date' => '2026-01-01', 'timezone' => 'Europe/Stockholm']);
    }
    Artisan::call('migrate', ['--force' => true]);

    expect(Series::orderBy('name')->pluck('name')->all())->toBe(['Kalmarmilen', 'Sylvesterloppet'])
        ->and(Event::whereNull('series_id')->count())->toBe(0)
        ->and(Series::where('name', 'Sylvesterloppet')->sole()->events()->count())->toBe(2);
});

it('shows the next upplaga at the arrangemang\'s link, and the latest once the last has been run', function () {
    $this->travelTo('2026-06-01 12:00:00');
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if']);
    $series = Series::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Sylvesterloppet']);
    $old = $series->addEdition('2025-12-31');
    $next = $series->addEdition('2026-12-31');

    $this->get('/hogby-if/sylvesterloppet')->assertOk()->assertSee('Sylvesterloppet 2026');
    $this->get('/hogby-if/sylvesterloppet-2025')->assertOk()->assertSee('Sylvesterloppet 2025');
    expect($next->publicUrl())->toBe(url('/hogby-if/sylvesterloppet'))
        ->and($old->publicUrl())->toBe(url('/hogby-if/sylvesterloppet-2025'));

    $this->travelTo('2027-01-15 12:00:00');
    $this->get('/hogby-if/sylvesterloppet')->assertOk()->assertSee('Sylvesterloppet 2026');
});

it('keeps the link of an upplaga that has the arrangemang\'s slug', function () {
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if']);
    $series = Series::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Kalmarmilen']);
    $series->addEdition('2026-05-01', ['name' => 'Kalmarmilen', 'slug' => 'kalmarmilen']);

    $this->get('/hogby-if/kalmarmilen')->assertOk()->assertSee('Kalmarmilen');
});

it('links to the published results of earlier upplagor from the arrangemang\'s page', function () {
    $this->travelTo('2026-06-01 12:00:00');
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if']);
    $series = Series::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Sylvesterloppet']);
    $series->addEdition('2024-12-31', ['results_public_at' => '2025-01-01 10:00:00']);
    $series->addEdition('2025-12-31');
    $series->addEdition('2026-12-31');

    $this->get('/hogby-if/sylvesterloppet')->assertOk()
        ->assertSee(__('registration.earlier_results'))
        ->assertSee(url('/hogby-if/sylvesterloppet-2024/results'))
        ->assertDontSee(url('/hogby-if/sylvesterloppet-2025/results'));
});
