{{-- Picture card: a large photo in the page's column, the club on a white panel over it. --}}
<section class="relative isolate flex min-h-[520px] flex-col justify-end overflow-hidden rounded-card bg-ink p-4 text-ink shadow-rest sm:min-h-[600px] sm:p-8">
    <img src="{{ $image }}" alt="{{ $block['alt'] }}" width="{{ $block['width'] }}" height="{{ $block['height'] }}" class="absolute inset-0 -z-10 h-full w-full object-cover">
    <div class="flex flex-col gap-5 rounded-panel bg-white p-6 shadow-rest sm:max-w-[32rem] sm:p-8">
        @if ($logo)
            <img src="{{ $logo }}" alt="" class="h-12 w-auto max-w-[180px] self-start object-contain">
        @endif
        <x-editable :$editor :$block field="title" as="h1" class="text-[36px] leading-[40px] font-normal tracking-[-0.02em] text-balance sm:text-[48px] sm:leading-[52px]" :placeholder="$name" :fallback="$name" />
        <x-editable :$editor :$block field="intro" class="text-[18px] leading-[27px] text-pretty text-ink-soft" :placeholder="__('page.intro_placeholder')" />
        @include('blocks.hero.next', ['tone' => 'light', 'stack' => true, 'class' => 'border-t border-contour pt-5'])
    </div>
</section>
