{{-- Earlier years per arrangemang, the latest five in view: the results once published, otherwise who ran. --}}
@if ($earlier->isNotEmpty() || $editor)
    <section class="flex flex-col gap-4">
        <x-editable :$editor :$block field="heading" as="h2" class="text-[26px] leading-[32px] font-medium sm:text-[32px] sm:leading-[38px]" :placeholder="__('public.earlier')" :fallback="__('public.earlier')" />
        @if ($earlier->isEmpty())<p class="text-[17px] text-ink-soft">{{ __('page.no_earlier') }}</p>@endif
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($earlier as $name => $years)
                <li class="flex flex-col gap-3 rounded-card bg-white p-5 shadow-rest">
                    <h3 class="text-[17px] font-medium">
                        @if ($series = $years->first()->series)
                            <a href="{{ route('public.event', ['organizer' => $organizer->slug, 'event' => $series->slug]) }}" class="underline decoration-contour underline-offset-2 hover:decoration-ink">{{ $name }}</a>
                        @else
                            {{ $name }}
                        @endif
                    </h3>
                    <p class="flex flex-wrap gap-1.5">@foreach ($years->take(5) as $event)@include('blocks.year')@endforeach</p>
                    @if ($years->count() > 5)
                        <details class="text-[14px]">
                            <summary class="cursor-pointer text-ink-soft hover:text-ink">{{ trans_choice('page.more_years', $years->count() - 5) }}</summary>
                            <p class="mt-2 flex flex-wrap gap-1.5">@foreach ($years->skip(5) as $event)@include('blocks.year')@endforeach</p>
                        </details>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
