<?php

use App\Timing\ChipListParser;

it('detects the delimiter and a header row', function (string $contents) {
    $list = ChipListParser::parse($contents);

    expect($list->header)->toBe(['Startnummer', 'Chip'])
        ->and($list->rows)->toBe([['501', 'E200341201'], ['502', 'E200341202']]);
})->with([
    'semicolon' => ["Startnummer;Chip\r\n501;E200341201\r\n502;E200341202\r\n"],
    'tab' => ["Startnummer\tChip\n501\tE200341201\n502\tE200341202\n"],
    'comma' => ["Startnummer,Chip\n501,E200341201\n502,E200341202"],
]);

it('reads a list without header', function () {
    $list = ChipListParser::parse("501;E1\n502;E2\n");

    expect($list->header)->toBeNull()->and($list->rows)->toHaveCount(2);
});

it('guesses the chip and bib columns from the header', function (array $header, ?int $chip, ?int $bib) {
    $list = ChipListParser::parse(implode(';', $header)."\n1;2;3\n");

    expect($list->chipColumn)->toBe($chip)->and($list->bibColumn)->toBe($bib);
})->with([
    [['Nr', 'Namn', 'Chipnummer'], 2, 0],
    [['Tag', 'Bib', 'X'], 0, 1],
    [['EPC', 'Startnr', 'X'], 0, 1],
    [['A', 'B', 'C'], null, null],
]);

it('skips empty lines and strips a BOM', function () {
    $list = ChipListParser::parse("\u{FEFF}Nr;Chip\n\n501;E1\n \n");

    expect($list->header)->toBe(['Nr', 'Chip'])->and($list->rows)->toBe([['501', 'E1']]);
});
