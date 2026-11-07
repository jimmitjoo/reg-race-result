{{-- The upplaga's registration; in the editor a sketch of it, since the form itself is the runner's. --}}
@if ($editor)
    <section class="flex flex-col gap-4">
        <p class="flex items-center gap-3 text-[21px] font-medium sm:text-[26px]">
            <span aria-hidden="true" class="grid size-8 place-items-center rounded-full bg-brand text-[15px] font-semibold text-brand-contrast">1</span>
            {{ __('registration.step_choose') }}
        </p>
        @forelse ($races as $race)
            <div wire:key="sketch-{{ $race->id }}" class="flex flex-wrap items-baseline justify-between gap-2 rounded-card bg-white p-5 shadow-rest">
                <span class="text-[19px] font-medium">{{ $race->name }}</span>
                <span class="text-[15px] text-ink-soft">{{ $race->raceClasses->pluck('name')->join(' · ') }}</span>
            </div>
        @empty
            <p class="rounded-card bg-white p-5 text-ink-soft shadow-rest">{{ __('page.registration_no_races') }}</p>
        @endforelse
        <p class="text-[15px] text-ink-soft">{{ __('page.registration_sketch') }}</p>
    </section>
@else
    @include('blocks.registration-form')
@endif
