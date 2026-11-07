{{-- Heading and text in a readable measure; the markdown has no HTML and its headings are h3. --}}
<section class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    @if (trim($block['heading']) !== '')
        <h2 class="text-[26px] leading-[32px] font-medium sm:text-[32px] sm:leading-[38px]">{{ $block['heading'] }}</h2>
    @endif
    <div class="flex flex-col gap-4 text-[17px] leading-[28px] [&_a]:underline [&_a]:decoration-contour [&_a]:underline-offset-2 hover:[&_a]:decoration-ink [&_blockquote]:border-s-4 [&_blockquote]:border-brand [&_blockquote]:ps-5 [&_blockquote]:text-ink-soft [&_h3]:mt-2 [&_h3]:text-[21px] [&_h3]:font-medium [&_ol]:list-decimal [&_ol]:ps-6 [&_ul]:list-disc [&_ul]:ps-6">
        {!! \App\Support\Page::markdown($block['body']) !!}
    </div>
</section>
