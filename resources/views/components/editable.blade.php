{{-- A block's text as the page shows it, written straight into it in the editor (#115): one line, saved as a draft while typing; left out of the page when empty. --}}
@props(['editor' => false, 'block', 'field', 'as' => 'p', 'placeholder' => '', 'fallback' => null])
@php($value = (string) ($block[$field] ?? ''))
@if ($editor)
    <{{ $as }} {{ $attributes->class('cursor-text rounded-[4px] outline-offset-4 focus:outline-2 focus:outline-current/40') }}
        contenteditable="plaintext-only" role="textbox" aria-label="{{ $placeholder }}" data-placeholder="{{ $placeholder }}" spellcheck="true"
        wire:ignore x-data="{ empty: {{ trim($value) === '' ? 'true' : 'false' }} }"
        x-bind:class="empty && 'before:pointer-events-none before:opacity-55 before:content-[attr(data-placeholder)]'"
        x-on:keydown.enter.prevent
        x-on:input="empty = $el.innerText.trim() === ''"
        x-on:input.debounce.400ms="$wire.setField('{{ $block['id'] }}', '{{ $field }}', $el.innerText.trim())"
        x-on:blur="$wire.setField('{{ $block['id'] }}', '{{ $field }}', $el.innerText.trim())">{{ $value }}</{{ $as }}>
@elseif (trim($value) !== '' || $fallback)
    <{{ $as }} {{ $attributes }}>{{ trim($value) ?: $fallback }}</{{ $as }}>
@endif
