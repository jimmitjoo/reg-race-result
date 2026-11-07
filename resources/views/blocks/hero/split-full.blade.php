{{-- Split band: the club's colour on one half and a picture on the other, edge to edge; the text lines up with the page's column. --}}
<section class="bleed {{ $top }} grid bg-brand text-brand-contrast lg:grid-cols-2">
    <div class="flex flex-col justify-center gap-8 px-4 py-14 sm:px-6 sm:py-20 lg:ps-[max(1.5rem,calc((100vw-1100px)/2+1.5rem))] lg:pe-12">
        @if ($logo)
            <span class="self-start rounded-panel bg-white p-3"><img src="{{ $logo }}" alt="" class="h-14 w-auto max-w-[200px] object-contain sm:h-16"></span>
        @endif
        <div class="flex flex-col gap-5">
            <h1 class="text-[44px] leading-[48px] font-normal tracking-[-0.03em] text-balance sm:text-[64px] sm:leading-[68px]">{{ $organizer->name }}</h1>
            <x-editable :$editor :$block field="intro" class="text-[20px] leading-[30px] text-pretty" :placeholder="__('page.intro_placeholder')" />
        </div>
        @include('blocks.hero.next', ['tone' => 'dark', 'stack' => true, 'class' => 'border-t border-current/25 pt-6'])
    </div>
    <img src="{{ $image }}" alt="{{ $block['alt'] }}" width="{{ $block['width'] }}" height="{{ $block['height'] }}" class="h-full min-h-[360px] w-full object-cover max-lg:aspect-[16/10] lg:min-h-[600px]">
</section>
