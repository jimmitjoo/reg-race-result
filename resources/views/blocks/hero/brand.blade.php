{{-- Brand card: the club in its colour in the page's column, a picture beside it if there is one. --}}
<section class="grid overflow-hidden rounded-card bg-brand text-brand-contrast shadow-rest @if ($block['image']) lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] @endif">
    <div class="flex flex-col gap-6 p-7 sm:p-10 lg:p-12">
        @if ($logo)
            <span class="self-start rounded-panel bg-white p-3"><img src="{{ $logo }}" alt="" class="h-14 w-auto max-w-[200px] object-contain sm:h-16"></span>
        @endif
        <div class="flex flex-col gap-4">
            <x-editable :$editor :$block field="title" as="h1" class="text-[40px] leading-[44px] font-normal tracking-[-0.02em] text-balance sm:text-[56px] sm:leading-[60px]" :placeholder="$name" :fallback="$name" />
            <x-editable :$editor :$block field="intro" class="max-w-[38rem] text-[19px] leading-[28px] text-pretty" :placeholder="__('page.intro_placeholder')" />
        </div>
        @include('blocks.hero.next', ['tone' => 'dark', 'class' => 'mt-auto border-t border-current/25 pt-6'])
    </div>
    @if ($block['image'])
        <img src="{{ $image }}" alt="{{ $block['alt'] }}" @if ($block['width']) width="{{ $block['width'] }}" height="{{ $block['height'] }}" @endif
            class="h-full max-h-[520px] w-full object-cover max-lg:aspect-[16/10]">
    @endif
</section>
