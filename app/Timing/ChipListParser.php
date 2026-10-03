<?php

namespace App\Timing;

/**
 * Reads a chip list (chip ↔ bib) from the bib supplier or a sheet, whatever its exact format:
 * tab, semicolon or comma separated, with or without a header. The columns are guessed from
 * the header and confirmed by a person before importing.
 */
final class ChipListParser
{
    private const CHIP_WORDS = ['chip', 'tag', 'epc', 'transponder', 'rfid'];

    private const BIB_WORDS = ['nr', 'no', 'bib', 'number', 'nummer', 'startnr', 'startnummer', 'nummerlapp'];

    public static function parse(string $contents): ChipList
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', ltrim($contents, "\u{FEFF}"))),
            fn (string $line) => $line !== '',
        ));

        if ($lines === []) {
            return new ChipList(null, [], null, null);
        }

        $delimiter = self::delimiter($lines[0]);
        $rows = array_map(fn (string $line) => array_map('trim', str_getcsv($line, $delimiter, '"', '')), $lines);

        // A header has a cell without digits ("Startnummer", "Chip"); chip codes and numbers have digits.
        $header = array_filter($rows[0], fn (string $cell) => $cell !== '' && ! preg_match('/\d/', $cell)) !== [] ? array_shift($rows) : null;

        return new ChipList($header, $rows, self::column($header, self::isChip(...)), self::column($header, self::isBib(...)));
    }

    private static function delimiter(string $line): string
    {
        $counts = ["\t" => substr_count($line, "\t"), ';' => substr_count($line, ';'), ',' => substr_count($line, ',')];
        arsort($counts);

        return array_key_first($counts);
    }

    private static function column(?array $header, callable $matches): ?int
    {
        foreach ($header ?? [] as $index => $name) {
            if ($matches(preg_replace('/[^a-z]/', '', mb_strtolower($name)))) {
                return $index;
            }
        }

        return null;
    }

    private static function isChip(string $name): bool
    {
        return array_filter(self::CHIP_WORDS, fn ($word) => str_contains($name, $word)) !== [];
    }

    private static function isBib(string $name): bool
    {
        return ! self::isChip($name) && (in_array($name, self::BIB_WORDS, true) || str_contains($name, 'nummer') || str_contains($name, 'bib'));
    }
}
