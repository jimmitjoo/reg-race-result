<?php

it('has the same translation keys in Swedish and English', function (string $file) {
    $flatten = fn (array $a, string $prefix = '') => collect($a)->flatMap(fn ($v, $k) => is_array($v) ? [] : ["{$prefix}{$k}"])->all();

    expect($flatten(require lang_path("sv/{$file}")))->toEqualCanonicalizing($flatten(require lang_path("en/{$file}")));
})->with(array_values(array_diff(array_map('basename', glob(__DIR__.'/../../lang/en/*.php')), ['validation.php'])));

it('uses translation keys, never English sentences, in the views', function () {
    $offenders = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if (! str_ends_with($file, '.blade.php')) {
            continue;
        }
        preg_match_all("/__\\('([^']+)'/", file_get_contents($file), $matches);
        foreach ($matches[1] as $key) {
            if (! preg_match('/^[a-z_]+\\.[a-z_.]+$/', $key) && ! preg_match('/^[a-z_]+$/', $key)) {
                $offenders[] = basename($file).": {$key}";
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('resolves every key used in the views', function () {
    app()->setLocale('sv');
    $missing = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if (! str_ends_with($file, '.blade.php')) {
            continue;
        }
        preg_match_all("/__\\('([a-z_]+\\.[a-z_]+)'[),]/", file_get_contents($file), $matches);
        foreach ($matches[1] as $key) {
            if (! trans()->has($key)) {
                $missing[] = $key;
            }
        }
    }

    expect(array_unique($missing))->toBe([]);
});
