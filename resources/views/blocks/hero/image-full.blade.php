{{-- Full picture: a photo from edge to edge, darkened towards the text so white on it always reads. --}}
<section class="bleed {{ $top }} relative isolate overflow-hidden bg-ink text-white">
    <img src="{{ $image }}" alt="{{ $block['alt'] }}" width="{{ $block['width'] }}" height="{{ $block['height'] }}" class="absolute inset-0 -z-10 h-full w-full object-cover">
    <div class="absolute inset-0 -z-10 bg-linear-to-t from-black/85 via-black/55 to-black/25"></div>
    <div class="mx-auto flex min-h-[560px] max-w-[1100px] flex-col justify-end gap-8 px-4 py-14 sm:min-h-[640px] sm:px-6 sm:py-20">
        @if ($logo)
            <span class="self-start rounded-panel bg-white p-3"><img src="{{ $logo }}" alt="" class="h-14 w-auto max-w-[200px] object-contain sm:h-16"></span>
        @endif
        <div class="flex max-w-[48rem] flex-col gap-5">
            <x-editable :$editor :$block field="title" as="h1" class="text-[44px] leading-[48px] font-normal tracking-[-0.03em] text-balance sm:text-[72px] sm:leading-[76px]" :placeholder="$name" :fallback="$name" />
            <x-editable :$editor :$block field="intro" class="max-w-[40rem] text-[20px] leading-[30px] text-pretty" :placeholder="__('page.intro_placeholder')" />
        </div>
        @include('blocks.hero.next', ['tone' => 'dark', 'class' => 'border-t border-white/30 pt-6'])
    </div>
</section>
