<?php

use App\Timing\RfidServerParser;

it('parses a line into a read in the given timezone', function () {
    $result = RfidServerParser::parse("704\t2024-12-31 11:09:58.877\t1\t4\r\n", reader: '241', timezone: 'Europe/Stockholm');

    expect($result->reads)->toHaveCount(1);
    $read = $result->reads[0];
    expect($read->chip)->toBe('704')
        ->and($read->reader)->toBe('241')
        ->and($read->unit)->toBe(1)
        ->and($read->antenna)->toBe(4)
        ->and($read->readAt->format('Y-m-d H:i:s.v P'))->toBe('2024-12-31 11:09:58.877 +01:00');
});

it('accepts CR CR LF, CR LF and LF line endings', function (string $eol) {
    $contents = "1\t2024-08-23 21:23:50.123\t1\t1{$eol}2\t2024-08-23 21:23:51.000\t2\t3{$eol}";

    expect(RfidServerParser::parse($contents, '241', 'Europe/Stockholm')->reads)->toHaveCount(2);
})->with(["\r\r\n", "\r\n", "\n"]);

it('rejects malformed lines instead of dropping them silently', function () {
    $result = RfidServerParser::parse("garbage\r\n5\t2024-08-23 21:23:50.123\t1\t1\r\n", '240', 'Europe/Stockholm');

    expect($result->reads)->toHaveCount(1)
        ->and($result->rejected)->toBe(['garbage']);
});

it('parses real RFIDServer files', function (string $file, int $count) {
    $result = RfidServerParser::parse(file_get_contents(__DIR__."/../../Fixtures/rfid/{$file}"), '241', 'Europe/Stockholm');

    expect($result->reads)->toHaveCount($count)
        ->and($result->rejected)->toBe([]);
})->with([
    ['sylvesterloppet-2024-192.168.1.241.txt', 3634],
    ['hagbloms-nattloppet-2024-192.168.1.241.txt', 5052],
]);
