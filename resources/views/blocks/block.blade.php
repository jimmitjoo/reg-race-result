{{-- A block, on the page's ground or on a band of the club's tint or colour from edge to edge, its content in the page's column. --}}
@php($background = $block['type'] === 'hero' ? 'none' : ($block['background'] ?? 'none'))
@if ($background === 'none')
    @include('blocks.'.$block['type'])
@else
    <div @class(['bleed py-12 sm:py-16', 'bg-brand-surface text-ink' => $background === 'tint', 'on-brand bg-brand' => $background === 'brand'])>
        <div class="mx-auto max-w-[1100px] px-4 sm:px-6">
            @include('blocks.'.$block['type'])
        </div>
    </div>
@endif
