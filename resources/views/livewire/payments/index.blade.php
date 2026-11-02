<?php

use App\Payments\StripeConnect;
use App\Support\Money;
use Livewire\Volt\Component;

new class extends Component {
    public function mount(): void
    {
        abort_unless(auth()->user()->organizer_id, 403);
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
</div>
