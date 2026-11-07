{{-- Earlier years per arrangemang, as cards or as a list, as many as the organizer chose; the rest of the years fold away. --}}
@php($series = (int) $block['limit'] > 0 ? $earlier->take((int) $block['limit']) : $earlier)
@php($shown = (int) $block['years'] > 0 ? (int) $block['years'] : PHP_INT_MAX)
@if ($earlier->isNotEmpty() || $editor)
    <section class="flex flex-col gap-4">
        <x-editable :$editor :$block field="heading" as="h2" class="text-[26px] leading-[32px] font-medium sm:text-[32px] sm:leading-[38px]" :placeholder="__('public.earlier')" :fallback="__('public.earlier')" />
        @if ($earlier->isEmpty())<p class="text-[17px] text-ink-soft">{{ __('page.no_earlier') }}</p>@endif
        <ul @class(['grid gap-3 sm:grid-cols-2 lg:grid-cols-3' => $block['layout'] !== 'list', 'flex flex-col rounded-card bg-white px-5 shadow-rest sm:px-7' => $block['layout'] === 'list'])>
            @foreach ($series as $name => $years)
                <li @class(['flex flex-col gap-3 rounded-card bg-white p-5 shadow-rest' => $block['layout'] !== 'list', 'grid gap-2 border-contour py-4 not-first:border-t sm:grid-cols-[14rem_minmax(0,1fr)] sm:gap-6' => $block['layout'] === 'list'])>
                    <h3 class="text-[17px] font-medium">
                        @if ($arrangemang = $years->first()->series)
                            <a href="{{ route('public.event', ['organizer' => $organizer->slug, 'event' => $arrangemang->slug]) }}" class="underline decoration-contour underline-offset-2 hover:decoration-ink">{{ $name }}</a>
                        @else
                            {{ $name }}
                        @endif
                    </h3>
                    <div class="flex flex-col gap-2">
                        <p class="flex flex-wrap gap-1.5">@foreach ($years->take($shown) as $event)@include('blocks.year')@endforeach</p>
                        @if ($years->count() > $shown)
                            <details class="text-[14px]">
                                <summary class="cursor-pointer text-ink-soft hover:text-ink">{{ trans_choice('page.more_years', $years->count() - $shown) }}</summary>
                                <p class="mt-2 flex flex-wrap gap-1.5">@foreach ($years->skip($shown) as $event)@include('blocks.year')@endforeach</p>
                            </details>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endif
