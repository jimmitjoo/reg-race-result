<?php

use Symfony\Component\Finder\Finder;

// #111: views take colours from the design system's tokens (resources/css/app.css), never hex values or other palettes.
// PDFs and mails cannot read CSS variables, so they keep their own.
it('uses only the design tokens for colour in views', function () {
    $palettes = 'red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|slate|stone';
    $utilities = 'bg|text|border|ring|ring-offset|fill|stroke|from|via|to|outline|divide|decoration|shadow|accent|caret|placeholder';
    $found = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->exclude(['pdf', 'mail', 'vendor'])->name('*.blade.php') as $file) {
        foreach (explode("\n", $file->getContents()) as $number => $line) {
            // Comments name issues (#118), not colours.
            if (str_contains($line, 'theme-color') || preg_match('~^\s*(//|\*|/\*|\{\{--)~', $line)) {
                continue;
            }
            if (preg_match("/(?<![\\w-])(?:{$utilities})-(?:{$palettes})-\\d{2,3}\\b/", $line, $m) || preg_match('/(?<![&\w])#(?:[0-9a-fA-F]{3}){1,2}\b/', $line, $m)) {
                $found[] = "{$file->getRelativePathname()}:".($number + 1).": {$m[0]}";
            }
        }
    }

    expect($found)->toBe([]);
});
