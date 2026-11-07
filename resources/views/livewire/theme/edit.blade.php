<?php

use App\Models\Event;
use App\Support\Theme;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/*
 * The organizer's theme, or an upplaga's on top of it (#112). Every change is a draft that the preview
 * shows at once; the pages change only when the organizer presses Publicera. As a panel in the page
 * editor the page itself is the preview, and the editor's Publicera publishes it with the page.
 */
new class extends Component {
    use WithFileUploads;

    public ?Event $event = null;

    public bool $panel = false;

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
        return Theme::owner(auth()->user()->organizer, $this->event);
    }

    /** An SVG can carry script: only its sanitized drawing is kept, without scripts, events or outside references (#122). */
    private function storeSvg(): string
    {
        $sanitizer = new \enshrined\svgSanitize\Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize($this->upload->get());
        if (! $clean) {
            throw \Illuminate\Validation\ValidationException::withMessages(['upload' => __('theme.svg_invalid')]);
        }
        $path = 'logos/'.\Illuminate\Support\Str::random(40).'.svg';
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, $clean);

        return $path;
    }

    public function updated(string $property): void
    {
        if ($property === 'upload') {
            $this->validate(['upload' => ['image:allow_svg', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024']], [], ['upload' => __('theme.logo')]);
            $this->logo = $this->upload->getMimeType() === 'image/svg+xml' ? $this->storeSvg() : $this->upload->store('logos', 'public');
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

        $this->saveDraft();
    }

    public function removeLogo(): void
    {
        $this->logo = null;
        $this->saveDraft();
    }

    private function saveDraft(): void
    {
        $this->owner()->update(['theme_draft' => $this->values()]);
        // The page editor around the panel draws the page anew in it.
        $this->dispatch('theme-changed');
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

<div @class(['flex flex-col gap-6', 'max-w-5xl' => ! $panel])>
    <style>{!! $css !!}</style>
    @if ($panel)
        @include('livewire.theme.controls')
    @else
    <div>
        <flux:heading size="xl">{{ __('theme.title') }}</flux:heading>
        <flux:subheading>{{ $event ? __('theme.for_event', ['event' => $event->series?->name ?? $event->name]) : __('theme.for_organizer') }}</flux:subheading>
    </div>
    <flux:text>{{ $event ? __($event->series ? 'theme.series_help' : 'theme.event_help') : __('theme.help') }}</flux:text>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start">
        <div class="flex flex-col gap-6">
            @include('livewire.theme.controls')
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
    @endif
</div>
