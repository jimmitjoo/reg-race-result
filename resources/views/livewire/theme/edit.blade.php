<?php

use App\Models\Event;
use App\Support\Theme;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/*
 * The organizer's theme, or an upplaga's on top of it (#112). Every change is a draft that the preview
 * shows at once; the pages change only when the organizer presses Publicera.
 */
new class extends Component {
    use WithFileUploads;

    public ?Event $event = null;

    public string $brand = '';
    public string $accent = '';
    public string $fonts = '';
    public string $shape = '';
    public ?string $logo = null;
    public $upload = null;

    public function mount(?Event $event = null): void
    {
        abort_unless(auth()->user()->organizer_id, 403);
        $this->event = $event?->exists ? $event : null;
        $draft = (array) ($this->owner()->theme_draft ?? $this->owner()->theme);
        $this->brand = (string) ($draft['brand'] ?? '');
        $this->accent = (string) ($draft['accent'] ?? '');
        $this->fonts = (string) ($draft['fonts'] ?? ($this->event ? '' : 'lopning'));
        $this->shape = (string) ($draft['shape'] ?? ($this->event ? '' : 'soft'));
        $this->logo = $draft['logo'] ?? null;
    }

    private function owner()
    {
        return $this->event ?? auth()->user()->organizer;
    }

    public function updated(string $property): void
    {
        if ($property === 'upload') {
            $this->validate(['upload' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:1024']], [], ['upload' => __('theme.logo')]);
            $this->logo = $this->upload->store('logos', 'public');
            $this->upload = null;
        }
        $this->brand = strtolower(trim($this->brand));
        $this->accent = strtolower(trim($this->accent));
        $this->validate([
            'brand' => ['nullable', 'regex:/^#[0-9a-f]{6}$/'],
            'accent' => ['nullable', 'regex:/^#[0-9a-f]{6}$/'],
            'fonts' => ['nullable', 'in:'.implode(',', array_keys(Theme::FONTS))],
            'shape' => ['nullable', 'in:'.implode(',', array_keys(Theme::SHAPES))],
        ], ['regex' => __('theme.hex')], ['brand' => __('theme.brand'), 'accent' => __('theme.accent')]);

        $this->owner()->update(['theme_draft' => $this->values()]);
    }

    public function removeLogo(): void
    {
        $this->logo = null;
        $this->owner()->update(['theme_draft' => $this->values()]);
    }

    public function publish(): void
    {
        $this->owner()->update(['theme' => $this->values(), 'theme_draft' => null]);
        $this->dispatch('theme-published');
    }

    private function values(): array
    {
        return array_filter(['brand' => $this->brand, 'accent' => $this->accent, 'fonts' => $this->fonts, 'shape' => $this->shape, 'logo' => $this->logo],
            fn ($value) => $value !== null && $value !== '');
    }

    public function with(): array
    {
        $organizer = auth()->user()->organizer;
        $theme = $this->event ? array_merge(Theme::for($organizer), $this->values()) : array_merge(Theme::DEFAULTS, $this->values());
        $brand = $theme['brand'] ? Theme::brand($theme['brand']) : null;
        $page = $this->event
            ? URL::signedRoute('public.event', ['organizer' => $organizer->slug, 'event' => $this->event->slug, 'forhandsvisning' => 1])
            : URL::signedRoute('public.organizer', ['organizer' => $organizer->slug, 'forhandsvisning' => 1]);

        return [
            'theme' => $theme,
            'adjusted' => $brand && $brand['adjusted'] ? $brand['color'] : null,
            'css' => Theme::allFaces().Theme::css($theme, '.theme-preview'),
            'logoUrl' => Theme::logoUrl($theme),
            'previewUrl' => $page,
            // Löpning.se's own fonts and shape are no change from a page without a theme.
            'unpublished' => array_diff_assoc($this->values(), ['fonts' => 'lopning', 'shape' => 'soft']) != array_diff_assoc(array_filter((array) $this->owner()->theme), ['fonts' => 'lopning', 'shape' => 'soft']),
            'name' => $organizer->name,
        ];
    }
}; ?>

<div class="flex max-w-5xl flex-col gap-6">
    <style>{!! $css !!}</style>
    <div>
        <flux:heading size="xl">{{ __('theme.title') }}</flux:heading>
        <flux:subheading>{{ $event ? __('theme.for_event', ['event' => $event->name]) : __('theme.for_organizer') }}</flux:subheading>
    </div>
    <flux:text>{{ $event ? __('theme.event_help') : __('theme.help') }}</flux:text>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start">
        <div class="flex flex-col gap-6">
            {{-- The logo, and the colour it suggests, read in the browser so nothing has to be installed on the server. --}}
            <div class="flex flex-col gap-3" x-data="{ suggested: null }">
                <flux:heading size="lg">{{ __('theme.logo') }}</flux:heading>
                <div class="flex flex-wrap items-center gap-4">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ __('theme.logo_of', ['name' => $name]) }}" class="h-16 w-auto max-w-[200px] rounded-field bg-white object-contain p-2 ring-1 ring-contour">
                        <flux:button size="sm" wire:click="removeLogo">{{ $event ? __('theme.use_organizers') : __('theme.remove') }}</flux:button>
                    @endif
                    <input type="file" wire:model="upload" accept="image/png,image/jpeg,image/webp" class="text-sm"
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
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (['brand', 'accent'] as $field)
                        <div class="flex items-end gap-2">
                            <input type="color" wire:model.live="{{ $field }}" value="{{ $this->{$field} ?: Theme::MOSS }}" class="h-10 w-12 shrink-0 cursor-pointer rounded-field border border-contour bg-white p-1" aria-label="{{ __("theme.{$field}") }}">
                            <flux:input wire:model.live.blur="{{ $field }}" :label="__("theme.{$field}")" placeholder="{{ $event && $field === 'brand' ? __('theme.inherited') : Theme::MOSS }}" class="flex-1" />
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
                <div class="grid gap-3 sm:grid-cols-2">
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
        </div>

        <aside class="flex flex-col gap-4 lg:sticky lg:top-6">
            <flux:heading size="lg">{{ __('theme.preview') }}</flux:heading>
            {{-- The theme on the same pieces the public pages are made of: heading, card, class choices and the pay button. --}}
            <div class="theme-preview lp-public flex flex-col gap-4 rounded-panel bg-ground p-5 text-ink" style="font-family: var(--font-sans)">
                @if ($logoUrl)<img src="{{ $logoUrl }}" alt="" class="h-10 w-auto self-start object-contain">@endif
                <h2 class="text-[30px] leading-tight font-normal tracking-[-0.02em]">Sylvesterloppet 2026</h2>
                <div class="flex flex-col gap-4 rounded-card bg-white p-5 shadow-rest">
                    <div class="flex items-baseline justify-between"><span class="text-[19px] font-medium">10 km</span><span class="text-[19px] font-medium">300 kr</span></div>
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-pill border-[1.5px] border-brand bg-brand px-4 py-2 text-[15px] font-medium text-brand-contrast">Kvinnor</span>
                        <span class="rounded-pill border-[1.5px] border-contour px-4 py-2 text-[15px] font-medium">Män</span>
                    </div>
                    <span class="rounded-pill border-[1.5px] border-contour px-4 py-2.5 text-[15px] text-ink-soft">Anna Karlsson</span>
                    <span class="inline-flex h-11 items-center justify-center rounded-pill bg-brand text-[15px] font-semibold text-brand-contrast">{{ __('registration.pay', ['price' => '300 kr']) }}</span>
                </div>
                <p class="rounded-card bg-brand-surface px-4 py-3 text-[15px]">{{ __('theme.surface_sample') }}</p>
            </div>
            <flux:button :href="$previewUrl" target="_blank" icon="eye">{{ __('theme.open_preview') }}</flux:button>

            <div class="flex items-center gap-3">
                <flux:button variant="primary" wire:click="publish" :disabled="! $unpublished">{{ __('theme.publish') }}</flux:button>
                <x-action-message on="theme-published">{{ __('theme.published') }}</x-action-message>
                @if ($unpublished)<flux:text class="text-sm">{{ __('theme.unpublished') }}</flux:text>@endif
            </div>
        </aside>
    </div>
</div>
