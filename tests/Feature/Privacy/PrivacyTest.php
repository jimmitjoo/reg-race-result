<?php

use App\Exports\ResultsPdf;
use App\Exports\SfifExport;
use App\Models\Chip;
use App\Models\ChipRead;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use App\Payments\StripeCheckout;
use App\Results\ResultList;
use Livewire\Volt\Volt;

function privacyEvent(): array
{
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if', 'name' => 'Högby IF']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'slug' => 'nattloppet-2026', 'date' => '2026-08-21', 'timezone' => 'Europe/Stockholm', 'contact_email' => 'friidrott@hogbyif.se']);
    $race = Race::factory()->for($event)->create();
    $race->priceSteps()->create(['until' => '2026-08-20', 'amount' => 25000]);
    $class = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'start_at' => '2026-08-21 20:00:00', 'min_time_seconds' => 600]);

    return [$organizer, $event, $class];
}

function finishedRunner(RaceClass $class, int $bib, array $attributes = []): Registration
{
    Chip::create(['event_id' => $class->event_id, 'code' => "c{$bib}", 'bib' => $bib]);
    ChipRead::create(['event_id' => $class->event_id, 'reader' => '240', 'chip' => "c{$bib}", 'read_at' => '2026-08-21 20:30:00', 'unit' => 1, 'antenna' => 1]);

    return Registration::factory()->for($class)->create($attributes + ['bib' => $bib, 'paid_at' => now(), 'gender' => 'K', 'birth_date' => '1990-01-01']);
}

it('requires accepting the terms when registering and remembers when', function () {
    [, , $class] = privacyEvent();
    $this->travelTo(now()->setDate(2026, 8, 1));
    $this->mock(StripeCheckout::class)->shouldReceive('create')->andReturn(['id' => 'cs', 'url' => 'https://checkout.stripe.com/x']);
    $form = fn () => Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'nattloppet-2026'])
        ->set('raceClassId', (string) $class->id)->set('firstName', 'Anna')->set('lastName', 'Karlsson')
        ->set('birthDate', '1991-04-03')->set('gender', 'K')->set('email', 'anna@example.com');

    $form()->call('register')->assertHasErrors(['acceptTerms']);
    $form()->set('acceptTerms', true)->call('register')->assertHasNoErrors();

    expect(Registration::sole()->terms_accepted_at)->not->toBeNull();
});

it('shows the terms of the organizer', function () {
    privacyEvent();

    $this->get('/hogby-if/terms')
        ->assertOk()
        ->assertSee('Högby IF')
        ->assertSee(__('privacy.published_heading'))
        ->assertSee(__('privacy.retention', ['months' => config('privacy.contact_retention_months')]));
});

it('shows hidden runners as anonymous in public lists but keeps them in the federation file', function () {
    [, $event, $class] = privacyEvent();
    finishedRunner($class, 1, ['first_name' => 'Hemlig', 'last_name' => 'Person', 'club' => 'Högby IF', 'hidden_at' => now()]);

    expect(ResultList::for($event)[$class->id]->rows[0])
        ->name->toBe(__('privacy.anonymous'))->firstName->toBe(__('privacy.anonymous'))->lastName->toBe('')->club->toBeNull()->birthYear->toBeNull()
        ->and(ResultsPdf::html($event))->not->toContain('Hemlig')
        ->and(SfifExport::csv($event))->toContain('Hemlig');

    $this->get('/hogby-if/nattloppet-2026/start-list')->assertDontSee('Hemlig')->assertSee(__('privacy.anonymous'));
});

it('lets admins find a registration and hide or show the name', function () {
    [, $event, $class] = privacyEvent();
    $registration = finishedRunner($class, 1, ['first_name' => 'Hemlig', 'last_name' => 'Person']);
    $this->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    Volt::test('events.registrations', ['event' => $event])
        ->set('search', 'hemlig')->assertSee('Hemlig Person')
        ->call('toggleHidden', $registration->id);
    expect($registration->fresh()->hidden_at)->not->toBeNull();

    Volt::test('events.registrations', ['event' => $event])->call('toggleHidden', $registration->id);
    expect($registration->fresh()->hidden_at)->toBeNull();

    Volt::test('events.registrations', ['event' => $event])->call('toggleHidden', Registration::factory()->create()->id)->assertNotFound();
});

it('deletes contact details some months after the event but keeps names and results', function () {
    [, $event, $class] = privacyEvent();
    $old = finishedRunner($class, 1, ['email' => 'old@example.com', 'phone' => '070']);
    $this->travelTo(now()->setDate(2027, 9, 1));
    $recentEvent = Event::factory()->create(['date' => now()->subMonth()->toDateString()]);
    $recent = Registration::factory()->for(RaceClass::factory()->create(['event_id' => $recentEvent->id]))->create(['email' => 'new@example.com']);

    $this->artisan('registrations:prune-contacts')->assertSuccessful();

    expect($old->fresh())->email->toBeNull()->phone->toBeNull()->first_name->not->toBeNull()
        ->and($recent->fresh()->email)->toBe('new@example.com');
});
