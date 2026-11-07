{{-- A new class in this race or the one being edited, under the race's classes. --}}
<form wire:submit="saveClass" class="flex flex-col gap-4 border-t border-zinc-200 p-5 dark:border-zinc-700">
    <flux:heading level="4">{{ $editingId ? __('events.edit_class') : __('events.new_class_in', ['race' => $race->name ?? '']) }}</flux:heading>

    <div class="grid gap-4 sm:grid-cols-2">
        @if ($editingId)
            <flux:select wire:model="raceId" :label="__('events.race')" :placeholder="__('events.choose_race')">
                @foreach ($races as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </flux:select>
        @endif
        <flux:input wire:model="className" :label="__('events.class_name')" :placeholder="__('events.class_name_example')" />
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:input wire:model="startTime" :label="__('events.start_time')" placeholder="10:00:00" />
        <flux:input wire:model="distanceMeters" type="number" min="1" :label="__('events.distance_meters')" />
        <flux:input wire:model="teamSize" type="number" min="2" max="20" :label="__('events.team_size')" :description="__('events.team_size_help')" />
        <flux:select wire:model="gender" :label="__('events.gender')">
            <option value="">{{ __('events.gender_any') }}</option>
            <option value="M">{{ __('events.gender_m') }}</option>
            <option value="K">{{ __('events.gender_k') }}</option>
        </flux:select>
    </div>

    <flux:checkbox wire:model.live="timed" :label="__('events.timed')" />

    <div x-show="$wire.timed">
        <flux:input wire:model="minTimeMinutes" type="number" min="0" :label="__('events.min_time_minutes')" :description="__('events.min_time_help')" />
    </div>

    <div class="flex gap-2">
        <flux:button type="submit" variant="primary">{{ __('events.save') }}</flux:button>
        @if ($editingId || $addingClassTo)
            <flux:button variant="ghost" wire:click="cancel">{{ __('events.cancel') }}</flux:button>
        @endif
        @if ($editingId)
            <flux:button variant="danger" icon="trash" class="ms-auto" wire:click="removeClass" wire:confirm="{{ __('events.remove_class_confirm') }}">{{ __('events.remove_class') }}</flux:button>
        @endif
    </div>
</form>
