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
        @if ($block['type'] === 'hero')
            {{-- Inside the theme, so the sketches show the club's colour. --}}
            <fieldset class="theme-preview flex flex-col gap-2">
                <legend class="mb-2 text-sm font-medium">{{ __('page.hero_look') }}</legend>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (\App\Support\Page::HEROES as $variant)
                        <label wire:key="look-{{ $variant }}" class="flex cursor-pointer flex-col gap-1.5 rounded-field p-1.5 ring-1 ring-transparent hover:bg-zinc-50 has-checked:bg-zinc-100 has-checked:ring-zinc-800 has-focus-visible:outline-2 dark:hover:bg-zinc-800 dark:has-checked:bg-zinc-800 dark:has-checked:ring-zinc-200">
                            <input type="radio" class="sr-only" value="{{ $variant }}" wire:model.live="{{ $field('variant') }}">
                            @include('livewire.page.hero-thumb')
                            <span class="text-[13px] leading-tight">{{ __("page.heroes.{$variant}") }}@if (in_array($variant, \App\Support\Page::FULL_HEROES, true))<span class="text-zinc-500"> · {{ __('page.full_width') }}</span>@endif</span>
                        </label>
                    @endforeach
                </div>
                @if (! $block['image'] && in_array($block['variant'], \App\Support\Page::IMAGE_HEROES, true))
                    <flux:text class="text-sm">{{ __('page.needs_image') }}</flux:text>
                @endif
            </fieldset>
        @endif
        @if (in_array($block['type'], ['hero', 'image'], true))
            @include('livewire.page.image-field')
        @endif
        @if (in_array($block['type'], ['form', 'contact'], true))
            <div class="flex flex-col gap-2">
                <flux:select wire:model.live="{{ $field('form_id') }}" :label="__('page.which_form')">
                    <option value="">{{ $block['type'] === 'contact' ? __('page.no_form') : __('page.choose_form') }}</option>
                    @foreach ($forms as $form)
                        <option value="{{ $form->id }}">{{ $form->name }}</option>
                    @endforeach
                </flux:select>
                @if ($chosen = $forms->get((int) $block['form_id']))
                    <flux:text class="text-sm">{{ __("forms.action_help.{$chosen->action}") }}@if ($chosen->action !== 'store') ({{ $chosen->recipient() }})@endif</flux:text>
                    <a href="{{ route('forms.edit', $chosen) }}" wire:navigate class="self-start text-sm underline underline-offset-2">{{ __('page.edit_form') }}</a>
                @endif
                <a href="{{ route('forms.index') }}" wire:navigate class="self-start text-sm underline underline-offset-2">{{ __('page.new_form') }}</a>
            </div>
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
