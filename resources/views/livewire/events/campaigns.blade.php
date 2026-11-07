<?php

use App\Models\Campaign;
use App\Models\CampaignCode;
use App\Models\Event;
use App\Rules\OrgNumber;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

// Campaigns for company and club registration (#85): own prices, a place cap, codes and invoice instead of card.
new class extends Component {
    public Event $event;

    public ?int $editing = null;
    public string $name = '';
    public string $kind = 'shared';
    public string $code = '';
    public string $cap = '';
    public array $prices = [];
    public bool $invoice = false;
    public string $invoiceName = '';
    public string $invoiceOrgNumber = '';
    public string $invoiceReference = '';
    public string $invoiceEmail = '';

    // Single-use codes to generate for a campaign.
    public array $generate = [];

    public function with(): array
    {
        return [
            'campaigns' => $this->event->campaigns()->withCount(['codes', 'codes as used_codes_count' => fn ($q) => $q->whereNotNull('used_at')])
                ->with(['registrations' => fn ($q) => $q->whereNull('refunded_at')])->orderBy('name')->get(),
            'races' => $this->event->races()->ordered()->get(),
            'currency' => $this->event->organizer->currency,
        ];
    }

    public function edit(?int $id = null): void
    {
        $this->resetErrorBag();
        $campaign = $id ? $this->event->campaigns()->findOrFail($id) : null;
        $this->editing = $id ?? 0;
        $this->name = (string) $campaign?->name;
        $this->kind = $campaign?->single_use ? 'single' : 'shared';
        $this->code = (string) $campaign?->code;
        $this->cap = (string) $campaign?->cap;
        $this->prices = collect($campaign?->prices ?? [])->map(fn ($amount) => (string) ($amount / 100))->all();
        $this->invoice = (bool) $campaign?->invoice;
        $this->invoiceName = (string) $campaign?->invoice_name;
        $this->invoiceOrgNumber = (string) $campaign?->invoice_org_number;
        $this->invoiceReference = (string) $campaign?->invoice_reference;
        $this->invoiceEmail = (string) $campaign?->invoice_email;
    }

    public function save(): void
    {
        $this->code = Campaign::normalize($this->code);
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'in:shared,single'],
            'code' => ['exclude_unless:kind,shared', 'required', 'alpha_num', 'max:40',
                Rule::unique('campaigns', 'code')->where('event_id', $this->event->id)->ignore($this->editing ?: null)],
            'cap' => ['nullable', 'integer', 'min:1'],
            'prices.*' => ['nullable', 'numeric', 'min:0'],
            'invoiceName' => ['exclude_unless:invoice,true', 'required', 'string', 'max:200'],
            'invoiceOrgNumber' => ['exclude_unless:invoice,true', 'nullable', new OrgNumber],
            'invoiceReference' => ['nullable', 'string', 'max:200'],
            'invoiceEmail' => ['exclude_unless:invoice,true', 'nullable', 'email'],
        ], [], [
            'name' => __('campaigns.name'), 'code' => __('campaigns.code'), 'cap' => __('campaigns.cap'), 'prices.*' => __('campaigns.price'),
            'invoiceName' => __('campaigns.invoice_name'), 'invoiceOrgNumber' => __('receipt.org_number_label'), 'invoiceEmail' => __('registration.email'),
        ]);

        $raceIds = $this->event->races()->pluck('id')->all();
        $attributes = [
            'name' => trim($this->name),
            'single_use' => $this->kind === 'single',
            'code' => $this->kind === 'shared' ? $this->code : null,
            'cap' => $this->cap === '' ? null : (int) $this->cap,
            'prices' => collect($this->prices)->filter(fn ($price, $raceId) => $price !== '' && $price !== null && in_array((int) $raceId, $raceIds, true))
                ->map(fn ($price) => (int) round((float) str_replace(',', '.', $price) * 100))->all() ?: null,
            'invoice' => $this->invoice,
            'invoice_name' => $this->invoice ? trim($this->invoiceName) : null,
            'invoice_org_number' => $this->invoice && $this->invoiceOrgNumber !== '' ? OrgNumber::format($this->invoiceOrgNumber) : null,
            'invoice_reference' => $this->invoice ? (trim($this->invoiceReference) ?: null) : null,
            'invoice_email' => $this->invoice ? (trim($this->invoiceEmail) ?: null) : null,
        ];

        $this->editing ? $this->event->campaigns()->findOrFail($this->editing)->update($attributes) : $this->event->campaigns()->create($attributes);
        $this->editing = null;
    }

    public function generateCodes(int $id): void
    {
        $campaign = $this->event->campaigns()->where('single_use', true)->findOrFail($id);
        $this->validate([
            "generate.{$id}.count" => ['required', 'integer', 'min:1', 'max:1000'],
            "generate.{$id}.validUntil" => ['nullable', 'date_format:Y-m-d'],
            "generate.{$id}.appliesTo" => ['nullable', Rule::in(CampaignCode::APPLIES_TO)],
        ], [], ["generate.{$id}.count" => __('campaigns.count'), "generate.{$id}.validUntil" => __('campaigns.valid_until')]);

        $options = $this->generate[$id];
        foreach (range(1, (int) $options['count']) as $n) {
            $campaign->codes()->create([
                'code' => CampaignCode::generate(),
                'valid_until' => ($options['validUntil'] ?? '') ?: null,
                'applies_to' => ($options['appliesTo'] ?? '') ?: 'both',
            ]);
        }
        unset($this->generate[$id]);
    }

    /** The money has come in for the campaign's invoice: its registrations become paid, with no mail (#85). */
    public function markInvoicePaid(int $id): void
    {
        $campaign = $this->event->campaigns()->where('invoice', true)->findOrFail($id);
        foreach ($campaign->registrations()->whereNull('paid_at')->get() as $registration) {
            $registration->markPaid('invoice', notify: false);
        }
        $campaign->update(['invoice_paid_at' => now()]);
    }
}; ?>

