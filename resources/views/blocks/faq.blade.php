{{-- Questions people ask, the answers folding out; on the page also FAQPage for search engines. --}}
<section class="flex flex-col gap-4">
    <x-editable :$editor :$block field="heading" as="h2" class="text-[26px] leading-[32px] font-medium sm:text-[32px] sm:leading-[38px]" :placeholder="__('page.faq_heading')" :fallback="__('page.faq_heading')" />
    <div class="flex flex-col rounded-card bg-white px-5 shadow-rest sm:px-7">
        @foreach ($block['items'] as $item)
            @if ($editor)
                <div wire:key="faq-{{ $item['id'] }}" class="flex flex-col gap-2 border-contour py-5 not-first:border-t">
                    <x-editable :$editor :$block :$item field="q" as="h3" class="text-[19px] font-medium" :placeholder="__('page.faq_q')" />
                    <x-editable :$editor :$block :$item field="a" class="text-[17px] leading-[28px] whitespace-pre-line text-ink-soft" :placeholder="__('page.faq_a')" multiline />
                </div>
            @elseif (trim($item['q']) !== '')
                <details class="group border-contour not-first:border-t">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-5 [&::-webkit-details-marker]:hidden">
                        <h3 class="text-[19px] font-medium">{{ $item['q'] }}</h3>
                        <flux:icon.plus class="size-5 shrink-0 text-ink-soft transition group-open:rotate-45" />
                    </summary>
                    <p class="pb-5 text-[17px] leading-[28px] whitespace-pre-line text-ink-soft">{{ $item['a'] }}</p>
                </details>
            @endif
        @endforeach
    </div>
    @unless ($editor)
        <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($block['items'])->filter(fn ($item) => trim($item['q']) !== '')->map(fn ($item) => ['@type' => 'Question', 'name' => $item['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']]])->values()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endunless
</section>
