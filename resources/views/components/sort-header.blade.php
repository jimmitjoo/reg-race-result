{{-- A table header that sorts its column: on the server with a column name, in the browser otherwise (resources/js/app.js). --}}
@props(['column' => null, 'sort' => null, 'dir' => 'asc'])

<th scope="col" {{ $attributes->class('group') }} @if ($column && $sort === $column) aria-sort="{{ $dir === 'desc' ? 'descending' : 'ascending' }}" @endif>
    <button type="button" @if ($column) wire:click="sortBy('{{ $column }}')" @else data-sort @endif
        class="inline-flex cursor-pointer items-center gap-1 font-[inherit] underline-offset-2 hover:underline">
        {{ $slot }}
        <svg aria-hidden="true" viewBox="0 0 12 12" class="size-3 shrink-0 opacity-0 transition group-hover:opacity-40 group-aria-[sort=ascending]:opacity-100 group-aria-[sort=descending]:rotate-180 group-aria-[sort=descending]:opacity-100">
            <path d="M3 7.5 6 4.5l3 3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>
</th>
