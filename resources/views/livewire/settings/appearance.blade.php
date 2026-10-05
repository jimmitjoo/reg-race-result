<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('account.appearance')" :subheading="__('account.appearance_description')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('account.light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('account.dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('account.system') }}</flux:radio>
        </flux:radio.group>
    </x-settings.layout>
</div>
