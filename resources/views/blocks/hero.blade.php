{{-- The club in its own colour: logo, name as the page's h1, a few words and the way to the next race. --}}
@use('App\Support\Format')
<section class="grid overflow-hidden rounded-card bg-brand text-brand-contrast shadow-rest @if ($block['image']) lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] @endif">
    <div class="flex flex-col gap-6 p-7 sm:p-10 lg:p-12">
        @if ($logo)
            <span class="self-start rounded-panel bg-white p-3"><img src="{{ $logo }}" alt="" class="h-14 w-auto max-w-[200px] object-contain sm:h-16"></span>
        @endif
        <div class="flex flex-col gap-4">
            <h1 class="text-[40px] leading-[44px] font-normal tracking-[-0.02em] text-balance sm:text-[56px] sm:leading-[60px]">{{ $organizer->name }}</h1>
            <x-editable :$editor :$block field="intro" class="max-w-[38rem] text-[19px] leading-[28px] text-pretty" :placeholder="__('page.intro_placeholder')" />
        </div>
        @if ($next)
            <div class="mt-auto flex flex-col gap-3 border-t border-current/25 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex flex-col">
                    <span class="text-[14px] font-medium tracking-wide uppercase">{{ __('page.next') }}</span>
                    <span class="text-[19px] font-medium">{{ $next->name }}</span>
                    <span class="text-[15px]">{{ Format::date($next->date) }}@if ($next->city) · {{ $next->city }}@endif</span>
                </p>
                <a href="{{ $next->publicUrl() }}" class="inline-flex h-12 shrink-0 items-center justify-center gap-2 self-start rounded-pill bg-white px-6 text-[17px] font-semibold text-ink hover:bg-ground sm:self-auto">
                    {{ __('registration.cta') }} <flux:icon.arrow-right class="size-4" />
                </a>
            </div>
        @endif
    </div>
    @if ($block['image'])
        <img src="{{ Storage::disk('public')->url($block['image']) }}" alt="{{ $block['alt'] }}" @if ($block['width']) width="{{ $block['width'] }}" height="{{ $block['height'] }}" @endif
            class="h-full max-h-[520px] w-full object-cover max-lg:aspect-[16/10]">
    @endif
</section>
