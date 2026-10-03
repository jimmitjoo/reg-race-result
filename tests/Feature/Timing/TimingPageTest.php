<?php

use App\Models\ChipRead;
use App\Models\Event;
use App\Models\User;

it('shows the timing page to signed in users', function () {
    $event = Event::factory()->create(['name' => 'Ekerumsloppet 2026']);

    $this->actingAs(User::factory()->create())
        ->get(route('timing.show', $event))
        ->assertOk()
        ->assertSee('Ekerumsloppet 2026')
        ->assertSee(__('timing.choose_folder'));
});

it('requires sign in', function () {
    $this->get(route('timing.show', Event::factory()->create()))->assertRedirect(route('login'));
    $this->postJson(route('timing.reads.store', Event::factory()->create()), [])->assertUnauthorized();
});

it('imports lines sent by the browser and tells how many were new', function () {
    $event = Event::factory()->create();
    $lines = "704\t2024-12-31 11:09:58.877\t1\t4\r\n318\t2024-12-31 11:09:59.626\t1\t4\r\n";

    $this->actingAs(User::factory()->create())
        ->postJson(route('timing.reads.store', $event), ['reader' => '241', 'lines' => $lines])
        ->assertOk()
        ->assertJson(['inserted' => 2, 'duplicates' => 0, 'rejected' => []]);

    $this->postJson(route('timing.reads.store', $event), ['reader' => '241', 'lines' => $lines])
        ->assertJson(['inserted' => 0, 'duplicates' => 2]);

    expect(ChipRead::count())->toBe(2);
});

it('accepts an empty batch', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('timing.reads.store', Event::factory()->create()), ['reader' => '240', 'lines' => ''])
        ->assertOk()
        ->assertJson(['inserted' => 0]);
});

it('validates the reader name', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('timing.reads.store', Event::factory()->create()), ['reader' => '../etc', 'lines' => ''])
        ->assertUnprocessable();
});

it('has every timing translation in Swedish and English', function () {
    $sv = require lang_path('sv/timing.php');
    $en = require lang_path('en/timing.php');

    expect(array_keys($sv))->toEqualCanonicalizing(array_keys($en));
});
