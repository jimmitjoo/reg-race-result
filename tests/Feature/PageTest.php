<?php

use App\Models\Organizer;
use App\Models\Series;
use App\Models\User;
use App\Support\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

// #115, #116: the organizer's page as blocks, edited as a draft and published.
function pageOrganizer(): Organizer
{
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if', 'name' => 'Högby IF']);
    test()->actingAs(User::factory()->create(['organizer_id' => $organizer->id]));

    return $organizer;
}

it('gives a page nobody has edited the club in its colour, the next race and earlier years', function () {
    $this->travelTo('2026-11-07 12:00');
    $organizer = pageOrganizer();
    $series = Series::create(['organizer_id' => $organizer->id, 'name' => 'Sylvesterloppet']);
    $series->addEdition('2026-12-31', ['city' => 'Kalmar']);
    $series->addEdition('2025-12-31');

    $html = $this->get('/hogby-if')->assertOk()
        ->assertSeeInOrder(['Högby IF', __('page.next'), 'Sylvesterloppet 2026', __('registration.cta'), __('public.upcoming'), __('public.earlier'), '2025'])
        ->getContent();
    expect(substr_count($html, '<h1'))->toBe(1);
});

it('keeps changes as a draft, seen in the preview, until Publicera', function () {
    $organizer = pageOrganizer();

    $editor = Volt::test('page.edit')->call('add', 'text', 1);
    $id = $editor->get('blocks')[1]['id'];
    $editor->set('blocks.1.heading', 'Om klubben')->set('blocks.1.body', 'Vi springer **varje** tisdag.')
        ->assertSet('editing', $id)->assertSee(__('page.draft'));

    $this->get('/hogby-if')->assertDontSee('Om klubben');
    $this->get(URL::signedRoute('public.organizer', ['organizer' => 'hogby-if', 'forhandsvisning' => 1]))
        ->assertSee('Om klubben')->assertSee('<strong>varje</strong>', false);

    $editor->call('publish');
    expect($organizer->fresh())->page_draft->toBeNull()->page->toHaveCount(4);
    $this->get('/hogby-if')->assertSeeInOrder(['Högby IF', 'Om klubben', __('public.upcoming')]);
});

it('moves blocks by dragging and with the arrows, and takes a removed block back on Ångra', function () {
    pageOrganizer();
    $editor = Volt::test('page.edit');
    $types = fn () => array_column($editor->get('blocks'), 'type');
    [$hero, $upcoming, $earlier] = array_column($editor->get('blocks'), 'id');

    $editor->call('sortBlock', $earlier, 0);
    expect($types())->toBe(['earlier', 'hero', 'upcoming']);
    $editor->call('move', $earlier, 1);
    expect($types())->toBe(['hero', 'earlier', 'upcoming']);

    $editor->call('remove', $hero);
    expect($types())->toBe(['earlier', 'upcoming']);
    $editor->assertSee(__('page.undo'))->call('undo');
    expect($types())->toBe(['hero', 'earlier', 'upcoming']);
});

it('keeps one h1 and block headings as h2 in any order, a text\'s own headings as h3, and no HTML', function () {
    $organizer = pageOrganizer();
    $text = array_merge(Page::block('text'), ['heading' => 'Om klubben', 'body' => "# Historia\n\n<script>alert(1)</script>[länk](javascript:alert(1))"]);
    $organizer->update(['page' => [$text, Page::block('upcoming'), Page::block('hero')]]);

    $html = $this->get('/hogby-if')->getContent();
    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('<h2 class="text-[26px] leading-[32px] font-medium sm:text-[32px] sm:leading-[38px]">Om klubben</h2>')
        ->toContain('<h3>Historia</h3>')->not->toContain('<script>alert')->not->toContain('javascript:');

    // Without the hero the name is still the h1, for screen readers.
    $organizer->update(['page' => [$text]]);
    $this->get('/hogby-if')->assertSee('<h1 class="sr-only">Högby IF</h1>', false);
});

