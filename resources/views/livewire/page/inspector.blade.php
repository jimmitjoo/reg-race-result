{{-- The selected block's settings, beside the page: what is not written into the page itself. --}}
<div class="flex flex-col gap-4 rounded-panel bg-white p-5 text-sm text-zinc-800 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-700">
    @if ($selected)
        @php($i = collect($blocks)->search(fn ($b) => $b['id'] === $selected['id']))
        @php($block = $selected)
        @php($field = fn ($name) => "blocks.{$i}.{$name}")
        <div class="flex flex-col gap-1">
            <flux:heading size="lg">{{ __("page.type.{$block['type']}") }}</flux:heading>
            <flux:text>{{ __("page.about.{$block['type']}") }}</flux:text>
        </div>
        @if (in_array($block['type'], ['hero', 'image'], true))
            @include('livewire.page.image-field')
        @endif
        @if ($block['type'] === 'image')
            <flux:select wire:model.live="{{ $field('size') }}" :label="__('page.size')">
                <option value="text">{{ __('page.size_text') }}</option>
                <option value="wide">{{ __('page.size_wide') }}</option>
            </flux:select>
        @endif
        <flux:text>{{ __("page.inline.{$block['type']}") }}</flux:text>
        <div class="flex flex-wrap gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:button size="sm" icon="arrow-up" wire:click="move('{{ $block['id'] }}', -1)" :disabled="$i === 0">{{ __('page.move_up') }}</flux:button>
            <flux:button size="sm" icon="arrow-down" wire:click="move('{{ $block['id'] }}', 1)" :disabled="$i === count($blocks) - 1">{{ __('page.move_down') }}</flux:button>
            <flux:button size="sm" variant="danger" icon="trash" wire:click="remove('{{ $block['id'] }}')">{{ __('page.remove') }}</flux:button>
        </div>
    @else
        <flux:heading size="lg">{{ __('page.how_title') }}</flux:heading>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-zinc-600 dark:text-zinc-300">
            <li>{{ __('page.how_write') }}</li>
            <li>{{ __('page.how_select') }}</li>
            <li>{{ __('page.how_add') }}</li>
            <li>{{ __('page.how_publish') }}</li>
        </ul>
    @endif
</div>
