@props(['organizer' => null, 'event' => null, 'editing' => false])
{{--
    For the organizer's own people, signed in: a bar above the page, as WordPress has, that switches the page
    between showing it and editing it in place. While editing, the editor's own actions sit in its slot. Never printed.
--}}
@if ($organizer && auth()->user()?->organizer_id === $organizer->id)
    @php($link = 'inline-flex h-8 shrink-0 items-center gap-1.5 rounded-[6px] px-2.5 font-medium hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-white')
    @php($view = $event ? $event->publicUrl() : route('public.organizer', $organizer->slug))
    @php($edit = $event ? route('events.page', $event) : route('page.edit'))
    <nav aria-label="{{ __('public.admin_bar') }}" {{ $attributes->class('lp-admin-bar bg-zinc-900 text-[13px] text-zinc-200 print:hidden') }}>
        <div class="mx-auto flex max-w-[1440px] items-center gap-1 overflow-x-auto px-2 py-1.5 whitespace-nowrap sm:px-4">
            <a href="{{ $event ? route('events.show', $event) : route('dashboard') }}" class="{{ $link }}"><flux:icon.arrow-left variant="micro" /><span class="max-sm:sr-only">{{ __('public.admin') }}</span></a>
            {{-- The switch: the page as runners see it, or the same page to edit. --}}
            <span class="ms-1 inline-flex shrink-0 rounded-[8px] bg-white/10 p-0.5" role="group" aria-label="{{ __('public.mode') }}">
                <a href="{{ $view }}" @if (! $editing) aria-current="page" @endif @class(['inline-flex h-7 items-center gap-1.5 rounded-[6px] px-3 font-semibold', 'bg-white text-zinc-900' => ! $editing, 'hover:text-white' => $editing])><flux:icon.eye variant="micro" />{{ __('public.mode_view') }}</a>
                <a href="{{ $edit }}" @if ($editing) aria-current="page" @endif @class(['inline-flex h-7 items-center gap-1.5 rounded-[6px] px-3 font-semibold', 'bg-white text-zinc-900' => $editing, 'hover:text-white' => ! $editing])><flux:icon.pencil-square variant="micro" />{{ __('public.mode_edit') }}</a>
            </span>
            @if ($editing)
                {{ $slot }}
            @else
                <a href="{{ $edit }}?utseende=1" class="{{ $link }}"><flux:icon.paint-brush variant="micro" />{{ __('theme.title') }}</a>
                @if ($event)
                    <a href="{{ route('events.show', $event) }}" class="{{ $link }}"><flux:icon.cog-6-tooth variant="micro" />{{ __('public.manage_event') }}</a>
                @endif
                @if (request()->boolean('forhandsvisning') && request()->hasValidSignature())
                    <span class="ms-auto rounded-pill bg-peach px-2.5 py-0.5 font-semibold text-ink">{{ __('public.previewing') }}</span>
                @endif
            @endif
        </div>
    </nav>
@endif
