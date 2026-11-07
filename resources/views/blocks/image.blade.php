{{-- One picture, as wide as the text or wider, with alt text and an optional caption. --}}
@if ($block['image'])
    <figure @class(['mx-auto flex w-full flex-col gap-3', 'max-w-[720px]' => $block['size'] !== 'wide'])>
        <img src="{{ Storage::disk('public')->url($block['image']) }}" alt="{{ $block['alt'] }}" loading="lazy" @if ($block['width']) width="{{ $block['width'] }}" height="{{ $block['height'] }}" @endif
            class="h-auto w-full rounded-card object-cover">
        <x-editable :$editor :$block field="caption" as="figcaption" class="text-[15px] text-ink-soft" :placeholder="__('page.caption_placeholder')" />
    </figure>
@elseif ($editor)
    <p class="mx-auto w-full max-w-[720px] rounded-card border-2 border-dashed border-contour p-10 text-center text-ink-soft">{{ __('page.no_image_yet') }}</p>
@endif
