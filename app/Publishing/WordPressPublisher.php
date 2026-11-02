<?php

namespace App\Publishing;

use App\Exports\ResultsPdf;
use App\Models\Event;
use App\Models\EventPublication;
use App\Models\WordPressSite;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Publishes the result list on the organizer's WordPress sites through the REST API
 * (application passwords): the PDF goes to the media library and, for a site with a results
 * parent page, a page with the PDF embedded is created or updated under it.
 */
final class WordPressPublisher
{
    /** @throws RequestException */
    public static function publish(Event $event, WordPressSite $site): EventPublication
    {
        $media = self::client($site)
            ->withHeaders(['Content-Disposition' => 'attachment; filename="'.__('pdf.filename', ['event' => $event->name]).'"'])
            ->withBody(Pdf::loadHTML(ResultsPdf::html($event))->setPaper('a4')->output(), 'application/pdf')
            ->post($site->api('media'))
            ->throw()
            ->json();

        $publication = EventPublication::firstOrNew(['event_id' => $event->id, 'wordpress_site_id' => $site->id]);
        $previousMedia = $publication->media_id;
        $publication->fill(['media_id' => $media['id'], 'media_url' => $media['source_url']]);

        if ($site->results_parent_page_id) {
            $page = self::client($site)->post($site->api($publication->page_id ? "pages/{$publication->page_id}" : 'pages'), [
                'title' => $event->name,
                'status' => 'publish',
                'parent' => $site->results_parent_page_id,
                'content' => view('publishing.wordpress-page', ['event' => $event, 'pdfUrl' => $media['source_url']])->render(),
            ])->throw()->json();

            $publication->fill(['page_id' => $page['id'], 'page_url' => $page['link']]);
        }

        $publication->save();

        // The old PDF is replaced, not kept beside the new one. Failing to delete it is harmless.
        if ($previousMedia && $previousMedia !== $media['id']) {
            self::client($site)->delete($site->api("media/{$previousMedia}"), ['force' => true]);
        }

        return $publication;
    }

    /** @throws RequestException */
    public static function test(WordPressSite $site): string
    {
        return self::client($site)->get($site->api('users/me'))->throw()->json('name');
    }

    private static function client(WordPressSite $site): PendingRequest
    {
        return Http::withBasicAuth($site->username, $site->app_password)->acceptJson()->timeout(60);
    }
}
