{{-- The text written in place, with a toolbar for what a text may hold while it has focus. --}}
<div wire:ignore x-data="richText('{{ $block['id'] }}', @js(\App\Support\Page::html($block['body'])), @js(__('page.body_placeholder')), @js(__('page.link_ask')))" class="flex flex-col gap-2">
    <div x-show="focused" x-cloak role="toolbar" aria-label="{{ __('page.format') }}"
        class="sticky top-2 z-10 flex flex-wrap items-center gap-0.5 self-start rounded-field bg-white p-1 text-sm text-zinc-800 shadow-rest ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-700">
        @foreach ([
            ['bold', 'toggleBold', [], 'B', 'font-bold'],
            ['italic', 'toggleItalic', [], 'I', 'italic font-serif'],
            ['heading', 'toggleHeading', [['level' => 3]], 'H', 'font-semibold'],
            ['bulletList', 'toggleBulletList', [], '•', 'text-lg leading-none'],
            ['orderedList', 'toggleOrderedList', [], '1.', 'tabular-nums'],
            ['blockquote', 'toggleBlockquote', [], '”', 'text-lg leading-none'],
        ] as [$mark, $command, $args, $label, $style])
            <button type="button" x-on:mousedown.prevent x-on:click="toggle('{{ $command }}', ...@js($args))"
                x-bind:aria-pressed="active('{{ $mark }}', @js($args[0] ?? []))" title="{{ __("page.format_{$mark}") }}" aria-label="{{ __("page.format_{$mark}") }}"
                class="flex size-8 items-center justify-center rounded-[6px] hover:bg-zinc-100 aria-pressed:bg-zinc-200 dark:hover:bg-zinc-800 dark:aria-pressed:bg-zinc-700 {{ $style }}">{{ $label }}</button>
        @endforeach
        <button type="button" x-on:mousedown.prevent x-on:click="link()" x-bind:aria-pressed="active('link')" title="{{ __('page.format_link') }}" aria-label="{{ __('page.format_link') }}"
            class="flex size-8 items-center justify-center rounded-[6px] hover:bg-zinc-100 aria-pressed:bg-zinc-200 dark:hover:bg-zinc-800 dark:aria-pressed:bg-zinc-700"><flux:icon.link class="size-4" /></button>
    </div>
    <div x-ref="body" class="{{ $prose }} cursor-text"></div>
</div>
