<x-layouts.public>
    <div class="flex flex-col gap-4">
        <flux:heading size="xl">{{ __('registration.confirmation_title') }}</flux:heading>
        <flux:text>
            {{ __('registration.confirmation_text', [
                'name' => $registration->first_name.' '.$registration->last_name,
                'class' => $registration->raceClass->name,
                'event' => $event->name,
            ]) }}
        </flux:text>
        @if (! $registration->paid_at)
            <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('registration.unpaid')" />
        @endif
    </div>
</x-layouts.public>
