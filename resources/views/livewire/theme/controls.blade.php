{{-- The theme's choices: logo, colours, fonts and shape; on its own page, or as a panel beside the page being edited. --}}
@use('App\Support\Theme')
{{-- The logo, and the colour it suggests, read in the browser so nothing has to be installed on the server. --}}
<div class="flex flex-col gap-3" x-data="{ suggested: null }">
    <flux:heading size="lg">{{ __('theme.logo') }}</flux:heading>
    <div class="flex flex-wrap items-center gap-4">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ __('theme.logo_of', ['name' => $name]) }}" class="h-16 w-auto max-w-[200px] rounded-field bg-white object-contain p-2 ring-1 ring-contour">
            <flux:button size="sm" wire:click="removeLogo">{{ $event ? __('theme.use_organizers') : __('theme.remove') }}</flux:button>
        @endif
        <input type="file" wire:model="upload" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="text-sm file:me-3 file:cursor-pointer file:rounded-field file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:font-medium file:text-zinc-800 hover:file:bg-zinc-200"
            aria-label="{{ __('theme.logo_upload') }}"
            x-on:change="
                const file = $event.target.files[0]; if (!file) return;
                const img = new Image(); img.onload = () => {
                    const c = document.createElement('canvas'); c.width = c.height = 32;
                    const g = c.getContext('2d'); g.drawImage(img, 0, 0, 32, 32);
                    const d = g.getImageData(0, 0, 32, 32).data; const count = {};
                    for (let i = 0; i < d.length; i += 4) {
                        const [r, gr, b, a] = [d[i], d[i + 1], d[i + 2], d[i + 3]];
                        const max = Math.max(r, gr, b), min = Math.min(r, gr, b);
                        if (a < 200 || max - min < 40) continue;
                        const key = [r, gr, b].map(v => Math.round(v / 32) * 32).map(v => Math.min(v, 255).toString(16).padStart(2, '0')).join('');
                        count[key] = (count[key] || 0) + 1;
                    }
                    const best = Object.entries(count).sort((a, b) => b[1] - a[1])[0];
                    suggested = best ? '#' + best[0] : null;
                }; img.src = URL.createObjectURL(file);
            ">
    </div>
    <flux:text class="text-sm">{{ __('theme.logo_help') }}</flux:text>
    @error('upload')<flux:text class="text-sm font-medium text-danger">{{ $message }}</flux:text>@enderror
    <template x-if="suggested">
        <div class="flex items-center gap-3">
            <span class="size-6 rounded-full ring-1 ring-contour" :style="'background:' + suggested"></span>
            <flux:button size="sm" x-on:click="$wire.set('brand', suggested); suggested = null">{{ __('theme.use_logo_colour') }}</flux:button>
        </div>
    </template>
</div>

<div class="flex flex-col gap-3">
    <flux:heading size="lg">{{ __('theme.colours') }}</flux:heading>
    <div @class(['grid gap-4', 'sm:grid-cols-2' => ! $panel])>
        @foreach (['brand' => __('theme.brand'), 'accent' => __('theme.accent')] as $field => $label)
            <div class="flex items-end gap-2">
                <input type="color" x-on:change="$wire.set('{{ $field }}', $event.target.value)" value="{{ $this->{$field} ?: Theme::MOSS }}" class="h-10 w-12 shrink-0 cursor-pointer rounded-field border border-contour bg-white p-1" aria-label="{{ $label }}">
                <flux:input wire:model.live.blur="{{ $field }}" :label="$label" placeholder="{{ $event && $field === 'brand' ? __('theme.inherited') : Theme::MOSS }}" class="flex-1" />
            </div>
        @endforeach
    </div>
    @if ($adjusted)
        <flux:callout icon="information-circle" :heading="__('theme.adjusted', ['colour' => $adjusted])" :text="__('theme.adjusted_help')" />
    @endif
    <flux:text class="text-sm">{{ __('theme.colours_help') }}</flux:text>
</div>

@php($choice = 'flex cursor-pointer flex-col rounded-field border border-contour bg-white p-3 peer-checked:border-accent peer-checked:ring-2 peer-checked:ring-accent/40 peer-focus-visible:outline-2 peer-focus-visible:outline-accent dark:bg-white/5')
<fieldset class="flex flex-col gap-3">
    <legend class="mb-1 text-sm font-medium">{{ __('theme.fonts') }}</legend>
    <div @class(['grid gap-3', 'sm:grid-cols-2' => ! $panel])>
        @if ($event)
            <label><input type="radio" name="fonts" value="" wire:model.live="fonts" class="peer sr-only"><span class="{{ $choice }} justify-center text-sm">{{ __('theme.inherit') }}</span></label>
        @endif
        @foreach (\App\Support\Theme::FONTS as $pair => [$display, $body])
            <label wire:key="font-{{ $pair }}">
                <input type="radio" name="fonts" value="{{ $pair }}" wire:model.live="fonts" class="peer sr-only">
                <span class="{{ $choice }}">
                    <span class="text-xs text-zinc-500">{{ __('theme.font_pair.'.$pair) }}</span>
                    <span class="text-[22px] leading-tight" style="font-family: '{{ $display }}'">Sylvesterloppet</span>
                    <span class="text-[15px]" style="font-family: '{{ $body }}'">{{ __('theme.font_sample') }}</span>
                </span>
            </label>
        @endforeach
    </div>
</fieldset>

<fieldset class="flex flex-col gap-3">
    <legend class="mb-1 text-sm font-medium">{{ __('theme.shape') }}</legend>
    <div class="flex flex-wrap gap-3">
        @if ($event)
            <label><input type="radio" name="shape" value="" wire:model.live="shape" class="peer sr-only"><span class="{{ $choice }} text-sm">{{ __('theme.inherit') }}</span></label>
        @endif
        @foreach (\App\Support\Theme::SHAPES as $shapeName => [$card])
            <label wire:key="shape-{{ $shapeName }}">
                <input type="radio" name="shape" value="{{ $shapeName }}" wire:model.live="shape" class="peer sr-only">
                <span class="{{ $choice }} flex-row items-center gap-3 text-sm"><span class="size-8 border-2 border-zinc-400" style="border-radius: {{ $card === '28px' ? '10px' : ($card === '16px' ? '6px' : '2px') }}"></span>{{ __('theme.shapes.'.$shapeName) }}</span>
            </label>
        @endforeach
    </div>
</fieldset>
