<?php

use App\Models\Event;
use App\Support\Page;
use App\Support\Theme;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/*
 * The block editor for the organizer's page (#115): the page itself, at its own width, with the tools floating
 * over it; the bar above switches between showing it and editing it. A block is changed where it stands,
 * added with + and moved by dragging or with the arrows. Every change is a draft; Publicera makes it the page, once nothing on it would shut someone out. The same for an upplaga's page, around its
 * registration, each block shown always or only while the upplaga is in a state.
 */
new #[Layout('components.layouts.public')] class extends Component {
    use WithFileUploads;

    public ?Event $event = null;

    public array $blocks = [];

    // The selected block, whose settings show beside the page.
    public ?string $editing = null;

    // The look's panel open instead of a block's: logo, colours, fonts and shape.
    #[\Livewire\Attributes\Url(as: 'utseende', except: false)]
    public bool $theming = false;

    // Where the block chooser is open: the place a new block goes (0 first).
    public ?int $addingAt = null;

    // The block last removed and where it stood, until Ångra or the next change.
    public ?array $removed = null;

    public $upload = null;

    public $backgroundUpload = null;

    public function mount(?Event $event = null): void
    {
        abort_unless(auth()->user()->organizer_id, 403);
        $this->event = $event?->exists ? $event : null;
        $this->blocks = Page::for($this->owner(), draft: true);
    }

    private function organizer(): App\Models\Organizer
    {
        return auth()->user()->organizer;
    }

    /** Whose page this is: the upplaga's, or the organizer's. */
    private function owner(): App\Models\Organizer|Event
    {
        return $this->event ?? $this->organizer();
    }

    /** The blocks that can be added: the page's kind, and one registration at most. */
    private function types(): array
    {
        return array_values(array_filter(Page::types($this->owner()), fn ($type) => $type !== 'registration' || ! collect($this->blocks)->contains('type', 'registration')));
    }

    private function index(string $id): int
    {
        $at = collect($this->blocks)->search(fn ($block) => $block['id'] === $id);
        abort_if($at === false, 404);

        return $at;
    }

    private function save(): void
    {
        $this->validate(Page::rules());
        $this->owner()->update(['page_draft' => $this->blocks]);
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'blocks.')) {
            $this->save();
        }
    }

    // ponytail: the image is kept as uploaded and the replaced one stays on disk; sizes and clean-up come with the media library (#113).
    public function updatedUpload(): void
    {
        $this->validate(['upload' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:4096']], [], ['upload' => __('page.image')]);
        $at = $this->index((string) $this->editing);
        [$width, $height] = getimagesize($this->upload->getRealPath()) ?: [null, null];
        $this->blocks[$at] = array_merge($this->blocks[$at], ['image' => $this->upload->store('pages', 'public'), 'width' => $width, 'height' => $height]);
        $this->upload = null;
        $this->save();
    }

    public function updatedBackgroundUpload(): void
    {
        $this->validate(['backgroundUpload' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:4096']], [], ['backgroundUpload' => __('page.background_image')]);
        $at = $this->index((string) $this->editing);
        $this->blocks[$at] = array_merge($this->blocks[$at], ['background' => 'image', 'background_image' => $this->backgroundUpload->store('pages', 'public')]);
        $this->backgroundUpload = null;
        $this->save();
    }

    public function removeImage(string $id): void
    {
        $at = $this->index($id);
        $this->blocks[$at] = array_merge($this->blocks[$at], ['image' => null, 'width' => null, 'height' => null]);
        $this->save();
    }

    public function edit(?string $id): void
    {
        $this->editing = $id === null ? null : $this->blocks[$this->index($id)]['id'];
        [$this->addingAt, $this->theming] = [null, false];
        $this->resetValidation();
    }

    /** A question and answer, or a figure, written straight into a block on the page. */
    public function setItem(string $id, string $itemId, string $field, string $value): void
    {
        $at = $this->index($id);
        abort_unless(in_array($field, Page::ITEM_FIELDS[$this->blocks[$at]['type']] ?? [], true), 404);
        $item = collect($this->blocks[$at]['items'])->search(fn ($item) => $item['id'] === $itemId);
        abort_if($item === false, 404);
        $this->blocks[$at]['items'][$item][$field] = $value;
        $this->save();
    }

    public function addItem(string $id): void
    {
        $at = $this->index($id);
        abort_unless(isset(Page::ITEM_FIELDS[$this->blocks[$at]['type']]), 404);
        $this->blocks[$at]['items'][] = Page::item($this->blocks[$at]['type']);
        $this->save();
    }

    public function removeItem(string $id, string $itemId): void
    {
        $at = $this->index($id);
        $this->blocks[$at]['items'] = array_values(array_filter($this->blocks[$at]['items'], fn ($item) => $item['id'] !== $itemId));
        $this->save();
    }

    /** Text written straight into a block on the page. */
    public function setField(string $id, string $field, string $value): void
    {
        $at = $this->index($id);
        abort_unless(in_array($field, Page::INLINE[$this->blocks[$at]['type']], true), 404);
        $this->blocks[$at][$field] = $field === 'body' ? Page::html($value) : $value;
        $this->save();
    }

    public function choose(?int $at): void
    {
        $this->addingAt = $this->addingAt === $at ? null : $at;
        $this->editing = null;
    }

    public function add(string $type, int $at): void
    {
        abort_unless(in_array($type, $this->types(), true), 404);
        $block = Page::block($type);
        array_splice($this->blocks, max(0, min(count($this->blocks), $at)), 0, [$block]);
        [$this->addingAt, $this->removed] = [null, null];
        // Blocks with something to fill in open for it at once.
        $this->editing = $block['id'];
        $this->save();
    }

    public function remove(string $id): void
    {
        $at = $this->index($id);
        $this->removed = ['at' => $at, 'block' => $this->blocks[$at]];
        array_splice($this->blocks, $at, 1);
        $this->editing = null;
        $this->save();
    }

    public function undo(): void
    {
        if ($this->removed) {
            array_splice($this->blocks, min($this->removed['at'], count($this->blocks)), 0, [$this->removed['block']]);
            $this->removed = null;
            $this->save();
        }
    }

    /** A block dragged to a place (0 first). */
    public function sortBlock(string $id, int $position): void
    {
        $this->place($id, fn () => $position);
    }

    /** One step up (-1) or down (1), for the arrows: the way without dragging. */
    public function move(string $id, int $step): void
    {
        $this->place($id, fn ($at) => $at + $step);
    }

    private function place(string $id, Closure $to): void
    {
        $at = $this->index($id);
        [$block] = array_splice($this->blocks, $at, 1);
        array_splice($this->blocks, max(0, min(count($this->blocks), $to($at))), 0, [$block]);
        $this->removed = null;
        $this->save();
    }

    public function theme(): void
    {
        [$this->theming, $this->editing, $this->addingAt] = [! $this->theming, null, null];
    }

    /** A change in the look's panel: the page is drawn anew in it. */
    #[On('theme-changed')]
    public function themeChanged(): void {}

    /** The page and its look together, so that what the editor shows is what runners get. */
    public function publish(): void
    {
        if (Page::problems($this->blocks, Page::forms($this->organizer())->keys()->all())) {
            return;
        }
        $this->validate(Page::rules());
        if ($this->owner()->page_draft !== null) {
            $this->owner()->update(['page' => $this->blocks, 'page_draft' => null]);
        }
        $look = Theme::owner($this->organizer(), $this->event);
        if ($look->theme_draft !== null) {
            $look->update(['theme' => $look->theme_draft, 'theme_draft' => null]);
        }
        $this->removed = null;
        $this->dispatch('page-published');
    }

    /** Back to the published page. */
    public function discard(): void
    {
        $this->owner()->update(['page_draft' => null]);
        // Anew, since the text written in place is the browser's until then.
        $this->event ? $this->redirectRoute('events.page', $this->event, navigate: true) : $this->redirectRoute('page.edit', navigate: true);
    }

    public function rendering($view): void
    {
        $view->title(__('page.editing', ['page' => $this->event?->name ?? $this->organizer()->name]))
            ->layoutData(['organizer' => $this->organizer(), 'event' => $this->event, 'editing' => true]);
    }

    public function with(): array
    {
        $organizer = $this->organizer();
        $event = $this->event;
        $theme = Theme::for($organizer, $event, true);
        $forms = Page::forms($organizer);

        return [
            'organizer' => $organizer,
            'edition' => $event,
            'types' => $this->types(),
            'editor' => true,
            'spans' => Page::spans($this->blocks),
            'spaces' => Page::spaces($this->blocks),
            'selected' => collect($this->blocks)->firstWhere('id', $this->editing),
            'problems' => collect(Page::problems($this->blocks, $forms->keys()->all()))->groupBy('id'),
            'forms' => $forms,
            'unpublished' => $this->owner()->page_draft !== null || Theme::owner($organizer, $event)->theme_draft !== null,
            // The editor shows the theme's draft; the page itself not until the theme too is published.
            // The club's look, under an upplaga's page, is published on the club's own page.
            'clubDraft' => $event && $organizer->theme_draft !== null,
            'css' => Theme::css($theme),
            'logo' => Theme::logoUrl($theme),
            'pageUrl' => $event ? $event->publicUrl() : route('public.organizer', $organizer->slug),
            'previewUrl' => $event
                ? URL::signedRoute('public.event', ['organizer' => $organizer->slug, 'event' => $event->slug, 'forhandsvisning' => 1])
                : URL::signedRoute('public.organizer', ['organizer' => $organizer->slug, 'forhandsvisning' => 1]),
            ...($event ? [
                'races' => $event->races()->with('raceClasses')->ordered()->get(),
                // The hero's way to the registration, shown as it will be while open.
                'registering' => collect($this->blocks)->contains('type', 'registration'),
                'next' => null,
                ...Page::edition($event, true),
            ] : Page::events($organizer)),
        ];
    }
}; ?>

<div x-data="{ help: true }">
    @assets
        @vite('resources/js/editor.js')
    @endassets
    {{-- The look as drafted, over the published one the page was loaded with, so a change in its panel shows at once. --}}
    @if ($css)<style>{!! $css !!}</style>@endif

    {{-- The bar the published page has, switched to Redigera, with the editor's actions in it. --}}
    <x-admin-bar :$organizer :event="$edition" editing class="bleed sticky top-0 z-50">
        <span class="ms-2 hidden truncate font-semibold text-white md:inline">{{ $edition?->name ?? $organizer->name }}</span>
        <span class="hidden truncate text-zinc-400 lg:inline" aria-live="polite">
            <x-action-message on="page-published">{{ __('page.published') }}</x-action-message>
            @if ($unpublished){{ __('page.draft_short') }}@endif
        </span>
        <span class="ms-auto"></span>
        <button type="button" wire:click="theme" aria-pressed="{{ $theming ? 'true' : 'false' }}" @class(['inline-flex h-8 shrink-0 items-center gap-1.5 rounded-[6px] px-2.5 font-medium', 'bg-white text-zinc-900' => $theming, 'hover:bg-white/10 hover:text-white' => ! $theming])><flux:icon.paint-brush variant="micro" /><span class="max-sm:sr-only">{{ __('theme.title') }}</span></button>
        @if ($unpublished)
            <button type="button" wire:click="discard" wire:confirm="{{ __('page.discard_confirm') }}" class="inline-flex h-8 shrink-0 items-center rounded-[6px] px-2.5 font-medium hover:bg-white/10 hover:text-white">{{ __('page.discard') }}</button>
        @endif
        <a href="{{ $previewUrl }}" target="_blank" class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-[6px] px-2.5 font-medium ring-1 ring-white/25 hover:text-white hover:ring-white/60"><flux:icon.arrow-top-right-on-square variant="micro" /><span class="max-sm:sr-only">{{ __('page.preview') }}</span></a>
        <button type="button" wire:click="publish" @disabled(! $unpublished || $problems->isNotEmpty())
            class="inline-flex h-8 shrink-0 items-center rounded-[6px] bg-white px-3.5 font-semibold text-zinc-900 hover:bg-zinc-200 disabled:cursor-not-allowed disabled:opacity-40">{{ __('page.publish') }}</button>
    </x-admin-bar>

    {{-- The page itself, at its own width, written into where it stands; its links are not followed here. --}}
    <div class="pt-8 sm:pt-12" x-on:click.capture="$event.target.closest('a') && $event.preventDefault()">
        @if (! $blocks)
            @include('livewire.page.adder', ['at' => 0, 'inline' => true])
        @endif
        @unless (collect($blocks)->contains('type', 'hero'))
            <h1 class="sr-only">{{ $edition?->name ?? $organizer->name }}</h1>
        @endunless
        <div wire:sort="sortBlock" class="grid grid-cols-12 gap-x-6">
            @foreach ($blocks as $i => $block)
                @php($chosen = $editing === $block['id'])
                <div wire:key="edit-{{ $block['id'] }}" wire:sort:item="{{ $block['id'] }}" id="block-{{ $block['id'] }}"
                    @class(['group relative min-w-0', \App\Support\Page::cell($spans[$block['id']]), $spaces[$block['id']], 'z-40' => $addingAt === $i + 1, 'z-10' => $chosen && $addingAt !== $i + 1, 'hover:z-20 focus-within:z-20' => ! $chosen])>
                    <div @unless ($chosen) wire:click="edit('{{ $block['id'] }}')" @endunless
                        @class(['relative h-full rounded-[2px] outline-offset-[6px]', 'outline-2 outline-brand' => $chosen, 'outline-2 outline-dashed outline-peach' => ! $chosen && $problems->has($block['id']), 'cursor-pointer outline-1 outline-transparent hover:outline-dashed hover:outline-zinc-400' => ! $chosen && ! $problems->has($block['id'])])>
                        {{-- Floating above the block, clear of its heading (inside the first, at the top of the page); when it shows, always in sight, for the page here is every state at once. --}}
                        <div @class(['pointer-events-none absolute z-30 flex flex-wrap items-center gap-2', 'top-2 left-2' => $loop->first, 'bottom-full left-0 mb-2.5' => ! $loop->first])>
                            <div @class(['pointer-events-auto flex items-center gap-1 rounded-field bg-white px-1.5 py-1 text-sm text-zinc-800 shadow-raised ring-1 ring-zinc-200 transition', 'opacity-0 group-hover:opacity-100 group-focus-within:opacity-100' => ! $chosen])>
                                @include('livewire.events.drag-handle')
                                <span class="px-1 font-medium">{{ __("page.type.{$block['type']}") }}</span>
                                <flux:button size="xs" variant="ghost" icon="arrow-up" wire:click.stop="move('{{ $block['id'] }}', -1)" :disabled="$loop->first" :aria-label="__('page.move_up')" />
                                <flux:button size="xs" variant="ghost" icon="arrow-down" wire:click.stop="move('{{ $block['id'] }}', 1)" :disabled="$loop->last" :aria-label="__('page.move_down')" />
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click.stop="remove('{{ $block['id'] }}')" :aria-label="__('page.remove')" />
                            </div>
                            @if ($edition && $block['when'] !== 'always')
                                <span class="inline-flex items-center gap-1 rounded-pill bg-zinc-900 px-2.5 py-1 text-[12px] font-medium text-white shadow-raised"><flux:icon.clock variant="micro" />{{ __("page.whens.{$block['when']}") }}</span>
                            @endif
                        </div>
                        @include('blocks.block', ['first' => $loop->first])
                    </div>
                    @include('livewire.page.adder', ['at' => $i + 1, 'chosen' => $chosen])
                </div>
            @endforeach
        </div>
    </div>

    {{-- The look, or the chosen block's settings, floating beside the page; at the foot of the screen on a phone. --}}
    @if ($theming)
        <aside wire:key="theme-panel" class="fixed inset-x-0 bottom-0 z-40 max-h-[60vh] overflow-y-auto rounded-panel bg-white p-5 text-sm text-zinc-800 shadow-raised ring-1 ring-zinc-200 lg:inset-x-auto lg:top-14 lg:right-4 lg:bottom-4 lg:max-h-none lg:w-[380px]">
            <div class="mb-4 flex items-start justify-between gap-2">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('theme.title') }}</flux:heading>
                    <flux:text class="text-sm">{{ $edition ? __($edition->series ? 'theme.series_help' : 'theme.event_help') : __('theme.help') }}</flux:text>
                </div>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="theme" :aria-label="__('page.close')" />
            </div>
            @livewire('theme.edit', ['event' => $edition, 'panel' => true], key('theme-'.($edition?->id ?? 'club')))
        </aside>
    @elseif ($selected)
        <aside wire:key="inspector-panel" class="fixed inset-x-0 bottom-0 z-40 max-h-[60vh] overflow-y-auto shadow-raised lg:inset-x-auto lg:top-14 lg:right-4 lg:bottom-4 lg:max-h-none lg:w-[340px]">
            @include('livewire.page.inspector')
        </aside>
    @else
        <aside wire:key="help-panel" x-show="help" class="fixed right-4 bottom-4 z-40 hidden w-80 flex-col gap-2 rounded-panel bg-white p-5 text-sm text-zinc-700 shadow-raised ring-1 ring-zinc-200 sm:flex">
            <div class="flex items-center justify-between gap-2">
                <p class="font-semibold text-zinc-900">{{ __('page.how_title') }}</p>
                <flux:button size="xs" variant="ghost" icon="x-mark" x-on:click="help = false" :aria-label="__('page.close')" />
            </div>
            <ul class="flex list-disc flex-col gap-1.5 ps-5">
                <li>{{ __('page.how_write') }}</li>
                <li>{{ __('page.how_select') }}</li>
                <li>{{ __('page.how_add') }}</li>
                <li>{{ __('page.how_publish') }}</li>
            </ul>
        </aside>
    @endif

    {{-- What needs doing, and Ångra, floating at the foot of the page. --}}
    <div class="fixed bottom-4 left-4 z-40 flex max-w-sm flex-col gap-2">
        @if ($problems->isNotEmpty())
            <div class="rounded-panel bg-white p-4 text-sm text-zinc-800 shadow-raised ring-1 ring-peach" role="status">
                <p class="font-semibold">{{ __('page.problems_title') }}</p>
                <ul class="mt-1 flex flex-col gap-1">
                    @foreach ($problems as $id => $list)
                        @php($block = collect($blocks)->firstWhere('id', $id))
                        @foreach ($list as $problem)
                            <li><button type="button" wire:click="edit('{{ $id }}')" x-on:click="document.getElementById('block-{{ $id }}')?.scrollIntoView({ block: 'center', behavior: 'smooth' })" class="text-left underline underline-offset-2">{{ __("page.type.{$block['type']}") }}: {{ __("page.problem.{$problem['problem']}") }}</button></li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($clubDraft)
            <div class="rounded-panel bg-white p-4 text-sm text-zinc-800 shadow-raised ring-1 ring-zinc-200">
                <p class="font-semibold">{{ __('page.theme_draft') }}</p>
                <p class="mt-1">{{ __('page.theme_draft_help') }} <a href="{{ route('page.edit', ['utseende' => 1]) }}" class="font-medium underline underline-offset-2">{{ __('page.theme_draft_link') }}</a></p>
            </div>
        @endif
        @if ($removed)
            <div class="flex items-center gap-3 self-start rounded-panel bg-zinc-900 px-4 py-2.5 text-sm text-white shadow-raised" role="status">
                {{ __('page.removed', ['type' => __("page.type.{$removed['block']['type']}")]) }}
                <button type="button" wire:click="undo" class="font-semibold underline underline-offset-2">{{ __('page.undo') }}</button>
            </div>
        @endif
    </div>
</div>
