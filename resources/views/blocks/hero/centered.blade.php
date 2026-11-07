{{-- Centred band: a pale tint of the club's colour from edge to edge, everything in the middle. --}}
<section class="bleed {{ $top }} bg-brand-surface text-ink">
    <div class="mx-auto flex max-w-[1100px] flex-col items-center gap-8 px-4 py-16 text-center sm:px-6 sm:py-24">
        @if ($logo)
            <img src="{{ $logo }}" alt="" class="h-20 w-auto max-w-[240px] object-contain sm:h-24">
        @endif
        <div class="flex max-w-[44rem] flex-col items-center gap-5">
            <x-editable :$editor :$block field="title" as="h1" class="text-[44px] leading-[48px] font-normal tracking-[-0.03em] text-balance sm:text-[72px] sm:leading-[76px]" :placeholder="$name" :fallback="$name" />
            <x-editable :$editor :$block field="intro" class="text-[20px] leading-[30px] text-pretty text-ink-soft" :placeholder="__('page.intro_placeholder')" />
        </div>
        @include('blocks.hero.next', ['tone' => 'light', 'stack' => true, 'center' => true])
    </div>
</section>
