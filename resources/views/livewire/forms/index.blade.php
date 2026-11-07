<?php

use App\Models\Form;
use Livewire\Volt\Component;

// The organizer's forms (#116): built here, shown on the page by a Formulär or Kontakt block.
new class extends Component {
    public string $name = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->organizer_id, 403);
    }

    public function create(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:120']], [], ['name' => __('forms.name')]);
        $form = auth()->user()->organizer->forms()->create(['name' => trim($this->name), 'fields' => Form::contactFields()]);

        $this->redirectRoute('forms.edit', $form, navigate: true);
    }

    public function with(): array
    {
        return ['forms' => auth()->user()->organizer->forms()->withCount('responses')->orderBy('name')->get()];
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">{{ __('forms.title') }}</flux:heading>
        <flux:text>{{ __('forms.help') }}</flux:text>
    </div>

    @if ($forms->isNotEmpty())
        <ul class="flex flex-col divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($forms as $form)
                <li wire:key="form-{{ $form->id }}">
                    <a href="{{ route('forms.edit', $form) }}" wire:navigate class="flex items-center justify-between gap-4 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <span class="flex flex-col">
                            <span class="font-medium">{{ $form->name }}</span>
                            <span class="text-sm text-zinc-500">{{ trans_choice('forms.fields_count', count($form->fields)) }} · {{ __("forms.action.{$form->action}") }}</span>
                        </span>
                        <span class="text-sm tabular-nums text-zinc-500">{{ trans_choice('forms.responses_count', $form->responses_count) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <form wire:submit="create" class="flex flex-col gap-4 rounded-panel border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg">{{ __('forms.new') }}</flux:heading>
        <flux:input wire:model="name" :label="__('forms.name')" :placeholder="__('forms.name_example')" :description="__('forms.new_help')" />
        <div><flux:button type="submit" variant="primary">{{ __('forms.create') }}</flux:button></div>
    </form>
</div>
