<?php

namespace App\Timing;

use App\Models\ChipRead;
use App\Models\Event;

final class ReadImporter
{
    /**
     * Imports RFIDServer lines for one reader. Safe to repeat: a line already stored is skipped,
     * so the browser can resend after a lost connection and a file can be uploaded again by hand.
     */
    public static function import(Event $event, string $reader, string $contents): ImportSummary
    {
        $parsed = RfidServerParser::parse($contents, $reader, $event->timezone);
        $now = now();

        $rows = array_map(fn (Read $read) => [
            'event_id' => $event->id,
            'reader' => $read->reader,
            'chip' => $read->chip,
            'read_at' => $read->readAt->utc()->format('Y-m-d H:i:s.v'),
            'unit' => $read->unit,
            'antenna' => $read->antenna,
            'created_at' => $now,
        ], $parsed->reads);

        $inserted = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            $inserted += ChipRead::insertOrIgnore($chunk);
        }

        return new ImportSummary($inserted, count($rows) - $inserted, $parsed->rejected);
    }
}
