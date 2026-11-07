{{-- + between two blocks: a new block goes here. --}}
<div class="flex flex-col items-center py-2 font-[ui-sans-serif,system-ui,sans-serif]">
    <button type="button" wire:click="choose({{ $at }})" aria-expanded="{{ $addingAt === $at ? 'true' : 'false' }}"
        class="group flex items-center gap-2 rounded-pill px-3 py-1 text-sm text-ink-soft hover:bg-white hover:text-ink focus-visible:bg-white">
        <span class="flex size-7 items-center justify-center rounded-full border-[1.5px] border-current"><flux:icon.plus class="size-4" /></span>
        <span @class(['sr-only group-hover:not-sr-only group-focus-visible:not-sr-only' => $addingAt !== $at])>{{ __('page.add') }}</span>
    </button>
    @if ($addingAt === $at)
        <div class="mt-2 grid w-full max-w-3xl gap-2 rounded-panel bg-white p-3 shadow-rest sm:grid-cols-2 lg:grid-cols-3" role="group" aria-label="{{ __('page.choose') }}">
            @foreach (\App\Support\Page::TYPES as $type)
                <button type="button" wire:click="add('{{ $type }}', {{ $at }})" class="flex flex-col gap-0.5 rounded-field p-3 text-left hover:bg-ground focus-visible:bg-ground">
                    <span class="font-medium text-ink">{{ __("page.type.{$type}") }}</span>
                    <span class="text-[13px] text-ink-soft">{{ __("page.about.{$type}") }}</span>
                </button>
            @endforeach
        </div>
    @endif
</div>
