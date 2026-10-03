<?php

use App\Results\Districts;

it('expands the nine districts since 2022 to the old names in the federation club list', function () {
    expect(Districts::expand(['Östsvenska']))->toBe(['Blekinge', 'Småland', 'Östergötland'])
        ->and(Districts::expand(['Gotland-Stockholm', 'Skåne']))->toBe(['Gotland', 'Stockholm', 'Skåne'])
        ->and(Districts::expand(['Västsvenska']))->toContain('Bohuslän-Dal', 'Halland', 'Västergötland');
});

it('keeps old district names as they are', function () {
    expect(Districts::expand(['Småland']))->toBe(['Småland']);
});

it('lists the nine districts', function () {
    expect(Districts::names())->toHaveCount(9)->toContain('Östsvenska', 'Norra Norrland', 'Göteborg');
});
