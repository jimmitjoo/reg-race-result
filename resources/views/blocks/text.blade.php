{{-- Heading and text in a readable measure; the text holds no HTML of its own and its headings are h3. --}}
<section class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    <x-editable :$editor :$block field="heading" as="h2" class="text-[26px] leading-[32px] font-medium sm:text-[32px] sm:leading-[38px]" :placeholder="__('page.heading_placeholder')" />
    @php($prose = 'flex flex-col gap-4 text-[17px] leading-[28px] [&_a]:underline [&_a]:decoration-contour [&_a]:underline-offset-2 hover:[&_a]:decoration-ink [&_blockquote]:border-s-4 [&_blockquote]:border-brand [&_blockquote]:ps-5 [&_blockquote]:text-ink-soft [&_h3]:mt-2 [&_h3]:text-[21px] [&_h3]:font-medium [&_ol]:list-decimal [&_ol]:ps-6 [&_ul]:list-disc [&_ul]:ps-6 [&_li_p]:my-0 [&_.ProseMirror]:flex [&_.ProseMirror]:flex-col [&_.ProseMirror]:gap-4 [&_.ProseMirror]:outline-none')
    @if ($editor)
        @include('livewire.page.rich-text')
    @else
        <div class="{{ $prose }}">{!! \App\Support\Page::html($block['body']) !!}</div>
    @endif
</section>
