{{-- Airy: a large name on the page's own ground, the next race as a card beside it. --}}
<section class="grid gap-8 py-4 text-ink sm:py-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end lg:gap-12">
    <div class="flex flex-col gap-6">
        @if ($logo)
            <img src="{{ $logo }}" alt="" class="h-16 w-auto max-w-[220px] self-start object-contain">
        @endif
        <x-editable :$editor :$block field="title" as="h1" class="text-[48px] leading-[50px] font-normal tracking-[-0.035em] text-balance sm:text-[88px] sm:leading-[88px]" :placeholder="$name" :fallback="$name" />
        <x-editable :$editor :$block field="intro" class="max-w-[38rem] text-[20px] leading-[30px] text-pretty text-ink-soft" :placeholder="__('page.intro_placeholder')" />
    </div>
    @if ($next || $edition)
        <div class="rounded-card bg-white p-6 shadow-rest sm:p-7">
            @include('blocks.hero.next', ['tone' => 'light', 'stack' => true])
        </div>
    @endif
</section>
