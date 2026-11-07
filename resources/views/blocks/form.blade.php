{{-- One of the organizer's forms, with a heading and a few words before it; wide, the words beside the form. --}}
@php($wide = in_array($size ?? 'text', ['wide', 'full'], true))
<section @class(['grid gap-4', 'lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] lg:items-start lg:gap-x-6' => $wide])>
    <div class="flex flex-col gap-4">
        <x-editable :$editor :$block field="heading" as="h2" class="text-[26px] leading-[32px] font-medium sm:text-[32px] sm:leading-[38px]" :placeholder="__('page.heading_placeholder')" />
        <x-editable :$editor :$block field="intro" class="text-[17px] leading-[28px] text-ink-soft" :placeholder="__('page.form_intro_placeholder')" />
    </div>
    @include('blocks.form-body')
</section>
