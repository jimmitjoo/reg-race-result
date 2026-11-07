<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use App\Payments\StripeCheckout;
use App\Payments\StripeConnect;
use App\Support\Money;
use Illuminate\Support\Number;
use Livewire\Volt\Volt;

// #47: payments go to the club's own connected Stripe account.
beforeEach(function () {
    $this->travelTo(now()->setTimezone('Europe/Stockholm')->setDate(2026, 11, 15)->setTime(12, 0));
    config(['services.stripe.webhook_secret' => 'whsec_platform', 'services.stripe.connect_webhook_secret' => 'whsec_connect']);
});

function connectEvent(array $organizer = []): RaceClass
{
    $organizer = Organizer::factory()->create(['slug' => 'kalmar-lk', 'stripe_account_id' => 'acct_kalmar'] + $organizer);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'slug' => 'kalmarmilen', 'date' => '2026-12-31']);
    $race = Race::factory()->for($event)->create(['name' => 'Kalmarmilen']);
    $race->priceSteps()->create(['until' => '2026-12-30', 'amount' => 25000]);

    return RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Kvinnor 10 km']);
}

function connectWebhook(array $event, string $secret = 'whsec_connect')
{
    $payload = json_encode(['object' => 'event'] + $event);
    $timestamp = time();

    return test()->call('POST', route('stripe.connect-webhook'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", $secret),
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

function paidSession(Registration $registration): array
{
    return ['object' => ['id' => $registration->stripe_checkout_session_id, 'object' => 'checkout.session', 'payment_status' => 'paid', 'metadata' => ['registration_id' => (string) $registration->id]]];
}

it('connects a Stripe account and shows the status Stripe reports, not the return visit', function () {
    $organizer = Organizer::factory()->unconnected()->create();
    $user = User::factory()->create(['organizer_id' => $organizer->id]);
    $this->actingAs($user);
    $stripe = $this->mock(StripeConnect::class);
    $stripe->shouldReceive('onboardingUrl')->once()->andReturnUsing(function (Organizer $o) {
        $o->update(['stripe_account_id' => 'acct_new']);

        return 'https://connect.stripe.com/setup/s/abc';
    });

    Volt::test('payments.index')->assertSee(__('payments.none'))->call('connect')->assertRedirect('https://connect.stripe.com/setup/s/abc');

    $stripe->shouldReceive('sync')->once()->andReturnUsing(fn (Organizer $o) => StripeConnect::apply($o, (object) ['details_submitted' => true, 'charges_enabled' => false]));
    $this->get(route('payments.return'))->assertRedirect(route('payments.index'));
    Volt::test('payments.index')->assertSee(__('payments.incomplete'));

    StripeConnect::apply($organizer->fresh(), (object) ['details_submitted' => true, 'charges_enabled' => true]);
    $organizer->update(['fee_basis_points' => 250, 'fee_fixed' => 300]);
    $this->actingAs($user->fresh());
    Volt::test('payments.index')->assertSee(__('payments.ready'))
        ->assertSee(__('payments.fee', ['fee' => Number::percentage(2.5, maxPrecision: 2, locale: 'sv').' + '.Money::format(300, 'SEK')]));
});

it('keeps the return routes behind sign-in', function () {
    $this->get(route('payments.return'))->assertRedirect(route('login'));
    $this->get(route('payments.refresh'))->assertRedirect(route('login'));
});

it('updates the account status from the Connect webhook', function () {
    $organizer = Organizer::factory()->create(['stripe_account_id' => 'acct_kalmar', 'stripe_charges_enabled' => false]);

    connectWebhook(['id' => 'evt_1', 'type' => 'account.updated', 'account' => 'acct_kalmar', 'data' => ['object' => ['id' => 'acct_kalmar', 'object' => 'account', 'details_submitted' => true, 'charges_enabled' => true]]])->assertOk();

    expect($organizer->fresh())->stripe_charges_enabled->toBeTrue()->stripe_details_submitted->toBeTrue();
});

it('takes the platform fee on the connected account, and none when the fee is zero', function () {
    $class = connectEvent(['fee_basis_points' => 250, 'fee_fixed' => 300]);
    $registration = Registration::factory()->for($class)->unpaid()->create(['price' => 25000]);

    expect(app(StripeCheckout::class)->params(collect([$registration]), 'https://ok', 'https://cancel')['payment_intent_data'])->toBe(['application_fee_amount' => 925]);

    $class->event->organizer->update(['fee_basis_points' => 0, 'fee_fixed' => 0]);
    expect(app(StripeCheckout::class)->params(collect([$registration->fresh()]), 'https://ok', 'https://cancel'))->not->toHaveKey('payment_intent_data');
});

it('never takes a fee larger than the price', function () {
    expect(Organizer::factory()->make(['fee_fixed' => 5000])->platformFee(3000))->toBe(3000);
});

it('marks paid from the Connect webhook only for the registration\'s own account', function () {
    $class = connectEvent();
    $registration = Registration::factory()->for($class)->unpaid()->create(['stripe_checkout_session_id' => 'cs_1']);

    connectWebhook(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'account' => 'acct_someone_else', 'data' => paidSession($registration)])->assertOk();
    expect($registration->fresh()->paid_at)->toBeNull();

    connectWebhook(['id' => 'evt_2', 'type' => 'checkout.session.completed', 'account' => 'acct_kalmar', 'data' => paidSession($registration)], 'whsec_platform')->assertStatus(400);
    expect($registration->fresh()->paid_at)->toBeNull();

    connectWebhook(['id' => 'evt_3', 'type' => 'checkout.session.completed', 'account' => 'acct_kalmar', 'data' => paidSession($registration)])->assertOk();
    expect($registration->fresh()->paid_at)->not->toBeNull();
});

it('reconciles paid checkouts the webhook missed, from the last 24 hours only', function () {
    $class = connectEvent();
    $missed = Registration::factory()->for($class)->unpaid()->create(['stripe_checkout_session_id' => 'cs_missed']);
    $abandoned = Registration::factory()->for($class)->unpaid()->create(['stripe_checkout_session_id' => 'cs_abandoned']);
    $old = Registration::factory()->for($class)->unpaid()->create(['stripe_checkout_session_id' => 'cs_old', 'created_at' => now()->subDays(2)]);
    $this->mock(StripeCheckout::class)->shouldReceive('isPaid')->twice()->andReturnUsing(fn (Registration $r) => $r->is($missed));

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($missed->fresh()->paid_at)->not->toBeNull()
        ->and($missed->fresh()->payment_method)->toBe('stripe')
        ->and($abandoned->fresh()->paid_at)->toBeNull()
        ->and($old->fresh()->paid_at)->toBeNull();
});

it('does not take online registrations for paid races until the account can take payments', function () {
    $class = connectEvent(['stripe_charges_enabled' => false]);
    $free = RaceClass::factory()->create(['event_id' => $class->event_id, 'race_id' => Race::factory()->for($class->event)->create(['name' => 'Barnloppet'])->id, 'name' => 'Barn']);
    $free->race->priceSteps()->create(['until' => '2026-12-30', 'amount' => 0]);
    $this->mock(StripeCheckout::class)->shouldNotReceive('create');

    $page = Volt::test('public.event', ['organizer' => 'kalmar-lk', 'event' => 'kalmarmilen'])
        ->assertSee(__('registration.opens_soon'))
        // The paid race's classes cannot be chosen; the free race's can.
        ->assertSeeHtml('value="'.$class->id.'" wire:model.live="participants.0.raceClassId" class="peer sr-only" disabled')
        ->assertDontSeeHtml('value="'.$free->id.'" wire:model.live="participants.0.raceClassId" class="peer sr-only" disabled');

    $page->set('participants.0.raceClassId', $class->id)->set('participants.0.firstName', 'Anna')->set('participants.0.lastName', 'Karlsson')->set('participants.0.birthDate', '1991-04-03')
        ->set('participants.0.gender', 'K')->set('email', 'anna@example.com')->set('acceptTerms', true)
        ->call('register')->assertHasErrors(['participants.0.raceClassId']);
    expect(Registration::count())->toBe(0);

    $page->set('participants.0.raceClassId', $free->id)->call('register')->assertHasNoErrors();
    expect(Registration::sole()->paid_at)->not->toBeNull();

    $this->actingAs(User::factory()->create(['organizer_id' => $class->event->organizer_id]))
        ->get(route('events.show', $class->event))->assertSee(__('events.registration_needs_stripe'));
});

it('stops online payments when the club disconnects the platform in Stripe', function () {
    $organizer = Organizer::factory()->create(['stripe_account_id' => 'acct_kalmar']);

    connectWebhook(['id' => 'evt_1', 'type' => 'account.application.deauthorized', 'account' => 'acct_kalmar', 'data' => ['object' => ['id' => 'ca_1', 'object' => 'application']]])->assertOk();

    expect($organizer->fresh())->stripe_account_id->toBeNull()->and($organizer->fresh()->canTakePayments())->toBeFalse();
});

it('takes a runner refunded in full in Stripe out of the participants, but not on a partial refund', function () {
    $class = connectEvent();
    $registration = Registration::factory()->for($class)->unpaid()->create(['stripe_checkout_session_id' => 'cs_1', 'first_name' => 'Anna']);
    connectWebhook(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'account' => 'acct_kalmar', 'data' => ['object' => paidSession($registration)['object'] + ['payment_intent' => 'pi_1']]]);
    expect($registration->fresh()->stripe_payment_intent_id)->toBe('pi_1');

    $refund = fn (bool $full, string $account = 'acct_kalmar') => connectWebhook(['id' => 'evt_r', 'type' => 'charge.refunded', 'account' => $account, 'data' => ['object' => ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_1', 'refunded' => $full]]])->assertOk();

    $refund(false);
    $refund(true, 'acct_someone_else');
    expect($registration->fresh()->refunded_at)->toBeNull()->and($class->event->registrations()->count())->toBe(1);

    $refund(true);
    expect($registration->fresh()->refunded_at)->not->toBeNull()->and($class->event->registrations()->count())->toBe(0);

    $this->actingAs(User::factory()->create(['organizer_id' => $class->event->organizer_id]));
    Volt::test('events.registrations', ['event' => $class->event])->assertDontSee('Anna')
        ->set('status', 'all')->assertSee('Anna')->assertSee(__('registrations.refunded'));
});

it('shows what the club gets from a registration after the fees', function () {
    $organizer = Organizer::factory()->create(['fee_basis_points' => 0, 'fee_fixed' => 500]);
    $this->actingAs(User::factory()->create(['organizer_id' => $organizer->id]));

    Volt::test('payments.index')->assertSee(__('payments.example', [
        'price' => Money::format(30000, 'SEK'),
        'stripe' => Money::format(630, 'SEK'),
        'platform' => Money::format(500, 'SEK'),
        'club' => Money::format(28870, 'SEK'),
    ]));
});
