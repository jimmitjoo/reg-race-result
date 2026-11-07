{{-- Brand band: the club's colour from edge to edge, the content in the page's column. --}}
<section class="bleed {{ $top }} bg-brand text-brand-contrast">
    <div class="mx-auto flex max-w-[1100px] flex-col gap-8 px-4 py-14 sm:px-6 sm:py-20 lg:py-24">
        @if ($logo)
            <span class="self-start rounded-panel bg-white p-3"><img src="{{ $logo }}" alt="" class="h-14 w-auto max-w-[200px] object-contain sm:h-16"></span>
        @endif
        <div class="flex max-w-[48rem] flex-col gap-5">
            <x-editable :$editor :$block field="title" as="h1" class="text-[44px] leading-[48px] font-normal tracking-[-0.03em] text-balance sm:text-[72px] sm:leading-[76px]" :placeholder="$organizer->name" :fallback="$organizer->name" />
            <x-editable :$editor :$block field="intro" class="max-w-[40rem] text-[20px] leading-[30px] text-pretty" :placeholder="__('page.intro_placeholder')" />
        </div>
        @include('blocks.hero.next', ['tone' => 'dark', 'class' => 'border-t border-current/25 pt-6'])
    </div>
</section>
