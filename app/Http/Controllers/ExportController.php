<?php

namespace App\Http\Controllers;

use App\Exports\SfifExport;
use App\Models\Event;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    public function sfif(Event $event): Response
    {
        return response(SfifExport::csv($event), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.SfifExport::filename($event).'"',
        ]);
    }
}
