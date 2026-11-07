{{-- + where a new block goes (0 first): floating on the edge below a block, shown on hover; in the page's flow on an empty page. --}}
@php($open = $addingAt === $at)
<div @class([
    'flex flex-col items-center',
    'relative py-6' => $inline ?? false,
    'absolute inset-x-0 -bottom-4 z-30' => ! ($inline ?? false),
    'opacity-0 group-hover:opacity-100 focus-within:opacity-100' => ! ($inline ?? false) && ! $open && ! ($chosen ?? false),
])>
    <button type="button" wire:click="choose({{ $at }})" aria-expanded="{{ $open ? 'true' : 'false' }}"
        class="flex items-center gap-2 rounded-pill bg-white px-3 py-1 text-sm text-zinc-700 shadow-raised ring-1 ring-zinc-200 hover:text-zinc-950">
        <flux:icon.plus class="size-4" />
        <span>{{ __('page.add') }}</span>
    </button>
    @if ($open)
        <div class="absolute top-full z-40 mt-2 grid w-[min(48rem,calc(100vw-2rem))] gap-2 rounded-panel bg-white p-3 shadow-raised ring-1 ring-zinc-200 sm:grid-cols-2 lg:grid-cols-3" role="group" aria-label="{{ __('page.choose') }}">
            @foreach ($types as $type)
                <button type="button" wire:click="add('{{ $type }}', {{ $at }})" class="flex flex-col gap-0.5 rounded-field p-3 text-left hover:bg-zinc-100 focus-visible:bg-zinc-100">
                    <span class="font-medium text-zinc-900">{{ __("page.type.{$type}") }}</span>
                    <span class="text-[13px] text-zinc-500">{{ __("page.about.{$type}") }}</span>
                </button>
            @endforeach
        </div>
    @endif
</div>
