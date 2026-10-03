<?php

namespace App\Exports;

use App\Models\Event;
use App\Results\ResultList;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/** The printable result list, laid out like the lists Högby IF publishes today. */
final class ResultsPdf
{
    public static function html(Event $event): string
    {
        $classes = ResultList::for($event);
        $finished = collect($classes)->sum(fn ($results) => collect($results->rows)->filter(fn ($row) => $row->placing !== null || ! $results->timed)->count());

        return view('pdf.results', [
            'event' => $event,
            'classes' => $classes,
            'registered' => $event->registrations()->count(),
            'finished' => $finished,
            'prizes' => $event->prizes()->with('winner')->whereNotNull('registration_id')->orderBy('id')->get(),
            'measuredRace' => $event->races()->whereNotNull('course_measurer')->whereNotNull('measured_on')->first(),
        ])->render();
    }

    public static function download(Event $event): Response
    {
        return Pdf::loadHTML(self::html($event))
            ->setPaper('a4')
            ->download(__('pdf.filename', ['event' => $event->name]));
    }
}
