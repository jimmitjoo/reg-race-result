<?php

use App\Timing\Entry;
use App\Timing\Read;
use App\Timing\ResultCalculator;
use App\Timing\ResultStatus;
use App\Timing\RfidServerParser;
use Carbon\CarbonImmutable;

const TZ = 'Europe/Stockholm';

function at(string $time): CarbonImmutable
{
    return CarbonImmutable::createFromFormat('Y-m-d H:i:s.v', "2026-10-03 {$time}", TZ);
}

function read(string $chip, string $time, string $reader = '240'): Read
{
    return new Read($chip, at($time), $reader, 1, 1);
}

function entry(string $bib, ?string $chip = null, string $start = '10:00:00.000', bool $dns = false, int $minTime = 60): Entry
{
    return new Entry($bib, $chip ?? $bib, at($start), $minTime, $dns);
}

it('times a runner from the start time to the first read, rounded up to whole seconds', function () {
    $result = ResultCalculator::calculate(
        [read('7', '10:18:00.001'), read('7', '10:18:01.500')],
        [entry('7')])->results['7'];

    expect($result->status)->toBe(ResultStatus::Finished)
        ->and($result->elapsedSeconds)->toBe(1081)
        ->and($result->finishAt->format('H:i:s.v'))->toBe('10:18:00.001');
});

it('does not round up a read on a whole second', function () {
    expect(ResultCalculator::calculate([read('7', '10:18:00.000')], [entry('7')])->results['7']->elapsedSeconds)
        ->toBe(1080);
});

it('ignores reads before the minimum time', function () {
    $result = ResultCalculator::calculate(
        [read('7', '10:00:05.000'), read('7', '10:20:00.000')],
        [entry('7')])->results['7'];

    expect($result->elapsedSeconds)->toBe(1200);
});

it('picks the earliest read even when lines are out of order and from both readers', function () {
    $result = ResultCalculator::calculate(
        [read('7', '10:20:02.000', '241'), read('7', '10:20:00.400', '240'), read('7', '10:20:01.000', '241')],
        [entry('7')])->results['7'];

    expect($result->elapsedSeconds)->toBe(1201);
});

it('maps chips to bib numbers', function () {
    $calculation = ResultCalculator::calculate([read('900123', '10:20:00.000')], [entry('42', chip: '900123')]);

    expect($calculation->results['42']->elapsedSeconds)->toBe(1200);
});

it('times each class from its own start time', function () {
    $calculation = ResultCalculator::calculate(
        [read('1', '10:20:00.000'), read('2', '10:20:00.000')],
        [entry('1'), entry('2', start: '10:03:30.000')]);

    expect($calculation->results['1']->elapsedSeconds)->toBe(1200)
        ->and($calculation->results['2']->elapsedSeconds)->toBe(990);
});

it('marks entries without a read as missing', function () {
    expect(ResultCalculator::calculate([], [entry('7')])->results['7']->status)->toBe(ResultStatus::Missing);
});

it('ignores reads from DNS entries', function () {
    $result = ResultCalculator::calculate([read('7', '10:20:00.000')], [entry('7', dns: true)])->results['7'];

    expect($result->status)->toBe(ResultStatus::Dns)
        ->and($result->elapsedSeconds)->toBeNull();
});

it('reports chips that belong to no entry', function () {
    expect(ResultCalculator::calculate([read('3841588197', '10:20:00.000')], [])->unknownChips)
        ->toBe(['3841588197']);
});

it('flags chips read continuously across the minimum time and gives them no time', function () {
    $reads = array_map(fn ($second) => read('704', '10:00:00.000')->readAt->addSeconds($second), range(30, 90, 10));
    $reads = array_map(fn ($readAt) => new Read('704', $readAt, '240', 1, 1), $reads);
    $calculation = ResultCalculator::calculate($reads, [entry('704')]);

    expect($calculation->continuousChips)->toBe(['704'])
        ->and($calculation->results['704']->status)->toBe(ResultStatus::Missing);
});

it('does not flag a runner who stays on the mats after finishing', function () {
    $reads = array_map(fn ($second) => new Read('7', at('10:20:00.000')->addSeconds($second), '240', 1, 1), range(0, 600, 5));

    $calculation = ResultCalculator::calculate($reads, [entry('7')]);

    expect($calculation->continuousChips)->toBe([])
        ->and($calculation->results['7']->elapsedSeconds)->toBe(1200);
});

it('does not flag a runner read twice', function () {
    $calculation = ResultCalculator::calculate(
        [read('7', '10:10:00.000'), read('7', '10:20:00.000')],
        [entry('7')]);

    expect($calculation->continuousChips)->toBe([])
        ->and($calculation->results['7']->elapsedSeconds)->toBe(600);
});

it('reproduces published results from Hagbloms Nattloppet 2024', function () {
    $file = file_get_contents(__DIR__.'/../../Fixtures/rfid/hagbloms-nattloppet-2024-192.168.1.241.txt');
    $reads = RfidServerParser::parse($file, '241', TZ)->reads;
    $start = CarbonImmutable::create(2024, 8, 23, 22, 6, 0, TZ);
    $entries = array_map(fn ($bib) => new Entry((string) $bib, (string) $bib, $start, 120), ['367', '57', '307']);

    $results = ResultCalculator::calculate($reads, $entries)->results;

    expect($results['367']->elapsedSeconds)->toBe(18 * 60)
        ->and($results['57']->elapsedSeconds)->toBe(18 * 60 + 15)
        ->and($results['307']->elapsedSeconds)->toBe(18 * 60 + 21);
});

it('flags only chips lying by the mats', function (string $file, string $start, int $minTime, array $expected) {
    $reads = RfidServerParser::parse(file_get_contents(__DIR__."/../../Fixtures/rfid/{$file}"), '241', TZ)->reads;
    $startAt = CarbonImmutable::parse($start, TZ);
    $entries = array_map(fn ($chip) => new Entry((string) $chip, (string) $chip, $startAt, $minTime), array_unique(array_map(fn (Read $r) => $r->chip, $reads)));

    expect(ResultCalculator::calculate($reads, array_values($entries))->continuousChips)->toBe($expected);
})->with([
    'Sylvesterloppet 2024: two bibs by the finish' => ['sylvesterloppet-2024-192.168.1.241.txt', '2024-12-31 11:00:00', 720, ['318', '704']],
    'Hagbloms Nattloppet 2024: runners on the mats before the start and after finishing' => ['hagbloms-nattloppet-2024-192.168.1.241.txt', '2024-08-23 22:06:00', 720, []],
]);

it('does not count reads before the start as lying by the mats', function () {
    $reads = array_map(fn ($minute) => read('7', sprintf('09:%02d:00.000', $minute)), range(30, 59));
    $reads[] = read('7', '10:20:00.000');

    $calculation = ResultCalculator::calculate($reads, [entry('7')]);

    expect($calculation->continuousChips)->toBe([])
        ->and($calculation->results['7']->elapsedSeconds)->toBe(1200);
});

it('uses the minimum time of each entry', function () {
    $calculation = ResultCalculator::calculate(
        [read('5', '10:10:00.000'), read('10', '10:10:00.000'), read('10', '10:40:00.000')],
        [entry('5', minTime: 300), entry('10', minTime: 1200)],
    );

    expect($calculation->results['5']->elapsedSeconds)->toBe(600)
        ->and($calculation->results['10']->elapsedSeconds)->toBe(2400);
});
