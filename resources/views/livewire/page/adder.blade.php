{{-- + between two blocks: a new block goes here. --}}
<div class="flex flex-col items-center py-2">
    <button type="button" wire:click="choose({{ $at }})" aria-expanded="{{ $addingAt === $at ? 'true' : 'false' }}"
        class="group flex items-center gap-2 rounded-pill px-3 py-1 text-sm text-ink-soft hover:bg-white hover:text-ink focus-visible:bg-white">
        <span class="flex size-7 items-center justify-center rounded-full border-[1.5px] border-current"><flux:icon.plus class="size-4" /></span>
        <span @class(['sr-only group-hover:not-sr-only group-focus-visible:not-sr-only' => $addingAt !== $at])>{{ __('page.add') }}</span>
    </button>
    @if ($addingAt === $at)
        <div class="mt-2 grid w-full max-w-3xl gap-2 rounded-panel bg-white p-3 shadow-rest ring-1 ring-zinc-200 sm:grid-cols-2 lg:grid-cols-3 dark:bg-zinc-900 dark:ring-zinc-700" role="group" aria-label="{{ __('page.choose') }}">
            @foreach ($types as $type)
                <button type="button" wire:click="add('{{ $type }}', {{ $at }})" class="flex flex-col gap-0.5 rounded-field p-3 text-left hover:bg-zinc-100 focus-visible:bg-zinc-100 dark:hover:bg-zinc-800 dark:focus-visible:bg-zinc-800">
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ __("page.type.{$type}") }}</span>
                    <span class="text-[13px] text-zinc-500 dark:text-zinc-400">{{ __("page.about.{$type}") }}</span>
                </button>
            @endforeach
        </div>
    @endif
</div>
