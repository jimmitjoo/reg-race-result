{{-- Split card: the club on white beside a picture, in the page's column. --}}
<section class="grid overflow-hidden rounded-card bg-white text-ink shadow-rest lg:grid-cols-2">
    <div class="flex flex-col gap-6 p-7 sm:p-10 lg:p-12">
        @if ($logo)
            <img src="{{ $logo }}" alt="" class="h-14 w-auto max-w-[200px] self-start object-contain sm:h-16">
        @endif
        <div class="flex flex-col gap-4">
            <x-editable :$editor :$block field="title" as="h1" class="text-[40px] leading-[44px] font-normal tracking-[-0.02em] text-balance sm:text-[56px] sm:leading-[60px]" :placeholder="$organizer->name" :fallback="$organizer->name" />
            <x-editable :$editor :$block field="intro" class="text-[19px] leading-[28px] text-pretty text-ink-soft" :placeholder="__('page.intro_placeholder')" />
        </div>
        @include('blocks.hero.next', ['tone' => 'light', 'stack' => true, 'class' => 'mt-auto border-t border-contour pt-6'])
    </div>
    <img src="{{ $image }}" alt="{{ $block['alt'] }}" width="{{ $block['width'] }}" height="{{ $block['height'] }}" class="h-full min-h-[320px] w-full object-cover max-lg:aspect-[16/10]">
</section>
