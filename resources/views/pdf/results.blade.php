<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('pdf.title', ['event' => $event->name]) }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 15pt; margin: 0 0 10pt; }
        .meta td { padding: 1pt 18pt 1pt 0; vertical-align: top; }
        .note { margin: 8pt 0; }
        h2 { font-size: 11pt; margin: 14pt 0 3pt; border-bottom: 0.6pt solid #999; padding-bottom: 2pt; }
        h2 .start { float: right; font-weight: normal; }
        table.results { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.results th { text-align: left; font-weight: bold; padding: 1.5pt 4pt 1.5pt 0; }
        table.results td { padding: 1pt 4pt 1pt 0; }
        table.results .num, table.results .result { text-align: right; }
        table.results .result { white-space: nowrap; }
        table.results .num { padding-right: 8pt; }
    </style>
</head>
<body>
    <h1>{{ __('pdf.title', ['event' => $event->name]) }}</h1>

    <table class="meta">
        <tr>
            <td>{{ __('pdf.date') }}: {{ $event->date->toDateString() }}</td>
            <td>@if ($event->city){{ __('pdf.place') }}: {{ $event->city }}@endif</td>
        </tr>
        <tr>
            <td>{{ __('pdf.organizer') }}: {{ $event->organizer->name }}</td>
            <td>@if ($event->race_director){{ __('pdf.race_director') }}: {{ $event->race_director }}@endif</td>
        </tr>
        @if ($event->weather)
            <tr><td colspan="2">{{ __('pdf.weather') }}: {{ $event->weather }}</td></tr>
        @endif
        <tr><td colspan="2">{{ __('pdf.participants') }}: {{ __('pdf.registered_finished', ['registered' => $registered, 'finished' => $finished]) }}</td></tr>
    </table>

    @if ($measuredRace)
        <p class="note">{{ __('pdf.measured', ['date' => $measuredRace->measured_on->toDateString(), 'name' => $measuredRace->course_measurer]) }}</p>
    @endif
    @if ($prizes->isNotEmpty())
        <table class="meta note">
            <tr><td colspan="3"><strong>{{ __('pdf.prizes') }}</strong></td></tr>
            @foreach ($prizes as $prize)
                <tr>
                    <td>{{ $prize->name }}</td>
                    <td>{{ $prize->winner->bib }}</td>
                    <td>{{ $prize->winner->first_name }} {{ $prize->winner->last_name }}@if ($prize->winner->club), {{ $prize->winner->club }}@endif</td>
                </tr>
            @endforeach
        </table>
    @endif
    @if ($event->contact_email)
        <p class="note">{{ __('pdf.feedback', ['email' => $event->contact_email]) }}</p>
    @endif

    @foreach ($classes as $results)
        @continue($results->rows === [])
        <h2>{{ $results->raceClass->name }} <span class="start">{{ $results->raceClass->start_at->setTimezone($event->timezone)->format('H:i:s') }}</span></h2>
        <table class="results">
            <thead>
                <tr>
                    <th class="num" style="width: 6%">{{ __('results.placing') }}</th>
                    <th style="width: 15%">{{ __('pdf.first_name') }}</th>
                    <th style="width: 18%">{{ __('pdf.last_name') }}</th>
                    <th style="width: 6%">{{ __('results.born') }}</th>
                    <th style="width: 29%">{{ __('results.club') }}</th>
                    <th class="result" style="width: 10%">{{ $results->timed ? __('results.time') : __('results.untimed') }}</th>
                    <th class="num" style="width: 8%">{{ __('results.bib') }}</th>
                    <th style="width: 8%">{{ __('pdf.country') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($results->rows as $row)
                    <tr>
                        <td class="num">{{ $row->placing }}</td>
                        <td>{{ $row->firstName }}</td>
                        <td>{{ $row->lastName }}</td>
                        <td>{{ $row->birthYear }}</td>
                        <td>{{ $row->club }}</td>
                        <td class="result">{{ $row->time }}</td>
                        <td class="num">{{ $row->bib }}</td>
                        <td>{{ $row->country }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
