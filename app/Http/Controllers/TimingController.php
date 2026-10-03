<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Timing\ReadImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimingController extends Controller
{
    public function show(Event $event): View
    {
        return view('timing', ['event' => $event]);
    }

    public function storeReads(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'reader' => ['required', 'regex:/^\d{1,3}$/'],
            'lines' => ['present', 'nullable', 'string', 'max:2000000'],
        ]);

        $summary = ReadImporter::import($event, $data['reader'], $data['lines'] ?? '');

        return response()->json([
            'inserted' => $summary->inserted,
            'duplicates' => $summary->duplicates,
            'rejected' => $summary->rejected,
        ]);
    }
}
