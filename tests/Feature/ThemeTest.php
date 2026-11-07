<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Series;
use App\Models\User;
use App\Support\Theme;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

// #112: an organizer's theme, and an upplaga's on top of it, never below WCAG AA.
it('keeps every brand colour on the colour wheel readable: white text 4.5:1, a component on the ground 3:1, ink on the surface 4.5:1', function () {
    $hsl = function (float $h, float $s, float $l) {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (intdiv((int) $h, 60)) {
            0 => [$c, $x, 0], 1 => [$x, $c, 0], 2 => [0, $c, $x], 3 => [0, $x, $c], 4 => [$x, 0, $c], default => [$c, 0, $x]
        };

        return sprintf('#%02x%02x%02x', round(($r + $m) * 255), round(($g + $m) * 255), round(($b + $m) * 255));
    };

    $failures = [];
    for ($h = 0; $h < 360; $h += 10) {
        foreach ([0, 0.3, 0.6, 1] as $s) {
            foreach ([0.05, 0.2, 0.35, 0.5, 0.65, 0.8, 0.95] as $l) {
                $picked = $hsl($h, $s, $l);
                $brand = Theme::rgb(Theme::brand($picked)['color']);
                $surface = Theme::rgb(Theme::surface($picked));
                if (Theme::contrast($brand, [255, 255, 255]) < 4.5 || Theme::contrast($brand, Theme::rgb('#eef0ec')) < 3 || Theme::contrast($surface, Theme::rgb('#18201c')) < 4.5) {
                    $failures[] = $picked;
                }
            }
        }
    }

    expect($failures)->toBe([])
        ->and(Theme::brand(Theme::MOSS))->toBe(['color' => Theme::MOSS, 'adjusted' => false])
        ->and(Theme::brand('#ffd500')['adjusted'])->toBeTrue();
});

it('lets an organizer upload a logo and choose colours, fonts and shape, seen in the preview and published only on Publicera', function () {
    Storage::fake('public');
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if']);
    $this->actingAs(User::factory()->create(['organizer_id' => $organizer->id]));

    Volt::test('theme.edit')
        // A 1×1 PNG: the test container has no GD to draw a bigger one.
        ->set('upload', UploadedFile::fake()->createWithContent('logga.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')))
        ->set('brand', '#FFD500')
        ->assertSee(__('theme.adjusted', ['colour' => Theme::brand('#ffd500')['color']]))
        ->set('fonts', 'sport')->set('shape', 'straight')
        ->set('brand', 'gul')->assertHasErrors(['brand'])->set('brand', '#ffd500')
        ->assertSee(__('theme.unpublished'));

    $draft = $organizer->fresh()->theme_draft;
    expect($draft)->toMatchArray(['brand' => '#ffd500', 'fonts' => 'sport', 'shape' => 'straight'])->and($organizer->fresh()->theme)->toBeNull();
    Storage::disk('public')->assertExists($draft['logo']);

    // The public page is unchanged until published; the signed preview shows the draft.
    $this->get('/hogby-if')->assertDontSee('Barlow Condensed')->assertDontSee($draft['logo']);
    $this->get(URL::signedRoute('public.organizer', ['organizer' => 'hogby-if', 'forhandsvisning' => 1]))
        ->assertSee('--brand:'.Theme::brand('#ffd500')['color'], false)->assertSee('Barlow Condensed')->assertSee('--radius-pill:4px', false);

    Volt::test('theme.edit')->call('publish');
    expect($organizer->fresh())->theme->toMatchArray(['brand' => '#ffd500', 'fonts' => 'sport'])->theme_draft->toBeNull();
    $this->get('/hogby-if')->assertSee('Barlow Condensed')->assertSee($draft['logo']);
});

it('gives an upplaga its own theme that inherits what it does not change', function () {
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if', 'theme' => ['brand' => '#1f5c46', 'fonts' => 'classic', 'shape' => 'rounded']]);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'slug' => 'sylvesterloppet-2026', 'date' => '2026-12-31']);
    $this->actingAs(User::factory()->create(['organizer_id' => $organizer->id]));

    Volt::test('theme.edit', ['event' => $event])->assertSet('fonts', '')->set('brand', '#1b2a6b')->call('publish');

    expect(Theme::for($organizer, $event->fresh()))->toMatchArray(['brand' => '#1b2a6b', 'fonts' => 'classic', 'shape' => 'rounded'])
        ->and(Theme::for($organizer))->toMatchArray(['brand' => '#1f5c46']);
    $this->get('/hogby-if/sylvesterloppet-2026')->assertOk()->assertSee('--brand:#1b2a6b', false)->assertSee('Source Serif 4');
});

it('leaves a page without a theme as Löpning.se made it', function () {
    Organizer::factory()->create(['slug' => 'hogby-if']);

    expect(Theme::css(Theme::for(Organizer::first())))->toBe('');
    $this->get('/hogby-if')->assertOk()->assertDontSee('--brand:', false);
});

it('takes an SVG logo, keeping its drawing but not its scripts, events or outside references', function () {
    Storage::fake('public');
    $organizer = Organizer::factory()->create();
    $this->actingAs(User::factory()->create(['organizer_id' => $organizer->id]));
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10"><script>alert(1)</script>'
        .'<circle cx="5" cy="5" r="4" fill="#1f5c46" onload="alert(2)"/><image xlink:href="https://spår.example/pixel.png"/>'
        .'<foreignObject><div>x</div></foreignObject></svg>';

    Volt::test('theme.edit')->set('upload', UploadedFile::fake()->createWithContent('logga.svg', $svg))->assertHasNoErrors();

    $stored = Storage::disk('public')->get($organizer->fresh()->theme_draft['logo']);
    expect($stored)->toContain('<circle')->not->toContain('script')->not->toContain('onload')->not->toContain('spår.example')->not->toContain('foreignObject');

    Volt::test('theme.edit')->set('upload', UploadedFile::fake()->createWithContent('trasig.svg', '<svg xmlns="http://www.w3.org/2000/svg"><circle'))->assertHasErrors('upload');
});

it('keeps the theme chosen for an upplaga of an arrangemang for its other years', function () {
    $this->travelTo('2026-11-07 12:00');
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if', 'theme' => ['brand' => '#b60202']]);
    $series = Series::create(['organizer_id' => $organizer->id, 'name' => 'Sylvesterloppet']);
    $this->actingAs(User::factory()->create(['organizer_id' => $organizer->id]));
    $now = $series->addEdition('2026-12-31');

    Volt::test('theme.edit', ['event' => $now])->assertSee(__('theme.series_help'))->set('brand', '#1b2a6b')->call('publish');
    $next = $series->addEdition('2027-12-31');

    expect($series->fresh()->theme)->toMatchArray(['brand' => '#1b2a6b'])->and($now->fresh()->theme)->toBeNull()
        ->and(Theme::for($organizer, $next))->toMatchArray(['brand' => '#1b2a6b'])
        ->and(Theme::for($organizer))->toMatchArray(['brand' => '#b60202']);
});
