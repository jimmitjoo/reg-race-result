{{-- The coming upplagor, read from the arrangemang so they cannot go out of date: as cards, as a list, or the next one large. --}}
@use('App\Support\Format')
@php($events = (int) $block['limit'] > 0 ? $upcoming->take((int) $block['limit']) : $upcoming)
<section class="flex flex-col gap-4">
    <x-editable :$editor :$block field="heading" as="h2" class="text-[26px] leading-[32px] font-medium sm:text-[32px] sm:leading-[38px]" :placeholder="__('public.upcoming')" :fallback="__('public.upcoming')" />
    @if ($events->isEmpty())
        <p class="rounded-card bg-white p-7 text-[17px] text-ink-soft shadow-rest">{{ __('public.no_upcoming') }}</p>
    @elseif ($block['layout'] === 'list')
        <ul class="flex flex-col rounded-card bg-white px-5 shadow-rest sm:px-7">
            @foreach ($events as $event)
                <li class="border-contour not-first:border-t">
                    <a href="{{ $event->publicUrl() }}" class="group flex items-center gap-5 py-4">
                        <span class="flex w-14 shrink-0 flex-col items-center rounded-field bg-brand-surface py-1.5 text-ink">
                            <span class="text-[22px] leading-[26px] font-medium tabular-nums">{{ $event->date->day }}</span>
                            <span class="text-[13px] uppercase">{{ $event->date->locale(app()->getLocale())->isoFormat('MMM') }}</span>
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="text-[19px] font-medium group-hover:underline">{{ $event->name }}</span>
                            <span class="text-[15px] text-ink-soft">@include('blocks.upcoming-status')</span>
                        </span>
                        <flux:icon.arrow-right class="size-5 shrink-0 text-ink-soft" />
                    </a>
                </li>
            @endforeach
        </ul>
    @else
        <div @class(['grid gap-4', 'sm:grid-cols-2' => $block['layout'] !== 'feature', 'sm:grid-cols-2 lg:grid-cols-3' => $block['layout'] === 'feature'])>
            @foreach ($events as $event)
                @php($big = $block['layout'] === 'feature' && $loop->first)
                <a href="{{ $event->publicUrl() }}" @class(['group flex flex-col gap-4 rounded-card bg-white p-6 shadow-rest hover:shadow-hover sm:p-7', 'sm:col-span-2 lg:col-span-3 lg:flex-row lg:items-end lg:justify-between lg:p-10' => $big])>
                    <span class="flex flex-col gap-1">
                        @if ($big)<span class="text-[14px] font-medium tracking-wide text-brand uppercase">{{ __('page.next') }}</span>@endif
                        <span @class(['font-medium group-hover:underline', 'text-[21px] leading-[28px]' => ! $big, 'text-[32px] leading-[38px] sm:text-[40px] sm:leading-[46px]' => $big])>{{ $event->name }}</span>
                        <span @class(['text-ink-soft', 'text-[15px]' => ! $big, 'text-[17px]' => $big])>@include('blocks.upcoming-status')</span>
                    </span>
                    @if ($event->status() !== 'cancelled')
                        <span @class(['mt-auto inline-flex items-center gap-2 font-semibold', 'text-[15px] text-brand' => ! $big, 'h-12 self-start rounded-pill bg-brand px-6 text-[17px] text-brand-contrast lg:self-auto' => $big])>{{ __('registration.cta') }} <flux:icon.arrow-right class="size-4" /></span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</section>
