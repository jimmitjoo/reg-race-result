{{-- A block's text, or a text of one of its items, as the page shows it, written straight into it in the editor (#115): saved as a draft while typing; left out of the page when empty. One line unless multiline. --}}
@props(['editor' => false, 'block', 'field', 'item' => null, 'as' => 'p', 'placeholder' => '', 'fallback' => null, 'multiline' => false])
@php($value = (string) (($item ?? $block)[$field] ?? ''))
@php($save = $item ? "\$wire.setItem('{$block['id']}', '{$item['id']}', '{$field}', value())" : "\$wire.setField('{$block['id']}', '{$field}', value())")
@if ($editor)
    <{{ $as }} {{ $attributes->class('cursor-text rounded-[4px] outline-offset-4 focus:outline-2 focus:outline-current/40') }}
        contenteditable="plaintext-only" role="textbox" aria-label="{{ $placeholder }}" data-placeholder="{{ $placeholder }}" spellcheck="true"
        {{-- A field with a default shows it as text; written back unchanged it stays the default. --}}
        wire:ignore x-data="{ empty: {{ trim($value) === '' && ! $fallback ? 'true' : 'false' }}, value: () => { const text = $el.innerText.trim(); return text === @js((string) $fallback) ? '' : text } }"
        x-bind:class="empty && 'before:pointer-events-none before:opacity-55 before:content-[attr(data-placeholder)]'"
        @unless ($multiline) x-on:keydown.enter.prevent @endunless
        x-on:input="empty = $el.innerText.trim() === ''"
        x-on:input.debounce.400ms="{{ $save }}"
        x-on:blur="{{ $save }}">{{ trim($value) ?: $fallback }}</{{ $as }}>
@elseif (trim($value) !== '' || $fallback)
    <{{ $as }} {{ $attributes }}>{{ trim($value) ?: $fallback }}</{{ $as }}>
@endif