<div class="flex max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('campaigns.title') }}</flux:heading>
            <flux:subheading>{{ $event->name }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="edit">{{ __('campaigns.new') }}</flux:button>
    </div>
    <flux:text>{{ __('campaigns.help') }}</flux:text>

    @if ($editing !== null)
        <form wire:submit="save" class="flex flex-col gap-4 rounded-panel border border-contour bg-white p-6 dark:border-white/10 dark:bg-white/5">
            <flux:heading size="lg">{{ $editing ? __('campaigns.edit') : __('campaigns.new') }}</flux:heading>
            <flux:input wire:model="name" :label="__('campaigns.name')" :description="__('campaigns.name_help')" />

            <flux:radio.group wire:model.live="kind" :label="__('campaigns.codes')">
                <flux:radio value="shared" :label="__('campaigns.shared')" :description="__('campaigns.shared_help')" />
                <flux:radio value="single" :label="__('campaigns.single')" :description="__('campaigns.single_help')" />
            </flux:radio.group>
            @if ($kind === 'shared')
                <flux:input wire:model="code" :label="__('campaigns.code')" placeholder="ATEA2027" class="max-w-xs" />
            @endif

            <fieldset class="flex flex-col gap-3">
                <legend class="mb-1 text-sm font-medium">{{ __('campaigns.prices') }}</legend>
                <flux:text class="text-sm">{{ __('campaigns.prices_help') }}</flux:text>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($races as $race)
                        <flux:input wire:model="prices.{{ $race->id }}" :label="$race->name" inputmode="decimal" :placeholder="Money::format((int) $race->priceAt(now()), $currency)" />
                    @endforeach
                </div>
            </fieldset>

            <flux:input wire:model="cap" type="number" min="1" :label="__('campaigns.cap_optional')" :description="__('campaigns.cap_help')" class="max-w-xs" />

            <flux:switch wire:model.live="invoice" :label="__('campaigns.invoice')" :description="__('campaigns.invoice_help')" />
            @if ($invoice)
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="invoiceName" :label="__('campaigns.invoice_name')" />
                    <flux:input wire:model="invoiceOrgNumber" :label="__('receipt.org_number_optional')" placeholder="NNNNNN-NNNN" />
                    <flux:input wire:model="invoiceReference" :label="__('campaigns.invoice_reference')" />
                    <flux:input wire:model="invoiceEmail" type="email" :label="__('campaigns.invoice_email')" />
                </div>
            @endif

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('campaigns.save') }}</flux:button>
                <flux:button wire:click="$set('editing', null)">{{ __('campaigns.cancel') }}</flux:button>
            </div>
        </form>
    @endif

    @forelse ($campaigns as $campaign)
        @php($taken = $campaign->taken())
        <section wire:key="kampanj-{{ $campaign->id }}" class="flex flex-col gap-4 rounded-panel border border-contour bg-white p-6 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ $campaign->name }}</flux:heading>
                    <flux:text>
                        {{ $campaign->single_use ? __('campaigns.single_count', ['used' => $campaign->used_codes_count, 'count' => $campaign->codes_count]) : __('campaigns.shared_code', ['code' => $campaign->code]) }}
                        · {{ $campaign->cap ? __('campaigns.taken_of', ['taken' => $taken, 'cap' => $campaign->cap]) : trans_choice('public.runners', $taken, ['count' => $taken]) }}
                        @foreach ($races as $race)
                            @isset($campaign->prices[$race->id]) · {{ $race->name }} {{ Money::format($campaign->prices[$race->id], $currency) }} @endisset
                        @endforeach
                    </flux:text>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($campaign->invoice)
                        <flux:badge :color="$campaign->invoice_paid_at ? 'green' : 'amber'">{{ $campaign->invoice_paid_at ? __('campaigns.invoice_paid') : __('campaigns.invoice_unpaid') }}</flux:badge>
                    @endif
                    <flux:button size="sm" wire:click="edit({{ $campaign->id }})">{{ __('campaigns.edit') }}</flux:button>
                </div>
            </div>

            @if (! $campaign->single_use)
                <flux:input readonly copyable :value="route('public.event', ['organizer' => $event->organizer->slug, 'event' => $event->series?->slug ?? $event->slug]).'?kod='.$campaign->code" :label="__('campaigns.link')" />
            @else
                <div class="flex flex-wrap items-end gap-3">
                    <flux:input wire:model="generate.{{ $campaign->id }}.count" type="number" min="1" max="1000" :label="__('campaigns.count')" class="w-28" />
                    <flux:input wire:model="generate.{{ $campaign->id }}.validUntil" type="date" :label="__('campaigns.valid_until')" class="w-44" />
                    <flux:select wire:model="generate.{{ $campaign->id }}.appliesTo" :label="__('campaigns.applies_to')" class="w-44">
                        @foreach (\App\Models\CampaignCode::APPLIES_TO as $value)
                            <option value="{{ $value }}">{{ __("campaigns.applies.{$value}") }}</option>
                        @endforeach
                    </flux:select>
                    <flux:button wire:click="generateCodes({{ $campaign->id }})">{{ __('campaigns.generate') }}</flux:button>
                    @if ($campaign->codes_count)
                        <flux:button icon="arrow-down-tray" :href="route('events.campaigns.codes', [$event, $campaign])">{{ __('campaigns.download_codes') }}</flux:button>
                    @endif
                </div>
            @endif

            @if ($campaign->invoice)
                <div class="flex flex-wrap gap-2">
                    <flux:button icon="document-text" :href="route('events.campaigns.invoice', [$event, $campaign])" target="_blank">{{ __('campaigns.invoice_basis') }}</flux:button>
                    <flux:button icon="arrow-down-tray" :href="route('events.campaigns.invoice', [$event, $campaign, 'format' => 'csv'])">{{ __('campaigns.invoice_csv') }}</flux:button>
                    @if (! $campaign->invoice_paid_at && $campaign->registrations->whereNull('paid_at')->isNotEmpty())
                        <flux:button variant="primary" icon="check" wire:click="markInvoicePaid({{ $campaign->id }})" wire:confirm="{{ __('campaigns.mark_paid_confirm') }}">{{ __('campaigns.mark_paid') }}</flux:button>
                    @endif
                </div>
            @endif
        </section>
    @empty
        <flux:text>{{ __('campaigns.none') }}</flux:text>
    @endforelse
</div>
