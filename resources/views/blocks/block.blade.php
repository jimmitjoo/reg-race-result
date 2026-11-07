{{--
    A block in its cell. Alone in its row it is as wide as chosen, and a background is a band from edge to
    edge; beside others it fills its cell, and a background is a panel. The hero lays itself out.
--}}
@php($span = $spans[$block['id']] ?? 12)
@php($background = $block['type'] === 'hero' ? 'none' : ($block['background'] ?? 'none'))
@if ($background === 'image' && empty($block['background_image']))
    @php($background = 'brand')
@endif
@php($size = $span < 12 ? 'cell' : ($block['size'] ?? 'text'))
{{-- Cards of upplagor read best in the page's column: as wide as the window, only their background is. --}}
@if ($size === 'full' && in_array($block['type'], ['upcoming', 'earlier'], true))
    @php($size = 'wide')
@endif
@php($measure = ['text' => 'mx-auto w-full max-w-[720px]', 'wide' => 'mx-auto w-full max-w-[1100px]', 'full' => $block['type'] === 'image' ? '' : 'px-4 sm:px-6', 'cell' => ''][$size])
@if ($block['type'] === 'hero')
    @include('blocks.hero')
@elseif ($background === 'none')
    <div @class(['h-full', 'bleed' => $size === 'full', $measure])>@include('blocks.'.$block['type'])</div>
@else
    <div @class([
        'relative isolate h-full overflow-hidden',
        'bleed py-12 sm:py-16' => $span === 12,
        'rounded-card p-6 shadow-rest sm:p-8' => $span < 12,
        'bg-brand-surface text-ink' => $background === 'tint',
        'on-brand bg-brand' => $background === 'brand',
        'on-image bg-ink' => $background === 'image',
    ])>
        @if ($background === 'image')
            {{-- Behind the text, so it says nothing to a screen reader. --}}
            <img src="{{ Storage::disk('public')->url($block['background_image']) }}" alt="" loading="lazy" class="absolute inset-0 -z-10 h-full w-full object-cover">
            <div class="absolute inset-0 -z-10 bg-black/60"></div>
        @endif
        <div @class(['mx-auto w-full px-4 sm:px-6' => $span === 12, 'max-w-[calc(720px+3rem)]' => $size === 'text', 'max-w-[calc(1100px+3rem)]' => $size === 'wide'])>
            @include('blocks.'.$block['type'])
        </div>
    </div>
@endif
