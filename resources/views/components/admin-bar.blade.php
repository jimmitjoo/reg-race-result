@props(['organizer' => null, 'event' => null])
{{-- For the organizer's own people, signed in: the way from the page to editing it, as a bar above it. Never printed. --}}
@if ($organizer && auth()->user()?->organizer_id === $organizer->id)
    <nav aria-label="{{ __('public.admin_bar') }}" class="lp-admin-bar bg-zinc-900 text-[13px] text-zinc-200 print:hidden">
        <div class="mx-auto flex max-w-[1100px] items-center gap-1 overflow-x-auto px-2 py-1.5 whitespace-nowrap sm:px-4">
            @php($link = 'inline-flex h-8 items-center gap-1.5 rounded-[6px] px-2.5 font-medium hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-white')
            <a href="{{ route('dashboard') }}" class="{{ $link }}"><flux:icon.home variant="micro" />{{ __('public.admin') }}</a>
            <span aria-hidden="true" class="mx-1 h-4 w-px shrink-0 bg-white/20"></span>
            @if ($event)
                <a href="{{ route('events.page', $event) }}" class="{{ $link }} text-white"><flux:icon.pencil-square variant="micro" />{{ __('public.edit_event_page') }}</a>
                <a href="{{ route('events.theme', $event) }}" class="{{ $link }}"><flux:icon.paint-brush variant="micro" />{{ __('theme.title') }}</a>
                <a href="{{ route('events.show', $event) }}" class="{{ $link }}"><flux:icon.cog-6-tooth variant="micro" />{{ __('public.manage_event') }}</a>
            @else
                <a href="{{ route('page.edit') }}" class="{{ $link }} text-white"><flux:icon.pencil-square variant="micro" />{{ __('public.edit_page') }}</a>
                <a href="{{ route('theme.edit') }}" class="{{ $link }}"><flux:icon.paint-brush variant="micro" />{{ __('theme.title') }}</a>
                <a href="{{ route('events.index') }}" class="{{ $link }}"><flux:icon.flag variant="micro" />{{ __('public.manage_events') }}</a>
            @endif
            @if (request()->boolean('forhandsvisning') && request()->hasValidSignature())
                <span class="ms-auto rounded-pill bg-peach px-2.5 py-0.5 font-semibold text-ink">{{ __('public.previewing') }}</span>
            @endif
        </div>
    </nav>
@endif
