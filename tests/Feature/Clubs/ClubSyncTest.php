<?php

use App\Models\Club;
use Illuminate\Support\Facades\Http;

function sfifClubsResponse(array $clubs): string
{
    return "\u{FEFF}".json_encode(['clubs' => $clubs]);
}

it('syncs the federation club list', function () {
    Http::fake(['tilastopaja.com/*' => Http::response(sfifClubsResponse([
        ['id' => '506', 'club' => 'Högby IF', 'district' => 'Småland', 'region' => 'Götaland', 'kommun' => 'Borgholm', 'idrottonline' => 'Open', 'athletes' => '400', 'country' => 'SWE'],
        ['id' => '1308', 'club' => 'Oskarshamn RC', 'district' => 'Småland', 'region' => 'Götaland', 'kommun' => '', 'idrottonline' => 'Not Open', 'athletes' => '36', 'country' => 'SWE'],
    ]))]);

    $this->artisan('clubs:sync')->assertSuccessful();

    expect(Club::count())->toBe(2)
        ->and(Club::where('external_id', '506')->sole())
        ->name->toBe('Högby IF')->district->toBe('Småland')->municipality->toBe('Borgholm')->federation->toBe('SFIF');
});

it('updates renamed clubs instead of duplicating them', function () {
    Club::create(['federation' => 'SFIF', 'external_id' => '506', 'name' => 'Högby Idrottsförening', 'district' => 'Småland']);
    Http::fake(['tilastopaja.com/*' => Http::response(sfifClubsResponse([['id' => '506', 'club' => 'Högby IF', 'district' => 'Småland', 'kommun' => 'Borgholm']]))]);

    $this->artisan('clubs:sync')->assertSuccessful();

    expect(Club::sole()->name)->toBe('Högby IF');
});

it('keeps the existing list when the federation API fails', function () {
    Club::create(['federation' => 'SFIF', 'external_id' => '506', 'name' => 'Högby IF']);
    Http::fake(['tilastopaja.com/*' => Http::response('', 500)]);

    $this->artisan('clubs:sync')->assertFailed();

    expect(Club::count())->toBe(1);
});

it('matches typed club names to the official name', function (string $typed, ?string $expected) {
    Club::create(['federation' => 'SFIF', 'external_id' => '506', 'name' => 'Högby IF']);

    expect(Club::match($typed)?->name)->toBe($expected);
})->with([
    ['Högby IF', 'Högby IF'],
    ['högby if ', 'Högby IF'],
    ['  HÖGBY  IF', 'Högby IF'],
    ['Kalmar', null],
    ['', null],
]);
