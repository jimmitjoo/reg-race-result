<?php

use App\Models\Event;
use App\Support\Page;
use App\Support\Theme;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/*
 * The block editor for the organizer's page (#115): the page as it will look, a block changed where it
 * stands, added with + and moved by dragging or with the arrows. Every change is a draft; Publicera makes
 * it the page, once nothing on it would shut someone out. The same for an upplaga's page, around its
 * registration, each block shown always or only while the upplaga is in a state.
 */
new class extends Component {
    use WithFileUploads;

    public ?Event $event = null;

    public array $blocks = [];

    // The selected block, whose settings show beside the page.
    public ?string $editing = null;

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
        $this->addingAt = null;
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

    public function publish(): void
    {
        if (Page::problems($this->blocks, Page::forms($this->organizer())->keys()->all())) {
            return;
        }
        $this->validate(Page::rules());
        $this->owner()->update(['page' => $this->blocks, 'page_draft' => null]);
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
            'unpublished' => $this->owner()->page_draft !== null,
            // The editor shows the theme's draft; the page itself not until the theme too is published.
            'themeDraft' => $organizer->theme_draft !== null || $event?->theme_draft !== null,
            'themeUrl' => $event && $event->theme_draft !== null ? route('events.theme', $event) : route('theme.edit'),
            'css' => Theme::css($theme, '.theme-preview'),
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

<div class="flex flex-col gap-6">
    @assets
        @vite('resources/js/editor.js')
    @endassets
    @if ($css)<style>{!! $css !!}</style>@endif

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex max-w-2xl flex-col gap-1">
            <flux:heading size="xl">{{ $edition ? __('page.event_title_of', ['event' => $edition->name]) : __('page.title') }}</flux:heading>
            <flux:text>{{ $edition ? __('page.event_help') : __('page.help') }}</flux:text>
            <a href="{{ $pageUrl }}" target="_blank" class="self-start text-sm text-zinc-500 underline underline-offset-2 hover:text-zinc-800 dark:hover:text-zinc-200">{{ preg_replace('#^https?://#', '', $pageUrl) }}</a>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm text-zinc-500" aria-live="polite">
                <x-action-message on="page-published">{{ __('page.published') }}</x-action-message>
                @if ($unpublished){{ __('page.draft') }}@endif
            </span>
            @if ($unpublished)
                <flux:button size="sm" variant="ghost" wire:click="discard" wire:confirm="{{ __('page.discard_confirm') }}">{{ __('page.discard') }}</flux:button>
            @endif
            <flux:button :href="$previewUrl" target="_blank" icon="eye">{{ __('page.preview') }}</flux:button>
            <flux:button variant="primary" wire:click="publish" :disabled="! $unpublished || $problems->isNotEmpty()">{{ __('page.publish') }}</flux:button>
        </div>
    </div>

    @if ($problems->isNotEmpty())
        <flux:callout color="amber" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('page.problems_title') }}</flux:callout.heading>
            <flux:callout.text>
                <ul class="flex flex-col gap-1">
                    @foreach ($problems as $id => $list)
                        @php($block = collect($blocks)->firstWhere('id', $id))
                        @foreach ($list as $problem)
                            <li><button type="button" wire:click="edit('{{ $id }}')" class="underline underline-offset-2">{{ __("page.type.{$block['type']}") }}: {{ __("page.problem.{$problem['problem']}") }}</button></li>
                        @endforeach
                    @endforeach
                </ul>
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($themeDraft)
        <flux:callout color="amber" icon="paint-brush">
            <flux:callout.heading>{{ __('page.theme_draft') }}</flux:callout.heading>
            <flux:callout.text>{{ __('page.theme_draft_help') }} <flux:link :href="$themeUrl" wire:navigate>{{ __('page.theme_draft_link') }}</flux:link></flux:callout.text>
        </flux:callout>
    @endif

    @if ($removed)
        <div class="flex items-center gap-3 self-start rounded-panel bg-zinc-800 px-4 py-2 text-sm text-white dark:bg-zinc-200 dark:text-zinc-900" role="status">
            {{ __('page.removed', ['type' => __("page.type.{$removed['block']['type']}")]) }}
            <button type="button" wire:click="undo" class="font-semibold underline underline-offset-2">{{ __('page.undo') }}</button>
        </div>
    @endif

    {{-- The page as it will look, in the organizer's theme. --}}
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
        {{-- The page as runners see it, always light, written into where it stands; its links are not followed here. --}}
        <div class="theme-preview lp-editor min-w-0 rounded-panel bg-ground p-3 sm:p-6" x-on:click.capture="$event.target.closest('a') && $event.preventDefault()">
            @include('livewire.page.adder', ['at' => 0])
            <div wire:sort="sortBlock" class="grid grid-cols-12 gap-x-4">
                @foreach ($blocks as $i => $block)
                    @php($chosen = $editing === $block['id'])
                    <div wire:key="edit-{{ $block['id'] }}" wire:sort:item="{{ $block['id'] }}" id="block-{{ $block['id'] }}" class="group flex min-w-0 flex-col {{ \App\Support\Page::cell($spans[$block['id']]) }} {{ $spaces[$block['id']] }}">
                        <div @unless ($chosen) wire:click="edit('{{ $block['id'] }}')" @endunless
                            @class(['relative flex flex-col gap-2 rounded-[calc(var(--radius-card)+10px)] p-2 ring-1 transition', 'ring-2 ring-brand' => $chosen, 'ring-peach' => ! $chosen && $problems->has($block['id']), 'cursor-pointer ring-transparent hover:ring-zinc-300' => ! $chosen && ! $problems->has($block['id'])])>
                            {{-- When the block shows, always in sight: the page the editor shows is every state at once. --}}
                            <div class="flex flex-wrap items-center gap-2">
                                <div @class(['flex items-center gap-1 self-start rounded-field bg-white px-1.5 py-1 text-sm text-zinc-800 shadow-rest ring-1 ring-zinc-200 transition dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-700', 'opacity-0 group-hover:opacity-100 group-focus-within:opacity-100' => ! $chosen])>
                                    @include('livewire.events.drag-handle')
                                    <span class="px-1 font-medium">{{ __("page.type.{$block['type']}") }}</span>
                                    <flux:button size="xs" variant="ghost" icon="arrow-up" wire:click.stop="move('{{ $block['id'] }}', -1)" :disabled="$loop->first" :aria-label="__('page.move_up')" />
                                    <flux:button size="xs" variant="ghost" icon="arrow-down" wire:click.stop="move('{{ $block['id'] }}', 1)" :disabled="$loop->last" :aria-label="__('page.move_down')" />
                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click.stop="remove('{{ $block['id'] }}')" :aria-label="__('page.remove')" />
                                </div>
                                @if ($edition && $block['when'] !== 'always')
                                    <span class="inline-flex items-center gap-1 rounded-pill bg-zinc-800 px-2.5 py-1 text-[12px] font-medium text-white dark:bg-zinc-200 dark:text-zinc-900"><flux:icon.clock variant="micro" />{{ __("page.whens.{$block['when']}") }}</span>
                                @endif
                            </div>
                            <div class="lp-public text-ink" style="font-family: var(--font-sans)">
                                @include('blocks.block')
                            </div>
                        </div>
                        @include('livewire.page.adder', ['at' => $i + 1])
                    </div>
                @endforeach
            </div>
        </div>

        <aside class="lg:sticky lg:top-4">
            @include('livewire.page.inspector')
        </aside>
    </div>
</div>
