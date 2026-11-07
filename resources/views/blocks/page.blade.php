{{-- The blocks in order. The name is the page's only h1: in the hero, or for screen readers when there is none. --}}
@unless (collect($blocks)->contains('type', 'hero'))
    <h1 class="sr-only">{{ $organizer->name }}</h1>
@endunless
@foreach ($blocks as $block)
    <div wire:key="block-{{ $block['id'] }}" class="contents">
        @include('blocks.block', ['first' => $loop->first])
    </div>
@endforeach
