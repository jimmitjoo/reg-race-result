<p><a href="{{ $pdfUrl }}">{{ __('sites.page_pdf_link', ['event' => $event->name]) }}</a></p>
<iframe src="{{ $pdfUrl }}" style="width:100%;height:1100px;border:0"></iframe>
@if ($event->results_public_at)
<p><a href="{{ route('public.results', ['organizer' => $event->organizer->slug, 'event' => $event->slug]) }}">{{ __('sites.page_live_link') }}</a></p>
@endif
