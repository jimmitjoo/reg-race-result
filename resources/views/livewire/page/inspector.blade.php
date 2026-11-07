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
        @endif
        @if (in_array($block['type'], ['upcoming', 'earlier'], true))
            <flux:radio.group wire:model.live="{{ $field('layout') }}" :label="__('page.layout')">
                @foreach ($block['type'] === 'upcoming' ? \App\Support\Page::LAYOUTS : ['cards', 'list'] as $layout)
                    <flux:radio :value="$layout" :label="__('page.layouts.'.$layout)" />
                @endforeach
            </flux:radio.group>
            <div class="grid grid-cols-2 gap-3">
                <flux:select wire:model.live="{{ $field('limit') }}" :label="__($block['type'] === 'upcoming' ? 'page.limit_races' : 'page.limit_series')">
                    <option value="0">{{ __('page.all') }}</option>
                    @foreach ([1, 2, 3, 4, 6, 8, 9, 12] as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                </flux:select>
                @if ($block['type'] === 'earlier')
                    <flux:select wire:model.live="{{ $field('years') }}" :label="__('page.limit_years')">
                        <option value="0">{{ __('page.all') }}</option>
                        @foreach ([1, 3, 5, 8, 10] as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                    </flux:select>
                @endif
            </div>
        @endif
        @if (isset(\App\Support\Page::ITEM_FIELDS[$block['type']]))
            <div class="flex flex-col gap-2">
                <flux:heading size="sm">{{ __("page.items.{$block['type']}") }}</flux:heading>
                <ol class="flex flex-col gap-1">
                    @foreach ($block['items'] as $item)
                        @php($label = $block['type'] === 'faq' ? $item['q'] : trim($item['value'].' '.$item['label']))
                        <li wire:key="item-{{ $item['id'] }}" class="flex items-center justify-between gap-2 rounded-field bg-zinc-50 py-1 ps-3 pe-1 dark:bg-zinc-800">
                            <span class="truncate">{{ $label !== '' ? $label : __('page.item_empty') }}</span>
                            <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removeItem('{{ $block['id'] }}', '{{ $item['id'] }}')" :aria-label="__('page.remove')" />
                        </li>
                    @endforeach
                </ol>
                <flux:button size="sm" icon="plus" wire:click="addItem('{{ $block['id'] }}')" class="self-start">{{ __("page.add_item.{$block['type']}") }}</flux:button>
            </div>
        @endif
        {{-- On an upplaga's page, a block can wait for its moment; the top, with the page's h1, always shows. --}}
        @if ($edition && $block['type'] !== 'hero')
            <flux:select wire:model.live="{{ $field('when') }}" :label="__('page.when')" :description="__('page.when_help')">
                @foreach (\App\Support\Page::WHENS as $when)
                    <option value="{{ $when }}">{{ __("page.whens.{$when}") }}</option>
                @endforeach
            </flux:select>
        @endif
        @if ($i > 0)
            <flux:radio.group wire:model.live="{{ $field('space') }}" :label="__('page.space')" variant="segmented" size="sm">
                @foreach (\App\Support\Page::SPACES as $space)
                    <flux:radio :value="$space" :label="__('page.spaces.'.$space)" />
                @endforeach
            </flux:radio.group>
        @endif
        @if ($block['type'] !== 'hero')
            <div class="flex flex-col gap-3">
                <flux:radio.group wire:model.live="{{ $field('size') }}" :label="__('page.size')" variant="segmented" size="sm">
                    @foreach (\App\Support\Page::SIZES as $size)
                        <flux:radio :value="$size" :label="__('page.sizes.'.$size)" />
                    @endforeach
                </flux:radio.group>
                @php($before = $blocks[$i - 1] ?? null)
                <flux:checkbox wire:model.live="{{ $field('beside') }}" :label="__('page.beside')" :description="__('page.beside_help')" :disabled="! $before || $before['type'] === 'hero'" />
                @if (($spans[$block['id']] ?? 12) < 12)
                    <flux:text class="text-sm">{{ __('page.in_row') }}</flux:text>
                @endif
            </div>
            {{-- Inside the theme, so the swatches show the club's colours. --}}
            <fieldset class="theme-preview flex flex-col gap-2">
                <legend class="mb-2 text-sm font-medium">{{ __('page.background') }}</legend>
                <div class="grid grid-cols-4 gap-2">
                    @foreach (\App\Support\Page::BACKGROUNDS as $background)
                        <label wire:key="bg-{{ $background }}" class="flex cursor-pointer flex-col items-center gap-1.5 rounded-field p-1.5 text-[13px] ring-1 ring-transparent hover:bg-zinc-50 has-checked:bg-zinc-100 has-checked:ring-zinc-800 has-focus-visible:outline-2 dark:hover:bg-zinc-800 dark:has-checked:bg-zinc-800 dark:has-checked:ring-zinc-200">
                            <input type="radio" class="sr-only" value="{{ $background }}" wire:model.live="{{ $field('background') }}">
                            <span @class(['h-8 w-full rounded-[6px] ring-1 ring-zinc-200 dark:ring-zinc-700', 'bg-ground' => $background === 'none', 'bg-brand-surface' => $background === 'tint', 'bg-brand' => $background === 'brand', 'bg-linear-to-br from-zinc-400 to-zinc-700' => $background === 'image']) aria-hidden="true"></span>
                            {{ __('page.backgrounds.'.$background) }}
                        </label>
                    @endforeach
                </div>
                @if ($block['background'] === 'image')
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        @if ($block['background_image'])
                            <img src="{{ Storage::disk('public')->url($block['background_image']) }}" alt="" class="h-12 w-20 rounded-field object-cover">
                        @endif
                        <input type="file" wire:model="backgroundUpload" accept="image/png,image/jpeg,image/webp" class="text-sm file:me-3 file:cursor-pointer file:rounded-field file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:font-medium file:text-zinc-800 hover:file:bg-zinc-200 dark:file:bg-zinc-800 dark:file:text-zinc-100 dark:hover:file:bg-zinc-700" aria-label="{{ __('page.background_image') }}">
                        <span wire:loading wire:target="backgroundUpload" class="text-sm text-zinc-500">{{ __('page.uploading') }}</span>
                    </div>
                    <flux:error name="backgroundUpload" />
                    <flux:text class="text-sm">{{ __('page.background_image_help') }}</flux:text>
                @endif
            </fieldset>
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
