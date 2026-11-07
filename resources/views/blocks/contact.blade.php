{{-- Who to ask, and how. --}}
<section class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    <h2 class="text-[26px] leading-[32px] font-medium sm:text-[32px] sm:leading-[38px]">{{ trim($block['heading']) ?: __('page.contact') }}</h2>
    <div class="flex flex-col gap-1 rounded-card bg-white p-6 text-[17px] shadow-rest sm:p-7">
        @if (trim($block['name']) !== '')<p class="font-medium">{{ $block['name'] }}</p>@endif
        @if (trim($block['email']) !== '')<a href="mailto:{{ $block['email'] }}" class="self-start underline decoration-contour underline-offset-2 hover:decoration-ink">{{ $block['email'] }}</a>@endif
        @if (trim($block['phone']) !== '')<a href="tel:{{ preg_replace('/[^\d+]/', '', $block['phone']) }}" class="self-start underline decoration-contour underline-offset-2 hover:decoration-ink">{{ $block['phone'] }}</a>@endif
    </div>
</section>
