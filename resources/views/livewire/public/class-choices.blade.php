{{-- A race's classes to choose from, grouped by start time with the time said once per group. --}}
<div class="flex flex-col gap-4">
    @foreach (\App\Support\Format::byStart($classes) as $group)
        @php($groupId = $key.'-'.$group->first()->start_at->getTimestamp())
        <div role="group" aria-labelledby="{{ $groupId }}" class="flex flex-col gap-2">
            <p id="{{ $groupId }}" class="text-[13px] font-medium text-ink-soft tabular-nums">{{ __('registration.starts', ['time' => \App\Support\Format::time($group->first()->start_at->setTimezone($event->timezone))]) }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($group as $class)
                    <label wire:key="{{ $key }}-{{ $class->id }}" class="group relative">
                        <input type="radio" name="{{ $name }}" value="{{ $class->id }}" wire:model.live="{{ $name }}" class="peer sr-only" @disabled($disabled)>
                        <span class="flex min-h-11 cursor-pointer flex-col justify-center rounded-pill border-[1.5px] border-contour bg-white px-4 py-1.5 leading-tight transition-colors peer-checked:border-brand peer-checked:bg-brand peer-checked:text-brand-contrast peer-focus-visible:outline-3 peer-focus-visible:outline-offset-3 peer-focus-visible:outline-brand peer-disabled:cursor-not-allowed peer-disabled:opacity-60 hover:border-ink-soft">
                            <span class="text-[15px] font-medium">{{ $class->name }}</span>
                            @if ($class->team_size)
                                <span class="text-[13px] opacity-80">{{ __('events.team_of', ['size' => $class->team_size]) }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
