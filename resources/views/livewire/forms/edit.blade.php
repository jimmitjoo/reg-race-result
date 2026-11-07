<?php

use App\Models\Form;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/*
 * A form built field by field, what becomes of what people send with it, and what they sent. Every change
 * is saved at once; a page shows the form as it is.
 */
new class extends Component {
    use WithPagination;

    public Form $form;

    public string $name = '';
    public array $fields = [];
    public string $action = 'both';
    public string $recipient = '';
    public string $button = '';
    public string $thanks = '';

    public function mount(Form $form): void
    {
        abort_unless($form->organizer_id === auth()->user()->organizer_id, 404);
        $this->name = $form->name;
        $this->fields = $form->fields;
        $this->action = $form->action;
        $this->recipient = (string) $form->recipient;
        $this->button = (string) $form->button;
        $this->thanks = (string) $form->thanks;
    }

    private function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'fields' => ['array', 'min:1', 'max:30'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.type' => ['required', Rule::in(Form::TYPES)],
            'fields.*.required' => ['boolean'],
            'fields.*.options' => ['nullable', 'string', 'max:500'],
            'action' => ['required', Rule::in(Form::ACTIONS)],
            'recipient' => ['nullable', 'email', 'max:255'],
            'button' => ['nullable', 'string', 'max:40'],
            'thanks' => ['nullable', 'string', 'max:300'],
        ], [], ['fields.*.label' => __('forms.label'), 'recipient' => __('forms.recipient')]);

        $this->form->update([
            'name' => trim($this->name), 'fields' => array_values($this->fields), 'action' => $this->action,
            'recipient' => trim($this->recipient) ?: null, 'button' => trim($this->button) ?: null, 'thanks' => trim($this->thanks) ?: null,
        ]);
        $this->dispatch('form-saved');
    }

    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'paginators')) {
            $this->save();
        }
    }

    public function addField(string $type): void
    {
        abort_unless(in_array($type, Form::TYPES, true), 404);
        $this->fields[] = Form::field($type, __("forms.type.{$type}"));
        $this->save();
    }

    public function removeField(int $at): void
    {
        array_splice($this->fields, $at, 1);
        $this->save();
    }

    public function moveField(int $at, int $step): void
    {
        $to = max(0, min(count($this->fields) - 1, $at + $step));
        [$field] = array_splice($this->fields, $at, 1);
        array_splice($this->fields, $to, 0, [$field]);
        $this->save();
    }

    public function deleteResponse(int $id): void
    {
        $this->form->responses()->findOrFail($id)->delete();
    }

    public function deleteForm(): void
    {
        $this->form->delete();
        $this->redirectRoute('forms.index', navigate: true);
    }

    public function with(): array
    {
        return [
            'responses' => $this->form->responses()->latest()->latest('id')->paginate(20),
            'fallback' => $this->form->recipient(),
        ];
    }
}; ?>

