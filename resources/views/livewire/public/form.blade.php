<?php

use App\Mail\FormAnswered;
use App\Models\Form;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

/*
 * An organizer's form on its page: what is sent is emailed, kept or both, as the form says. A hidden field
 * catches robots and a limit per address stops floods. In the editor it is shown but cannot be sent.
 */
new class extends Component {
    #[Locked]
    public int $formId;

    #[Locked]
    public bool $disabled = false;

    public array $answers = [];

    // Hidden from people; a robot that fills it in is thanked and ignored.
    public string $website = '';

    public bool $sent = false;

    private function form(): Form
    {
        return Form::findOrFail($this->formId);
    }

    public function send(): void
    {
        abort_if($this->disabled, 403);
        if ($this->website !== '') {
            $this->sent = true;

            return;
        }
        $key = "form:{$this->formId}:".request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('form', __('forms.too_many'));

            return;
        }

        $form = $this->form();
        $rules = [];
        $names = [];
        foreach ($form->fields as $field) {
            $name = "answers.{$field['id']}";
            $names[$name] = $field['label'];
            $rules[$name] = match ($field['type']) {
                'email' => [$field['required'] ? 'required' : 'nullable', 'email', 'max:255'],
                'phone' => [$field['required'] ? 'required' : 'nullable', 'string', 'max:40'],
                'textarea' => [$field['required'] ? 'required' : 'nullable', 'string', 'max:5000'],
                'select' => [$field['required'] ? 'required' : 'nullable', Rule::in(Form::options($field))],
                'checkbox' => $field['required'] ? ['accepted'] : ['nullable', 'boolean'],
                default => [$field['required'] ? 'required' : 'nullable', 'string', 'max:255'],
            };
        }
        $this->validate($rules, [], $names);
        RateLimiter::hit($key, 600);

        $answers = collect($form->fields)->map(fn ($field) => [
            'label' => $field['label'],
            'value' => $field['type'] === 'checkbox'
                ? (empty($this->answers[$field['id']]) ? __('forms.no') : __('forms.yes'))
                : trim((string) ($this->answers[$field['id']] ?? '')),
        ])->all();
        $replyTo = collect($form->fields)->where('type', 'email')->map(fn ($field) => $this->answers[$field['id']] ?? null)->filter()->first();

        if ($form->action !== 'email') {
            $form->responses()->create(['answers' => $answers]);
        }
        if ($form->action !== 'store' && ($to = $form->recipient())) {
            Mail::to($to)->send(new FormAnswered($form, $answers, $replyTo));
        }
        [$this->sent, $this->answers] = [true, []];
    }

    public function with(): array
    {
        return ['form' => $this->form()];
    }
}; ?>

<div>
    @if ($sent)
        <p class="rounded-panel bg-brand-surface p-5 text-[17px] text-ink" role="status">{{ $form->thanks ?: __('forms.thanks_default') }}</p>
    @else
        <form wire:submit="send" class="flex flex-col gap-5" novalidate>
            @foreach ($form->fields as $field)
                @php($name = "answers.{$field['id']}")
                <div wire:key="answer-{{ $field['id'] }}">
                    @switch ($field['type'])
                        @case ('textarea')
                            <flux:textarea wire:model="{{ $name }}" rows="5" :label="$field['label'].($field['required'] ? ' *' : '')" :disabled="$disabled" />
                            @break
                        @case ('select')
                            <flux:select wire:model="{{ $name }}" :label="$field['label'].($field['required'] ? ' *' : '')" :disabled="$disabled">
                                <option value="">{{ __('forms.choose') }}</option>
                                @foreach (\App\Models\Form::options($field) as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </flux:select>
                            @break
                        @case ('checkbox')
                            <flux:checkbox wire:model="{{ $name }}" :label="$field['label'].($field['required'] ? ' *' : '')" :disabled="$disabled" />
                            @break
                        @default
                            <flux:input wire:model="{{ $name }}" :type="['email' => 'email', 'phone' => 'tel'][$field['type']] ?? 'text'"
                                :autocomplete="['email' => 'email', 'phone' => 'tel'][$field['type']] ?? 'on'" :label="$field['label'].($field['required'] ? ' *' : '')" :disabled="$disabled" />
                    @endswitch
                </div>
            @endforeach
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label>{{ __('forms.honeypot') }} <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <flux:error name="form" />
            <div class="flex flex-wrap items-center gap-4">
                <button type="submit" @disabled($disabled) class="inline-flex h-12 items-center rounded-pill bg-brand px-6 text-[17px] font-semibold text-brand-contrast hover:shadow-hover disabled:opacity-60">{{ $form->button ?: __('forms.button_default') }}</button>
                <span class="text-[14px] text-ink-soft">{{ __('forms.required_note') }}</span>
            </div>
        </form>
    @endif
</div>
