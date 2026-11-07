{{-- The receipt (#132): to print or save as PDF, for the books and for friskvårdsbidrag. --}}
<x-layouts.public :organizer="$event->organizer" :event="$event" :title="__('public.title', ['page' => ($receipt['number'] ? __('receipt.title_number', ['number' => $receipt['number']]) : __('receipt.title')), 'event' => $event->name, 'organizer' => $event->organizer->name])">
    <x-breadcrumbs :event="$event" :page="__('receipt.title')" class="mx-auto mb-6 max-w-[720px] print:hidden" />
    <article class="mx-auto flex max-w-[720px] flex-col gap-6 rounded-card bg-white p-5 shadow-rest sm:p-8 print:max-w-none print:p-0 print:shadow-none">
        @if ($logo = \App\Support\Theme::logoUrl(\App\Support\Theme::for($event->organizer, $event)))
            <img src="{{ $logo }}" alt="{{ $event->organizer->name }}" class="h-14 w-auto max-w-[200px] self-start object-contain">
        @endif
        <header class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
            <h1 class="text-[38px] leading-[42px] font-normal tracking-[-0.02em]">{{ ($receipt['number'] ? __('receipt.title_number', ['number' => $receipt['number']]) : __('receipt.title')) }}</h1>
            <p class="text-[15px] text-ink-soft">{{ __('receipt.paid_on', ['date' => $receipt['paidAt']]) }}</p>
        </header>

        @if ($receipt['refunded'])
            <p class="rounded-[18px] bg-peach-light px-5 py-3 text-[15px] font-medium text-peach-deep">{{ __('receipt.refunded') }}</p>
        @endif

        <div class="grid gap-6 sm:grid-cols-2">
            <section aria-labelledby="saljare">
                <h2 id="saljare" class="text-[13px] font-medium text-ink-soft">{{ __('receipt.seller') }}</h2>
                <p class="mt-1 flex flex-col text-[15px]">@foreach ($receipt['seller'] as $line)<span>{{ $line }}</span>@endforeach</p>
            </section>
            <section aria-labelledby="kopare">
                <h2 id="kopare" class="text-[13px] font-medium text-ink-soft">{{ __('receipt.buyer') }}</h2>
                <p class="mt-1 flex flex-col text-[15px]">@foreach ($receipt['buyer'] as $line)<span>{{ $line }}</span>@endforeach</p>
            </section>
        </div>

        <table class="w-full text-[15px]">
            <caption class="sr-only">{{ __('receipt.lines') }}</caption>
            <thead class="text-left text-[13px] text-ink-soft">
                <tr>
                    <th scope="col" class="pb-2 font-medium">{{ __('receipt.participant') }}</th>
                    <th scope="col" class="pb-2 font-medium">{{ __('receipt.what') }}</th>
                    <th scope="col" class="pb-2 text-right font-medium">{{ __('receipt.price') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($receipt['lines'] as $line)
                    <tr class="border-t border-contour">
                        <td class="py-2.5 pe-3 align-top font-medium">{{ $line['name'] }}</td>
                        <td class="py-2.5 pe-3 align-top">{{ $line['what'] }}<span class="block text-[14px] text-ink-soft">{{ $line['date'] }}</span></td>
                        <td class="py-2.5 text-right align-top tabular-nums">{{ $line['price'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-ink">
                    <th scope="row" colspan="2" class="pt-3 text-left font-medium">{{ __('registration.total') }}</th>
                    <td class="pt-3 text-right text-[21px] font-medium tabular-nums">{{ $receipt['total'] }}</td>
                </tr>
                <tr>
                    <td colspan="3" class="pt-1 text-right text-[14px] text-ink-soft">{{ $receipt['vat'] }}@if ($receipt['method']) · {{ __('receipt.paid_with', ['method' => $receipt['method']]) }}@endif</td>
                </tr>
            </tfoot>
        </table>

        <p class="border-t border-contour pt-4 text-[13px] text-ink-soft">{{ $receipt['via'] }}</p>

        <div class="print:hidden">
            <button type="button" onclick="window.print()" class="inline-flex h-11 items-center rounded-pill bg-brand px-6 text-[15px] font-semibold text-brand-contrast hover:opacity-90">{{ __('receipt.print') }}</button>
        </div>
    </article>
</x-layouts.public>
