{{-- Who to ask, and how; wide, the details beside the form across the page's column. --}}
@php($wide = in_array($size ?? 'text', ['wide', 'full'], true))
<section class="flex flex-col gap-4">
    <x-editable :$editor :$block field="heading" as="h2" class="text-[26px] leading-[32px] font-medium sm:text-[32px] sm:leading-[38px]" :placeholder="__('page.contact')" :fallback="__('page.contact')" />
    <div @class(['flex flex-col gap-4', 'lg:grid lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] lg:items-start lg:gap-6' => $wide])>
    @if ($editor || trim($block['name'].$block['email'].$block['phone']) !== '')
    <div class="flex flex-col gap-1 rounded-card bg-white p-6 text-[17px] shadow-rest sm:p-7">
        @if ($editor)
            <x-editable :$editor :$block field="name" class="font-medium" :placeholder="__('page.name')" />
            <x-editable :$editor :$block field="email" :placeholder="__('page.email')" />
            <x-editable :$editor :$block field="phone" :placeholder="__('page.phone')" />
        @else
            @if (trim($block['name']) !== '')<p class="font-medium">{{ $block['name'] }}</p>@endif
            @if (trim($block['email']) !== '')<a href="mailto:{{ $block['email'] }}" class="self-start underline decoration-contour underline-offset-2 hover:decoration-ink">{{ $block['email'] }}</a>@endif
            @if (trim($block['phone']) !== '')<a href="tel:{{ preg_replace('/[^\d+]/', '', $block['phone']) }}" class="self-start underline decoration-contour underline-offset-2 hover:decoration-ink">{{ $block['phone'] }}</a>@endif
        @endif
    </div>
    @endif
    @include('blocks.form-body')
    </div>
</section>
