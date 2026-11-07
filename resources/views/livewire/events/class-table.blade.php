{{-- A race's classes: when they start, what kind, how many have come, and moving and editing. --}}
<div class="overflow-x-auto border-t border-zinc-200 dark:border-zinc-700">
    <table class="w-full text-left text-sm">
        <caption class="sr-only">{{ $caption }}</caption>
        <thead class="text-zinc-600 dark:text-zinc-300">
            <tr>
                <th scope="col" class="py-2 ps-5 pe-3 font-medium">{{ __('events.class_name') }}</th>
                <th scope="col" class="px-3 py-2 font-medium">{{ __('events.start_time') }}</th>
                <th scope="col" class="px-3 py-2 font-medium max-sm:sr-only">{{ __('events.class_kind') }}</th>
                <th scope="col" class="px-3 py-2 text-right font-medium">{{ __('events.overview_runners') }}</th>
                <th scope="col" class="py-2 ps-3 pe-3"><span class="sr-only">{{ __('events.actions') }}</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($classes as $class)
                <tr wire:key="class-{{ $class->id }}" @class(['bg-zinc-50 dark:bg-zinc-800' => $editingId === $class->id])>
                    <th scope="row" class="py-2 ps-5 pe-3 font-semibold">{{ $class->name }}</th>
                    <td class="whitespace-nowrap px-3 py-2 tabular-nums">{{ \App\Support\Format::time($class->start_at->setTimezone($event->timezone)) }}</td>
                    <td class="px-3 py-2 text-zinc-600 max-sm:hidden dark:text-zinc-300">
                        {{ collect([
                            $class->gender ? __('events.gender_'.strtolower($class->gender)) : null,
                            $class->team_size ? __('events.team_of', ['size' => $class->team_size]) : null,
                            $class->timed ? null : __('events.untimed'),
                            $class->timed && $class->min_time_seconds ? __('events.min_time_short', ['minutes' => intdiv($class->min_time_seconds, 60)]) : null,
                        ])->filter()->implode(' · ') }}
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ $counts[$class->id]->runners ?? 0 }}</td>
                    <td class="py-1 ps-3 pe-3">
                        <div class="flex items-center justify-end">
                            <flux:button size="sm" variant="ghost" icon="chevron-up" class="max-sm:hidden" wire:click="moveClass({{ $class->id }}, -1)" :disabled="$loop->first" :aria-label="__('events.move_up', ['name' => $class->name])" />
                            <flux:button size="sm" variant="ghost" icon="chevron-down" class="max-sm:hidden" wire:click="moveClass({{ $class->id }}, 1)" :disabled="$loop->last" :aria-label="__('events.move_down', ['name' => $class->name])" />
                            <flux:button size="sm" variant="ghost" wire:click="editClass({{ $class->id }})" :aria-label="__('events.edit').' '.$class->name">{{ __('events.edit') }}</flux:button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
