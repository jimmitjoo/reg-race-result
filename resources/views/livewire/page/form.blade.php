{{-- The fields of the block being edited, saved as a draft while typing. --}}
@php($field = fn ($name) => "blocks.{$i}.{$name}")
<div class="flex flex-col gap-4 rounded-panel bg-white p-5 text-zinc-800 shadow-rest ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-700">
    @switch ($block['type'])
        @case ('hero')
            <flux:textarea wire:model.live.debounce.400ms="{{ $field('intro') }}" rows="3" :label="__('page.intro')" :description="__('page.intro_help')" />
            @include('livewire.page.image-field')
            @break
        @case ('text')
            <flux:input wire:model.live.debounce.400ms="{{ $field('heading') }}" :label="__('page.heading')" />
            <flux:textarea wire:model.live.debounce.400ms="{{ $field('body') }}" rows="8" :label="__('page.body')" :description="__('page.body_help')" />
            @break
        @case ('image')
            @include('livewire.page.image-field')
            <flux:input wire:model.live.debounce.400ms="{{ $field('caption') }}" :label="__('page.caption')" />
            <flux:select wire:model.live="{{ $field('size') }}" :label="__('page.size')">
                <option value="text">{{ __('page.size_text') }}</option>
                <option value="wide">{{ __('page.size_wide') }}</option>
            </flux:select>
            @break
        @case ('upcoming')
        @case ('earlier')
            <flux:text>{{ __('page.automatic') }}</flux:text>
            <flux:input wire:model.live.debounce.400ms="{{ $field('heading') }}" :label="__('page.heading')" :placeholder="__($block['type'] === 'upcoming' ? 'public.upcoming' : 'public.earlier')" />
            @break
        @case ('contact')
            <flux:input wire:model.live.debounce.400ms="{{ $field('heading') }}" :label="__('page.heading')" :placeholder="__('page.contact')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model.live.debounce.400ms="{{ $field('name') }}" :label="__('page.name')" />
                <flux:input wire:model.live.debounce.400ms="{{ $field('email') }}" type="email" :label="__('page.email')" />
                <flux:input wire:model.live.debounce.400ms="{{ $field('phone') }}" type="tel" :label="__('page.phone')" />
            </div>
            @break
    @endswitch
    <div><flux:button size="sm" variant="primary" wire:click="edit('{{ $block['id'] }}')">{{ __('page.done') }}</flux:button></div>
</div>