it('will not publish an image without alt text, an empty block or a link that says "klicka här"', function () {
    Storage::fake('public');
    $organizer = pageOrganizer();
    $editor = Volt::test('page.edit')->call('add', 'image', 0)
        ->set('upload', UploadedFile::fake()->createWithContent('lopp.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')))
        ->call('add', 'text', 0)->set('blocks.0.body', 'Anmälan finns [klicka här](https://example.com).');

    expect(array_column(Page::problems($editor->get('blocks')), 'problem'))->toBe(['link_text', 'alt']);
    $editor->assertSee(__('page.problem.alt'))->call('publish');
    expect($organizer->fresh()->page)->toBeNull();

    $editor->set('blocks.0.body', 'Läs [om anmälan](https://example.com).')->set('blocks.1.alt', 'Löpare i snön vid start')->call('publish');
    expect($organizer->fresh()->page[1])->toMatchArray(['alt' => 'Löpare i snön vid start', 'width' => 1, 'height' => 1]);
    $this->get('/hogby-if')->assertSee('alt="Löpare i snön vid start"', false);
});

it('takes text written straight into a block, the text\'s HTML cleaned to what a text may hold', function () {
    $organizer = pageOrganizer();
    $editor = Volt::test('page.edit')->call('add', 'text', 1);
    $id = $editor->get('blocks')[1]['id'];

    $editor->call('setField', $id, 'heading', 'Om klubben')
        ->call('setField', $id, 'body', '<h2>Historia</h2><p onclick="x()">Grundad <strong>1932</strong> <a href="javascript:alert(1)">x</a> <a href="https://hogbyif.se" target="_blank">vår sajt</a></p><script>alert(1)</script><img src=x onerror=alert(1)>');

    expect($organizer->fresh()->page_draft[1])->toMatchArray([
        'heading' => 'Om klubben',
        'body' => '<h3>Historia</h3><p>Grundad <strong>1932</strong> <a>x</a> <a href="https://hogbyif.se">vår sajt</a></p>',
    ]);

    // Only the fields written on the page, never the image or the type.
    $editor->call('setField', $id, 'type', 'hero')->assertNotFound();
});

it('draws the hero in the look picked, the full ones to the window\'s edges, and asks a picture of those that need one', function () {
    $organizer = pageOrganizer();
    $hero = fn (string $variant, ?string $image = null) => array_merge(Page::block('hero'), ['variant' => $variant, 'image' => $image, 'alt' => 'Start i snön']);

    foreach (Page::HEROES as $variant) {
        $organizer->update(['page' => [$hero($variant, 'pages/start.jpg')]]);
        $html = $this->get('/hogby-if')->assertOk()->getContent();
        expect(substr_count($html, '<h1'))->toBe(1)
            ->and(str_contains($html, 'class="bleed'))->toBe(in_array($variant, Page::FULL_HEROES, true));
    }

    // Without its picture a picture hero is the colour band, and will not be published so.
    $organizer->update(['page' => [$hero('image-full')]]);
    expect($this->get('/hogby-if')->getContent())->toContain('class="bleed -mt-8 sm:-mt-12 bg-brand')
        ->and(array_column(Page::problems([$hero('image-full')]), 'problem'))->toBe(['no_image'])
        ->and(Page::problems([$hero('brand-full')]))->toBe([]);
});

it('puts nothing of Löpning.se above the organizer\'s page, only a quiet line at its foot', function () {
    pageOrganizer();

    $html = $this->get('/hogby-if')->getContent();
    expect($html)->not->toContain('<header')
        ->and(strpos($html, __('public.powered_by')))->toBeGreaterThan(strpos($html, '</main>'));
});

it('lets the hero say something else than the club\'s name, and says the name again when emptied', function () {
    $organizer = pageOrganizer();
    $editor = Volt::test('page.edit');
    $hero = $editor->get('blocks')[0]['id'];

    $editor->call('setField', $hero, 'title', 'Högbys arrangemang')->call('publish');
    $this->get('/hogby-if')->assertSee('Högbys arrangemang</h1>', false);

    $editor->call('setField', $hero, 'title', '')->call('publish');
    $this->get('/hogby-if')->assertSee('Högby IF</h1>', false);
});

