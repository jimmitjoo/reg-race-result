<?php

namespace App\Http\Controllers;

use App\Exports\RegistrationsCsv;
use App\Exports\ResultsPdf;
use App\Exports\SfifExport;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ExportController extends Controller
{
    public function sfif(Event $event): Response
    {
        return response(SfifExport::csv($event), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.SfifExport::filename($event).'"',
        ]);
    }

    public function registrations(Request $request, Event $event): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(RegistrationsCsv::STATUSES)],
            'race' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return response(RegistrationsCsv::csv($event, $filters['status'] ?? 'paid', $filters['race'] ?? null, trim($filters['search'] ?? '')), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.RegistrationsCsv::filename($event).'"',
        ]);
    }

    public function pdf(Event $event): Response
    {
        return ResultsPdf::download($event);
    }
}
