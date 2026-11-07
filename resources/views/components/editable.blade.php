{{-- A block's text as the page shows it, written straight into it in the editor (#115): one line, saved as a draft while typing; left out of the page when empty. --}}
@props(['editor' => false, 'block', 'field', 'as' => 'p', 'placeholder' => '', 'fallback' => null])
@php($value = (string) ($block[$field] ?? ''))
@if ($editor)
    <{{ $as }} {{ $attributes->class('cursor-text rounded-[4px] outline-offset-4 focus:outline-2 focus:outline-current/40') }}
        contenteditable="plaintext-only" role="textbox" aria-label="{{ $placeholder }}" data-placeholder="{{ $placeholder }}" spellcheck="true"
        {{-- A field with a default shows it as text; written back unchanged it stays the default. --}}
        wire:ignore x-data="{ empty: {{ trim($value) === '' && ! $fallback ? 'true' : 'false' }}, value: () => { const text = $el.innerText.trim(); return text === @js((string) $fallback) ? '' : text } }"
        x-bind:class="empty && 'before:pointer-events-none before:opacity-55 before:content-[attr(data-placeholder)]'"
        x-on:keydown.enter.prevent
        x-on:input="empty = $el.innerText.trim() === ''"
        x-on:input.debounce.400ms="$wire.setField('{{ $block['id'] }}', '{{ $field }}', value())"
        x-on:blur="$wire.setField('{{ $block['id'] }}', '{{ $field }}', value())">{{ trim($value) ?: $fallback }}</{{ $as }}>
@elseif (trim($value) !== '' || $fallback)
    <{{ $as }} {{ $attributes }}>{{ trim($value) ?: $fallback }}</{{ $as }}>
@endif
