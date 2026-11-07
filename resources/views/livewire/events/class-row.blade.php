<div wire:key="class-{{ $class->id }}" class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
    <span class="min-w-40 font-semibold">{{ $class->name }}</span>
    <span class="tabular-nums">{{ \App\Support\Format::time($class->start_at->setTimezone($event->timezone)) }}</span>
    <span class="text-sm text-zinc-500">{{ $class->timed ? __('events.timed') : __('events.untimed') }}</span>
    @if ($class->team_size)
        <span class="text-sm text-zinc-500">{{ __('events.team_of', ['size' => $class->team_size]) }}</span>
    @endif
    @if ($class->timed)
        <span class="text-sm text-zinc-500">{{ __('events.min_time_minutes') }}: {{ intdiv($class->min_time_seconds, 60) }}</span>
    @endif
    <div class="ms-auto flex items-center">
        @isset($loop)
            <flux:button size="sm" variant="ghost" icon="chevron-up" wire:click="moveClass({{ $class->id }}, -1)" :disabled="$loop->first" :aria-label="__('events.move_up', ['name' => $class->name])" />
            <flux:button size="sm" variant="ghost" icon="chevron-down" wire:click="moveClass({{ $class->id }}, 1)" :disabled="$loop->last" :aria-label="__('events.move_down', ['name' => $class->name])" />
        @endisset
        <flux:button size="sm" variant="ghost" wire:click="editClass({{ $class->id }})">{{ __('events.edit') }}</flux:button>
    </div>
</div>
