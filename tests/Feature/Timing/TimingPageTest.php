<?php

use App\Models\ChipRead;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\UploadedFile;

it('shows the timing page to signed in users', function () {
    $event = Event::factory()->create(['name' => 'Ekerumsloppet 2026']);

    $this->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]))
        ->get(route('events.timing', $event))
        ->assertOk()
        ->assertSee('Ekerumsloppet 2026')
        ->assertSee(__('timing.choose_folder'));
});

it('requires sign in', function () {
    $this->get(route('events.timing', Event::factory()->create()))->assertRedirect(route('login'));
    $this->postJson(route('events.timing.reads', Event::factory()->create()), [])->assertUnauthorized();
});

it('imports lines sent by the browser and tells how many were new', function () {
    $event = Event::factory()->create();
    $lines = "704\t2024-12-31 11:09:58.877\t1\t4\r\n318\t2024-12-31 11:09:59.626\t1\t4\r\n";

    $this->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]))
        ->postJson(route('events.timing.reads', $event), ['reader' => '241', 'lines' => $lines])
        ->assertOk()
        ->assertJson(['inserted' => 2, 'duplicates' => 0, 'rejected' => []]);

    $this->postJson(route('events.timing.reads', $event), ['reader' => '241', 'lines' => $lines])
        ->assertJson(['inserted' => 0, 'duplicates' => 2]);

    expect(ChipRead::count())->toBe(2);
});

it('accepts an empty batch', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]))
        ->postJson(route('events.timing.reads', $event), ['reader' => '240', 'lines' => ''])
        ->assertOk()
        ->assertJson(['inserted' => 0]);
});

it('validates the reader name', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]))
        ->postJson(route('events.timing.reads', $event), ['reader' => '../etc', 'lines' => ''])
        ->assertUnprocessable();
});

it('has every timing translation in Swedish and English', function () {
    $sv = require lang_path('sv/timing.php');
    $en = require lang_path('en/timing.php');

    expect(array_keys($sv))->toEqualCanonicalizing(array_keys($en));
});

it('imports an uploaded RFIDServer file, naming the reader from the file name', function () {
    $event = Event::factory()->create();
    $this->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));
    $file = UploadedFile::fake()->createWithContent('192.168.1.241.txt', "704\t2024-12-31 11:09:58.877\t1\t4\r\n318\t2024-12-31 11:09:59.626\t1\t4\r\n");

    $this->post(route('events.timing.upload', $event), ['file' => $file])
        ->assertRedirect(route('events.timing', $event))
        ->assertSessionHas('upload', __('timing.uploaded', ['inserted' => 2, 'duplicates' => 0, 'reader' => '241']));

    expect(ChipRead::where('reader', '241')->count())->toBe(2);

    $this->post(route('events.timing.upload', $event), ['file' => UploadedFile::fake()->createWithContent('192.168.1.241.txt', "704\t2024-12-31 11:09:58.877\t1\t4\r\n")])
        ->assertSessionHas('upload', __('timing.uploaded', ['inserted' => 0, 'duplicates' => 1, 'reader' => '241']));
});

it('rejects a file that is not named after a reader', function () {
    $event = Event::factory()->create();
    $this->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    $this->post(route('events.timing.upload', $event), ['file' => UploadedFile::fake()->createWithContent('resultat.txt', '')])
        ->assertSessionHasErrors('file');
});
