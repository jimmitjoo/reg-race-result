<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Timing\ReadImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    /** Fallback when the page could not read the folder live: upload RFIDServer's file afterwards. */
    public function upload(Request $request, Event $event): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:20480']]);
        $file = $request->file('file');

        if (! preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.(\d{1,3})(?: \(\d+\))?\.txt$/', $file->getClientOriginalName(), $m)) {
            return back()->withErrors(['file' => __('timing.upload_bad_name')]);
        }

        $summary = ReadImporter::import($event, $m[1], $file->get());

        return redirect()->route('events.timing', $event)->with('upload', __('timing.uploaded', [
            'inserted' => $summary->inserted,
            'duplicates' => $summary->duplicates,
            'reader' => $m[1],
        ]));
    }
}