it('shows as many races and years as chosen, as a list or cards, on the background chosen', function () {
    $this->travelTo('2026-11-07 12:00');
    $organizer = pageOrganizer();
    $series = Series::create(['organizer_id' => $organizer->id, 'name' => 'Sylvesterloppet']);
    foreach ([2026, 2025, 2024, 2023] as $year) {
        $series->addEdition("{$year}-12-31");
    }
    Series::create(['organizer_id' => $organizer->id, 'name' => 'Vårloppet'])->addEdition('2027-04-01');

    $organizer->update(['page' => [
        array_merge(Page::block('upcoming'), ['layout' => 'list', 'limit' => 1, 'background' => 'brand']),
        array_merge(Page::block('earlier'), ['years' => 2]),
    ]]);

    $this->get('/hogby-if')
        ->assertSee('Sylvesterloppet 2026')->assertDontSee('Vårloppet 2027')
        ->assertSee('on-brand bg-brand', false)
        ->assertSeeInOrder(['>2025<', '>2024<', trans_choice('page.more_years', 1), '>2023<'], false);

    $editor = Volt::test('page.edit');
    $editor->call('edit', $editor->get('blocks')[0]['id'])
        ->assertSee(__('page.layouts.feature'))->assertSee(__('page.backgrounds.brand'))->assertSee(__('page.limit_races'));
});

it('places blocks beside the one before them, up to four in a row, never beside the hero', function () {
    $block = fn (string $type, bool $beside = false) => array_merge(Page::block($type), ['beside' => $beside]);
    $blocks = [$block('hero'), $block('text', true), $block('image', true), $block('text'), $block('text', true), $block('text', true), $block('text', true), $block('text', true)];

    expect(array_values(Page::spans($blocks)))->toBe([12, 6, 6, 3, 3, 3, 3, 12]);

    $organizer = pageOrganizer();
    $organizer->update(['page' => [array_merge($block('text'), ['heading' => 'Om oss']), array_merge($block('contact', true), ['name' => 'Anna', 'background' => 'brand'])]]);
    $this->get('/hogby-if')->assertSee('col-span-12 lg:col-span-6', false)->assertSee('rounded-card p-6 shadow-rest sm:p-8', false);
});

it('writes questions and figures in place, folds the answers out on the page and tells search engines', function () {
    $organizer = pageOrganizer();
    $editor = Volt::test('page.edit')->call('add', 'faq', 1);
    $faq = $editor->get('blocks')[1];

    $editor->call('setItem', $faq['id'], $faq['items'][0]['id'], 'q', 'Finns det dusch?')
        ->assertSee(__('page.problem.empty'))
        ->call('setItem', $faq['id'], $faq['items'][0]['id'], 'a', "Ja, i sporthallen.\nTa med handduk.");
    $editor->call('addItem', $faq['id']);
    expect($editor->get('blocks')[1]['items'])->toHaveCount(2);
    $editor->call('removeItem', $faq['id'], $editor->get('blocks')[1]['items'][1]['id'])->call('publish');

    $this->get('/hogby-if')
        ->assertSee('<h3 class="text-[19px] font-medium">Finns det dusch?</h3>', false)
        ->assertSee('"@type":"FAQPage"', false)->assertSee('"name":"Finns det dusch?"', false);

    // Only the texts of an item, never its id.
    Volt::test('page.edit')->call('setItem', $faq['id'], $faq['items'][0]['id'], 'id', 'x')->assertNotFound();
});

it('puts the room above a block as chosen, a block beside another lined up with the first in its row', function () {
    $block = fn (string $type, array $with = []) => array_merge(Page::block($type), $with);
    $blocks = [$block('hero'), $block('text', ['space' => 'none']), $block('text', ['space' => 'large']), $block('text', ['beside' => true]), $block('facts', ['space' => 'small'])];

    expect(array_values(Page::spaces($blocks)))->toBe(['', '', 'mt-20 lg:mt-28', 'mt-6 lg:mt-28', 'mt-6 lg:mt-8']);
});

it('lays coming races and earlier years across the window as a band, their cards kept in the page\'s column', function () {
    $organizer = pageOrganizer();
    $organizer->update(['page' => [['background' => 'brand', 'size' => 'full'] + Page::block('upcoming'), ['size' => 'full'] + Page::block('earlier')]]);

    $this->get('/hogby-if')->assertOk()
        ->assertSee('on-brand bg-brand', false)
        ->assertSee('max-w-[calc(1100px+3rem)]', false)
        ->assertSee('mx-auto w-full max-w-[1100px]', false);
});
