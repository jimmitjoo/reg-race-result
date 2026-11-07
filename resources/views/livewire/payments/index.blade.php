<?php

use App\Payments\StripeConnect;
use App\Rules\OrgNumber;
use App\Support\Money;
use Livewire\Volt\Component;

new class extends Component {
    // What the receipt says about the club as seller (#132).
    public string $orgNumber = '';
    public string $address = '';
    public string $vatPercent = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->organizer_id, 403);
        $organizer = auth()->user()->organizer;
        $this->orgNumber = (string) $organizer->org_number;
        $this->address = (string) $organizer->address;
        $this->vatPercent = (string) $organizer->vat_percent;
    }

    public function saveReceipt(): void
    {
        $this->orgNumber = trim($this->orgNumber);
        $this->address = trim($this->address);
        $this->validate([
            'orgNumber' => ['required', new OrgNumber],
            'address' => ['nullable', 'string', 'max:200'],
            'vatPercent' => ['nullable', 'in:6,12,25'],
        ], [], ['orgNumber' => __('receipt.org_number_label'), 'address' => __('receipt.address')]);

        auth()->user()->organizer->update([
            'org_number' => OrgNumber::format($this->orgNumber),
            'address' => $this->address ?: null,
            'vat_percent' => $this->vatPercent === '' ? null : (int) $this->vatPercent,
        ]);
        $this->orgNumber = OrgNumber::format($this->orgNumber);
        $this->dispatch('receipt-saved');
    }

    // ponytail: Stripe's list price for a standard EEA card in Sweden (1.5 % + 1.80 kr), only for the example; update if Stripe changes it.
    private const EXAMPLE_PRICE = 30000;

    public function with(): array
    {
        $organizer = auth()->user()->organizer;
        $stripeFee = (int) round(self::EXAMPLE_PRICE * 0.015) + 180;
        $platformFee = $organizer->platformFee(self::EXAMPLE_PRICE);

        return [
            'organizer' => $organizer,
            'status' => match (true) {
                $organizer->canTakePayments() => 'ready',
                (bool) $organizer->stripe_account_id => 'incomplete',
                default => 'none',
            },
            'example' => __('payments.example', [
                'price' => Money::format(self::EXAMPLE_PRICE, $organizer->currency),
                'stripe' => Money::format($stripeFee, $organizer->currency),
                'platform' => Money::format($platformFee, $organizer->currency),
                'club' => Money::format(self::EXAMPLE_PRICE - $stripeFee - $platformFee, $organizer->currency),
            ]),
            'fee' => trim(($organizer->fee_basis_points ? Illuminate\Support\Number::percentage($organizer->fee_basis_points / 100, maxPrecision: 2, locale: app()->getLocale()) : '')
                .($organizer->fee_basis_points && $organizer->fee_fixed ? ' + ' : '')
                .($organizer->fee_fixed ? Money::format($organizer->fee_fixed, $organizer->currency) : '')) ?: __('payments.no_fee'),
        ];
    }

    public function connect(StripeConnect $stripe): void
    {
        $this->redirect($stripe->onboardingUrl(auth()->user()->organizer, route('payments.refresh'), route('payments.return')));
    }
}; ?>

<div class="flex max-w-2xl flex-col gap-6">
    <flux:heading size="xl">{{ __('payments.title') }}</flux:heading>
    <flux:text>{{ __('payments.help') }}</flux:text>

    @if ($status === 'ready')
        <flux:callout variant="success" icon="check-circle" :heading="__('payments.ready')" />
    @elseif ($status === 'incomplete')
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('payments.incomplete')" />
    @else
        <flux:callout icon="credit-card" :heading="__('payments.none')" />
    @endif

    @if ($status !== 'ready')
        <div><flux:button variant="primary" wire:click="connect">{{ $status === 'none' ? __('payments.connect') : __('payments.continue') }}</flux:button></div>
    @endif

    <flux:text class="text-sm">{{ __('payments.fee', ['fee' => $fee]) }}</flux:text>
    <flux:text class="text-sm">{{ $example }}</flux:text>
    <flux:text class="text-sm">{{ __('payments.responsibility') }}</flux:text>

    <form wire:submit="saveReceipt" class="flex flex-col gap-4 pt-6">
        <flux:heading size="lg">{{ __('receipt.settings') }}</flux:heading>
        <flux:text>{{ __('receipt.settings_help') }}</flux:text>
        @unless ($organizer->org_number)
            <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('receipt.missing_org_number')" />
        @endunless
        <flux:input wire:model="orgNumber" :label="__('receipt.org_number_label')" placeholder="NNNNNN-NNNN" inputmode="numeric" class="max-w-xs" />
        <flux:input wire:model="address" :label="__('receipt.address')" :description="__('receipt.address_help')" />
        <flux:select wire:model="vatPercent" :label="__('receipt.vat_label')" :description="__('receipt.vat_help')" class="max-w-xs">
            <option value="">{{ __('receipt.vat_exempt') }}</option>
            @foreach ([25, 12, 6] as $percent)
                <option value="{{ $percent }}">{{ $percent }} %</option>
            @endforeach
        </flux:select>
        <div class="flex items-center gap-4">
            <flux:button type="submit" variant="primary">{{ __('receipt.save') }}</flux:button>
            <x-action-message on="receipt-saved">{{ __('receipt.saved') }}</x-action-message>
        </div>
    </form>
</div>
