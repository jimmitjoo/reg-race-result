{{-- A few figures large, each with what it counts: the year, the runners, the metres of climb. --}}
<section class="flex flex-col gap-6">
    <x-editable :$editor :$block field="heading" as="h2" class="text-[26px] leading-[32px] font-medium sm:text-[32px] sm:leading-[38px]" :placeholder="__('page.facts_heading')" />
    <dl @class(['grid gap-x-6 gap-y-8', 'grid-cols-2' => count($block['items']) > 1, 'lg:grid-cols-3' => count($block['items']) === 3, 'lg:grid-cols-4' => count($block['items']) >= 4])>
        @foreach ($block['items'] as $item)
            <div wire:key="fact-{{ $item['id'] }}" class="flex flex-col-reverse gap-1 border-t-2 border-current/20 pt-4">
                <x-editable :$editor :$block :$item field="label" as="dt" class="text-[17px] text-ink-soft" :placeholder="__('page.facts_label')" />
                <x-editable :$editor :$block :$item field="value" as="dd" class="text-[44px] leading-[48px] font-medium tracking-[-0.02em] tabular-nums sm:text-[56px] sm:leading-[60px]" :placeholder="__('page.facts_value')" />
            </div>
        @endforeach
    </dl>
</section>
