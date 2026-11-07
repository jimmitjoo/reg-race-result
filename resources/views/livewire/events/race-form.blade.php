{{-- A new race or the one being edited, inside the races panel. --}}
<form wire:submit="saveRace" class="flex flex-col gap-4 p-5">
    <flux:heading size="lg" level="3">{{ $editingRaceId ? __('events.edit_race') : __('events.new_race') }}</flux:heading>
    <flux:input wire:model="raceName" :label="__('events.race_name')" :placeholder="__('events.race_name_example')" />
    <div class="grid gap-4 sm:grid-cols-3">
        <flux:select wire:model="raceType" :label="__('events.race_type')">
            @foreach (App\Models\Race::TYPES as $type)
                <option value="{{ $type }}">{{ $type }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="courseMeasurer" :label="__('events.course_measurer')" />
        <flux:input wire:model="measuredOn" type="date" :label="__('events.measured_on')" />
    </div>
    <flux:text class="text-sm">{{ __('events.measurement_help') }}</flux:text>
    <flux:checkbox wire:model="ageGroups" :label="__('events.age_groups')" :description="__('events.age_groups_help')" />
    <flux:select wire:model.live="championship" :label="__('events.championship')">
        <option value="">{{ __('events.championship_none') }}</option>
        <option value="DM">{{ __('events.championship_dm') }}</option>
        <option value="SM">{{ __('events.championship_sm') }}</option>
    </flux:select>
    @if ($championship === 'DM')
        <flux:checkbox.group wire:model="championshipDistricts" :label="__('events.championship_districts')" :description="__('events.championship_districts_help')">
            <div class="grid grid-cols-2 gap-1 sm:grid-cols-3">
                @foreach ($districts as $district)
                    <flux:checkbox :value="$district" :label="$district" />
                @endforeach
            </div>
        </flux:checkbox.group>
    @endif
    @if ($championship)
        <flux:checkbox wire:model="championshipVeterans" :label="__('events.championship_veterans', ['name' => 'V'.$championship])" />
    @endif
    <div class="flex gap-2">
        <flux:button type="submit" variant="primary">{{ $editingRaceId ? __('events.save') : __('events.create') }}</flux:button>
        @if ($editingRaceId || $addingRace)
            <flux:button variant="ghost" wire:click="cancelRace">{{ __('events.cancel') }}</flux:button>
        @endif
        @if ($editingRaceId)
            <flux:button variant="danger" icon="trash" class="ms-auto" wire:click="removeRace" wire:confirm="{{ __('events.remove_race_confirm') }}">{{ __('events.remove_race') }}</flux:button>
        @endif
    </div>
</form>