<div class="flex max-w-4xl flex-col gap-8">
    <div class="flex flex-col gap-2">
        <a href="{{ route('forms.index') }}" wire:navigate class="self-start text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">← {{ __('forms.title') }}</a>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <flux:heading size="xl">{{ $name ?: $form->name }}</flux:heading>
            <span class="text-sm text-zinc-500"><x-action-message on="form-saved">{{ __('forms.saved') }}</x-action-message></span>
        </div>
    </div>

    <section class="flex flex-col gap-4" aria-labelledby="falt">
        <flux:heading id="falt" size="lg">{{ __('forms.fields') }}</flux:heading>
        <flux:input wire:model.live.debounce.500ms="name" :label="__('forms.name')" :description="__('forms.name_help')" />
        <ol class="flex flex-col gap-3">
            @foreach ($fields as $i => $field)
                <li wire:key="field-{{ $field['id'] }}" class="flex flex-col gap-3 rounded-panel border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem]">
                        <flux:input wire:model.live.debounce.500ms="fields.{{ $i }}.label" :label="__('forms.label')" />
                        <flux:select wire:model.live="fields.{{ $i }}.type" :label="__('forms.type_label')">
                            @foreach (\App\Models\Form::TYPES as $type)
                                <option value="{{ $type }}">{{ __("forms.type.{$type}") }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                    @if ($field['type'] === 'select')
                        <flux:input wire:model.live.debounce.500ms="fields.{{ $i }}.options" :label="__('forms.options')" :description="__('forms.options_help')" />
                    @endif
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <flux:checkbox wire:model.live="fields.{{ $i }}.required" :label="$field['type'] === 'checkbox' ? __('forms.required_checkbox') : __('forms.required')" />
                        <div class="flex gap-1">
                            <flux:button size="sm" variant="ghost" icon="arrow-up" wire:click="moveField({{ $i }}, -1)" :disabled="$loop->first" :aria-label="__('page.move_up')" />
                            <flux:button size="sm" variant="ghost" icon="arrow-down" wire:click="moveField({{ $i }}, 1)" :disabled="$loop->last" :aria-label="__('page.move_down')" />
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeField({{ $i }})" :disabled="count($fields) === 1">{{ __('page.remove') }}</flux:button>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-zinc-500">{{ __('forms.add_field') }}</span>
            @foreach (\App\Models\Form::TYPES as $type)
                <flux:button size="sm" icon="plus" wire:click="addField('{{ $type }}')">{{ __("forms.type.{$type}") }}</flux:button>
            @endforeach
        </div>
    </section>

    <section class="flex flex-col gap-4" aria-labelledby="svar-hamnar">
        <flux:heading id="svar-hamnar" size="lg">{{ __('forms.what_happens') }}</flux:heading>
        <flux:radio.group wire:model.live="action">
            @foreach (\App\Models\Form::ACTIONS as $option)
                <flux:radio :value="$option" :label="__('forms.action.'.$option)" :description="__('forms.action_help.'.$option)" />
            @endforeach
        </flux:radio.group>
        @if ($action !== 'store')
            <flux:input wire:model.live.debounce.500ms="recipient" type="email" :label="__('forms.recipient')" :placeholder="$fallback" :description="__('forms.recipient_help')" />
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model.live.debounce.500ms="button" :label="__('forms.button')" :placeholder="__('forms.button_default')" />
            <flux:input wire:model.live.debounce.500ms="thanks" :label="__('forms.thanks')" :placeholder="__('forms.thanks_default')" />
        </div>
        <flux:text class="text-sm">{{ __('forms.retention', ['months' => config('privacy.form_retention_months')]) }}</flux:text>
    </section>

    <section class="flex flex-col gap-4" aria-labelledby="svar">
        <flux:heading id="svar" size="lg">{{ __('forms.responses') }} <span class="text-zinc-500">({{ $responses->total() }})</span></flux:heading>
        @if ($action === 'email')
            <flux:text>{{ __('forms.not_stored') }}</flux:text>
        @endif
        @forelse ($responses as $response)
            <article wire:key="response-{{ $response->id }}" class="flex flex-col gap-3 rounded-panel border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-zinc-500">{{ \App\Support\Format::dateTime($response->created_at->setTimezone($form->organizer->timezone)) }}</span>
                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteResponse({{ $response->id }})" wire:confirm="{{ __('forms.delete_response_confirm') }}">{{ __('page.remove') }}</flux:button>
                </div>
                <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-[12rem_minmax(0,1fr)]">
                    @foreach ($response->answers as $answer)
                        <dt class="text-zinc-500">{{ $answer['label'] }}</dt>
                        <dd class="break-words whitespace-pre-line">{{ $answer['value'] === '' ? '–' : $answer['value'] }}</dd>
                    @endforeach
                </dl>
            </article>
        @empty
            <flux:text>{{ __('forms.no_responses') }}</flux:text>
        @endforelse
        {{ $responses->links('livewire::simple-tailwind') }}
    </section>

    <div class="border-t border-zinc-200 pt-6 dark:border-zinc-700">
        <flux:button variant="danger" size="sm" wire:click="deleteForm" wire:confirm="{{ __('forms.delete_confirm') }}">{{ __('forms.delete') }}</flux:button>
    </div>
</div>
