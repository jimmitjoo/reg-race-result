<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Payments\StripeCheckout;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->travelTo(now()->setTimezone('Europe/Stockholm')->setDate(2026, 11, 15)->setTime(12, 0));
    config(['services.stripe.webhook_secret' => 'whsec_test']);
});

function paidEvent(int $amount = 25000): RaceClass
{
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if', 'currency' => 'SEK']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Sylvesterloppet 2026', 'slug' => 'sylvesterloppet-2026', 'date' => '2026-12-31']);
    $race = Race::factory()->for($event)->create(['name' => 'Sylvesterloppet']);
    $race->priceSteps()->create(['until' => '2026-12-30', 'amount' => $amount]);

    return RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Kvinnor 10 km']);
}

function submitRegistration(RaceClass $class)
{
    return Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026'])
        ->set('raceClassId', $class->id)
        ->set('firstName', 'Anna')
        ->set('lastName', 'Karlsson')
        ->set('birthDate', '1991-04-03')
        ->set('gender', 'K')
        ->set('email', 'anna@example.com')
        ->call('register');
}

function signedStripeEvent(array $event): array
{
    $payload = json_encode($event);
    $timestamp = time();

    return [$payload, "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test')];
}

it('sends the runner to Stripe Checkout and remembers the session', function () {
    $class = paidEvent();
    $this->mock(StripeCheckout::class)
        ->shouldReceive('create')->once()
        ->andReturn(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123']);

    submitRegistration($class)->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

    expect(Registration::sole())->stripe_checkout_session_id->toBe('cs_test_123')->paid_at->toBeNull();
});

it('marks free registrations as paid without Stripe', function () {
    $class = paidEvent(amount: 0);
    $this->mock(StripeCheckout::class)->shouldNotReceive('create');

    submitRegistration($class)->assertRedirectContains('/registrations/');

    expect(Registration::sole()->paid_at)->not->toBeNull();
});

it('builds the checkout session from the registration', function () {
    $class = paidEvent();
    $registration = Registration::factory()->for($class)->create(['price' => 25000, 'email' => 'anna@example.com']);

    $params = app(StripeCheckout::class)->params($registration, 'https://ok', 'https://cancel');

    expect($params)
        ->mode->toBe('payment')
        ->customer_email->toBe('anna@example.com')
        ->success_url->toBe('https://ok')
        ->cancel_url->toBe('https://cancel')
        ->client_reference_id->toBe((string) $registration->id)
        ->and($params['line_items'][0]['price_data'])->toMatchArray([
            'currency' => 'sek',
            'unit_amount' => 25000,
        ])
        ->and($params['line_items'][0]['price_data']['product_data']['name'])->toBe('Sylvesterloppet 2026 – Kvinnor 10 km')
        ->and($params['metadata'])->toBe(['registration_id' => (string) $registration->id]);
});

it('marks the registration paid on the confirmation page when Stripe says it is paid', function () {
    $class = paidEvent();
    $registration = Registration::factory()->for($class)->create(['stripe_checkout_session_id' => 'cs_test_123', 'paid_at' => null]);
    $this->mock(StripeCheckout::class)->shouldReceive('isPaid')->withArgs(fn ($r) => $r->is($registration))->andReturnTrue();

    $this->get(URL::signedRoute('public.registration', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026', 'registration' => $registration]))
        ->assertOk()
        ->assertSee(__('registration.paid'))
        ->assertDontSee(__('registration.unpaid'));

    expect($registration->fresh()->paid_at)->not->toBeNull();
});

it('marks the registration paid from the Stripe webhook', function () {
    $class = paidEvent();
    $registration = Registration::factory()->for($class)->create(['stripe_checkout_session_id' => 'cs_test_123']);

    [$payload, $signature] = signedStripeEvent([
        'id' => 'evt_1', 'object' => 'event', 'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_test_123', 'object' => 'checkout.session', 'payment_status' => 'paid', 'metadata' => ['registration_id' => (string) $registration->id]]],
    ]);

    $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload)
        ->assertOk();

    expect($registration->fresh()->paid_at)->not->toBeNull();
});

it('ignores unpaid sessions and unknown registrations in the webhook', function () {
    $class = paidEvent();
    $registration = Registration::factory()->for($class)->create(['stripe_checkout_session_id' => 'cs_test_123']);

    foreach ([['paid' => 'unpaid', 'id' => (string) $registration->id], ['paid' => 'paid', 'id' => '999999']] as $case) {
        [$payload, $signature] = signedStripeEvent([
            'id' => 'evt_2', 'object' => 'event', 'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_123', 'object' => 'checkout.session', 'payment_status' => $case['paid'], 'metadata' => ['registration_id' => $case['id']]]],
        ]);
        $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();
    }

    expect($registration->fresh()->paid_at)->toBeNull();
});

it('rejects webhooks with a bad signature', function () {
    $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=bad', 'CONTENT_TYPE' => 'application/json'], '{}')
        ->assertStatus(400);
});
