<?php

use App\Models\Club;
use App\Models\Organizer;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';

    public function mount(): void
    {
        if (auth()->user()->organizer_id) {
            $this->redirectRoute('events.index', navigate: true);
        }
    }

    public function with(): array
    {
        return ['clubs' => Club::orderBy('name')->pluck('name')];
    }

    public function create(): void
    {
        $this->name = trim($this->name);
        $this->validate(['name' => ['required', 'string', 'max:100']]);

        $user = auth()->user();
        abort_if($user->organizer_id, 409);
        $organizer = Organizer::open($this->name, $user);

        // The platform wants to know who receives money through it.
        Mail::raw("{$organizer->name} (/{$organizer->slug}) skapades av {$user->name} <{$user->email}>.", fn ($message) => $message
            ->to(config('mail.platform_address'))
            ->subject("Ny arrangör: {$organizer->name}"));

        $this->redirectRoute('events.index', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('account.club_title')" :description="__('account.club_description')" />

    <form wire:submit="create" class="flex flex-col gap-6">
        <flux:input wire:model="name" list="clubs" autocomplete="off" required autofocus :label="__('account.club_name')" :description="__('account.club_help')" />
        <datalist id="clubs">
            @foreach ($clubs as $club)
                <option value="{{ $club }}">
            @endforeach
        </datalist>

        <flux:button type="submit" variant="primary" class="w-full">{{ __('account.club_create') }}</flux:button>
    </form>
</div>
