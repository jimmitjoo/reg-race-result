<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\User;
use App\Support\Page;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

// An upplaga's page as blocks, like the organizer's (#115), each block shown always or in a state of the upplaga.
function pageEvent(): Event
{
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if', 'name' => 'Högby IF']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Sylvesterloppet 2026', 'slug' => 'sylvesterloppet-2026', 'date' => '2026-12-31', 'city' => 'Kalmar']);
    $race = Race::factory()->for($event)->create(['name' => 'Milen']);
    $race->priceSteps()->create(['until' => '2026-12-30', 'amount' => 0]);
    RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Kvinnor 10 km']);
    test()->actingAs(User::factory()->create(['organizer_id' => $organizer->id]));

    return $event;
}

it('gives an upplaga nobody has built a page its name, the way to register and the form, under one h1', function () {
    $this->travelTo('2026-11-07 12:00');
    pageEvent();

    $html = $this->get('/hogby-if/sylvesterloppet-2026')->assertOk()
        ->assertSeeInOrder(['Sylvesterloppet 2026', 'Kalmar', __('registration.cta'), __('registration.step_choose'), 'Kvinnor 10 km'])
        ->getContent();
    expect(substr_count($html, '<h1'))->toBe(1);
});

it('shows a block only while the upplaga is in the state chosen for it', function () {
    $event = pageEvent();
    $block = fn (string $heading, string $when) => ['heading' => $heading, 'body' => 'x', 'when' => $when] + Page::block('text');
    $event->update(['page' => [Page::block('hero'), Page::block('registration'),
        $block('Anmäl dig nu', 'open'), $block('Anmälan har stängt', 'closed'), $block('Inför loppet', 'before'), $block('Tack för i år', 'after'), $block('Resultaten är här', 'results')]]);

    $this->travelTo('2026-11-07 12:00');
    $this->get('/hogby-if/sylvesterloppet-2026')->assertSee('Anmäl dig nu')->assertSee('Inför loppet')
        ->assertDontSee('Anmälan har stängt')->assertDontSee('Tack för i år')->assertDontSee('Resultaten är här');

    $this->travelTo('2027-01-01 12:00');
    $event->update(['results_public_at' => now()]);
    $this->get('/hogby-if/sylvesterloppet-2026')->assertSee('Anmälan har stängt')->assertSee('Tack för i år')->assertSee('Resultaten är här')
        ->assertDontSee('Anmäl dig nu')->assertDontSee('Inför loppet')->assertDontSee(__('registration.cta'));
});

it('edits the upplaga\'s page as a draft of its own, with one registration at most and only an upplaga\'s blocks', function () {
    $this->travelTo('2026-11-07 12:00');
    $event = pageEvent();

    $editor = Volt::test('page.edit', ['event' => $event])
        ->assertSee(__('page.event_title_of', ['event' => 'Sylvesterloppet 2026']))
        ->call('add', 'faq', 3)->set('blocks.3.when', 'before');
    $editor->call('add', 'registration', 0)->assertNotFound();

    expect($event->fresh()->page_draft[3])->toMatchArray(['type' => 'faq', 'when' => 'before'])
        ->and($event->organizer->fresh()->page_draft)->toBeNull();
    $this->get(URL::signedRoute('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026', 'forhandsvisning' => 1]))->assertSee(__('page.faq_heading'));
    $this->get('/hogby-if/sylvesterloppet-2026')->assertDontSee(__('page.faq_heading'));

    Volt::test('page.edit', ['event' => $event])->call('add', 'upcoming', 1)->assertNotFound();
});

it('opens no other organizer\'s upplaga in the editor', function () {
    $event = pageEvent();
    $this->actingAs(User::factory()->create(['organizer_id' => Organizer::factory()->create()->id]));

    $this->get(route('events.page', $event))->assertNotFound();
});

it('tells the editor when the colours it shows are not published yet, and where to publish them', function () {
    $event = pageEvent();
    $event->update(['theme_draft' => ['brand' => '#0011ff']]);

    Volt::test('page.edit', ['event' => $event])->assertSee(__('page.theme_draft'))->assertSee(route('events.theme', $event));
    $event->update(['theme' => ['brand' => '#0011ff'], 'theme_draft' => null]);
    Volt::test('page.edit', ['event' => $event])->assertDontSee(__('page.theme_draft'));
});
