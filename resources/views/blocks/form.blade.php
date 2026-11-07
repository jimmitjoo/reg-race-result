{{-- One of the organizer's forms, with a heading and a few words before it. --}}
<section class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    <x-editable :$editor :$block field="heading" as="h2" class="text-[26px] leading-[32px] font-medium sm:text-[32px] sm:leading-[38px]" :placeholder="__('page.heading_placeholder')" />
    <x-editable :$editor :$block field="intro" class="text-[17px] leading-[28px] text-ink-soft" :placeholder="__('page.form_intro_placeholder')" />
    @include('blocks.form-body')
</section>
