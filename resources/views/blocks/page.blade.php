{{-- The blocks in order, in a grid of twelve. The hero's title is the page's only h1, or for screen readers the name when there is no hero. --}}
@unless (collect($blocks)->contains('type', 'hero'))
    <h1 class="sr-only">{{ $organizer->name }}</h1>
@endunless
@php($spans = \App\Support\Page::spans($blocks))
<div class="grid grid-cols-12 gap-x-6 gap-y-12 sm:gap-y-16">
    @foreach ($blocks as $block)
        <div wire:key="block-{{ $block['id'] }}" class="{{ \App\Support\Page::cell($spans[$block['id']]) }} min-w-0">
            @include('blocks.block', ['first' => $loop->first])
        </div>
    @endforeach
</div>
