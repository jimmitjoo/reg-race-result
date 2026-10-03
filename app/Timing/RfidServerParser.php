<?php

namespace App\Timing;

use Carbon\CarbonImmutable;

/**
 * Parses the text files RFIDServer.exe (RFID Timing) writes, one per reader:
 * chip<TAB>Y-m-d H:i:s.v<TAB>unit<TAB>antenna, local time, CR LF or CR CR LF.
 * Lines are not in chronological order.
 */
final class RfidServerParser
{
    private const LINE = '/^(\d+)\t(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\t(\d+)\t(\d+)$/';

    public static function parse(string $contents, string $reader, string $timezone): ParseResult
    {
        $reads = [];
        $rejected = [];

        foreach (preg_split('/\r+\n?|\n/', $contents) as $line) {
            if ($line === '') {
                continue;
            }

            if (! preg_match(self::LINE, $line, $m)) {
                $rejected[] = $line;

                continue;
            }

            $reads[] = new Read(
                chip: $m[1],
                readAt: CarbonImmutable::createFromFormat('Y-m-d H:i:s.v', $m[2], $timezone),
                reader: $reader,
                unit: (int) $m[3],
                antenna: (int) $m[4],
            );
        }

        return new ParseResult($reads, $rejected);
    }
}
