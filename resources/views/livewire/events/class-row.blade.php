<div wire:key="class-{{ $class->id }}" class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
    <span class="min-w-40 font-semibold">{{ $class->name }}</span>
    <span class="font-mono">{{ $class->start_at->setTimezone($event->timezone)->format('H:i:s') }}</span>
    <span class="text-sm text-zinc-500">{{ $class->timed ? __('events.timed') : __('events.untimed') }}</span>
    @if ($class->timed)
        <span class="text-sm text-zinc-500">{{ __('events.min_time_minutes') }}: {{ intdiv($class->min_time_seconds, 60) }}</span>
    @endif
    <flux:button size="sm" variant="ghost" class="ms-auto" wire:click="editClass({{ $class->id }})">{{ __('events.edit') }}</flux:button>
</div>
