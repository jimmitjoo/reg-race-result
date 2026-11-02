<?php

use App\Models\Event;
use App\Models\EventPublication;
use App\Models\Organizer;
use App\Models\User;
use App\Models\WordPressSite;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function wpEvent(): array
{
    $event = Event::factory()->create(['name' => 'Hagbloms Nattloppet 2026']);
    $site = WordPressSite::create([
        'organizer_id' => $event->organizer_id, 'name' => 'friidrott.hogbyif.se', 'url' => 'https://friidrott.hogbyif.se',
        'username' => 'resultat', 'app_password' => 'abcd efgh ijkl mnop', 'results_parent_page_id' => 42,
    ]);
    test()->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    return [$event, $site];
}

function fakeWordPress(): void
{
    Http::fake([
        '*/wp-json/wp/v2/media' => Http::response(['id' => 900, 'source_url' => 'https://friidrott.hogbyif.se/wp-content/uploads/2026/08/nattloppet.pdf'], 201),
        '*/wp-json/wp/v2/pages' => Http::response(['id' => 77, 'link' => 'https://friidrott.hogbyif.se/tavlingsresultat/hagbloms-nattloppet-2026/'], 201),
        '*/wp-json/wp/v2/pages/77' => Http::response(['id' => 77, 'link' => 'https://friidrott.hogbyif.se/tavlingsresultat/hagbloms-nattloppet-2026/'], 200),
        '*/wp-json/wp/v2/media/*' => Http::response(['deleted' => true], 200),
    ]);
}

it('stores the application password encrypted', function () {
    [, $site] = wpEvent();

    expect(DB::table('wordpress_sites')->value('app_password'))->not->toContain('abcd')
        ->and($site->fresh()->app_password)->toBe('abcd efgh ijkl mnop');
});

it('uploads the result PDF and creates a results page under the parent page', function () {
    [$event, $site] = wpEvent();
    fakeWordPress();

    Volt::test('events.results', ['event' => $event])->call('publish', $site->id)->assertHasNoErrors();

    Http::assertSent(fn (Request $r) => $r->url() === 'https://friidrott.hogbyif.se/wp-json/wp/v2/media'
        && $r->hasHeader('Content-Type', 'application/pdf')
        && str_contains($r->header('Content-Disposition')[0], 'Hagbloms Nattloppet 2026')
        && $r->header('Authorization')[0] === 'Basic '.base64_encode('resultat:abcd efgh ijkl mnop')
        && str_starts_with($r->body(), '%PDF'));
    Http::assertSent(fn (Request $r) => $r->url() === 'https://friidrott.hogbyif.se/wp-json/wp/v2/pages'
        && $r['parent'] === 42 && $r['status'] === 'publish' && $r['title'] === 'Hagbloms Nattloppet 2026'
        && str_contains($r['content'], 'nattloppet.pdf'));

    expect(EventPublication::sole())
        ->media_url->toBe('https://friidrott.hogbyif.se/wp-content/uploads/2026/08/nattloppet.pdf')
        ->page_id->toBe(77)
        ->page_url->toBe('https://friidrott.hogbyif.se/tavlingsresultat/hagbloms-nattloppet-2026/');
});

it('updates the same page and replaces the old PDF when publishing again', function () {
    [$event, $site] = wpEvent();
    Http::fake([
        '*/wp-json/wp/v2/media' => Http::sequence()
            ->push(['id' => 900, 'source_url' => 'https://friidrott.hogbyif.se/a.pdf'], 201)
            ->push(['id' => 901, 'source_url' => 'https://friidrott.hogbyif.se/b.pdf'], 201),
        '*/wp-json/wp/v2/pages' => Http::response(['id' => 77, 'link' => 'https://friidrott.hogbyif.se/p/'], 201),
        '*/wp-json/wp/v2/pages/77' => Http::response(['id' => 77, 'link' => 'https://friidrott.hogbyif.se/p/'], 200),
        '*/wp-json/wp/v2/media/900*' => Http::response(['deleted' => true], 200),
    ]);
    $page = Volt::test('events.results', ['event' => $event]);

    $page->call('publish', $site->id);
    $page->call('publish', $site->id);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://friidrott.hogbyif.se/wp-json/wp/v2/pages/77');
    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/media/900'));
    expect(EventPublication::sole()->media_url)->toBe('https://friidrott.hogbyif.se/b.pdf');
});

it('only uploads the PDF for a site without a results parent page', function () {
    [$event, $site] = wpEvent();
    $site->update(['results_parent_page_id' => null]);
    fakeWordPress();

    Volt::test('events.results', ['event' => $event])->call('publish', $site->id);

    Http::assertSentCount(1);
    expect(EventPublication::sole())->page_id->toBeNull()->media_url->not->toBeNull();
});

it('tells when WordPress refuses', function () {
    [$event, $site] = wpEvent();
    Http::fake(['*' => Http::response(['code' => 'rest_cannot_create', 'message' => 'Sorry, you are not allowed'], 401)]);

    Volt::test('events.results', ['event' => $event])->call('publish', $site->id)->assertHasErrors(['publish']);

    expect(EventPublication::count())->toBe(0);
});

it('manages the sites of the organizer and tests the connection', function () {
    [$event] = wpEvent();
    Http::fake(['*/wp-json/wp/v2/users/me' => Http::response(['id' => 1, 'name' => 'resultat'])]);

    Volt::test('sites.index')
        ->set('name', 'www.irunkalmar.com')->set('url', 'https://www.irunkalmar.com/')->set('username', 'admin')->set('appPassword', 'xxxx yyyy')
        ->call('add')
        ->assertHasNoErrors();

    $site = WordPressSite::where('name', 'www.irunkalmar.com')->sole();
    expect($site)->url->toBe('https://www.irunkalmar.com')->organizer_id->toBe($event->organizer_id);

    Volt::test('sites.index')->call('test', $site->id)->assertSee(__('sites.connection_ok'));
    Volt::test('sites.index')->call('remove', WordPressSite::create(['organizer_id' => Organizer::factory()->create()->id, 'name' => 'x', 'url' => 'https://x', 'username' => 'u', 'app_password' => 'p'])->id)->assertNotFound();
});
