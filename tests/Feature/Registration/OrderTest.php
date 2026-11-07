<?php

use App\Mail\RegistrationReceipt;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Payments\StripeCheckout;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

// #109: several participants in one order, paid once.
beforeEach(function () {
    $this->travelTo(now()->setTimezone('Europe/Stockholm')->setDate(2026, 11, 15)->setTime(12, 0));
    config(['services.stripe.connect_webhook_secret' => 'whsec_connect']);
    Mail::fake();
});

/** Five classes in two races: 10 km (250 kr) for adults, the children's race (50 kr, or free). */
function orderEvent(int $childPrice = 5000): Collection
{
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if', 'currency' => 'SEK', 'stripe_account_id' => 'acct_hogby', 'stripe_charges_enabled' => true, 'fee_basis_points' => 0, 'fee_fixed' => 300]);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'slug' => 'sylvesterloppet-2026', 'date' => '2026-12-31']);
    $adults = Race::factory()->for($event)->create(['name' => '10 km']);
    $adults->priceSteps()->create(['until' => '2026-12-30', 'amount' => 25000]);
    $children = Race::factory()->for($event)->create(['name' => 'Barnloppet']);
    $children->priceSteps()->create(['until' => '2026-12-30', 'amount' => $childPrice]);

    return collect([
        'women' => [$adults, 'Kvinnor 10 km', 'K', 10000],
        'men' => [$adults, 'Män 10 km', 'M', 10000],
        'girls' => [$children, 'F9 1 km', 'K', 1000],
        'boys' => [$children, 'P9 1 km', 'M', 1000],
        'small' => [$children, 'Knatte 500 m', null, 500],
    ])->map(fn ($c) => RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $c[0]->id, 'name' => $c[1], 'gender' => $c[2], 'distance_meters' => $c[3]]));
}

function orderForm(array $people)
{
    $page = Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']);
    foreach ($people as $i => [$class, $first, $gender, $born]) {
        if ($i > 0) {
            $page->call('addParticipant');
        }
        $page->set("participants.{$i}.raceClassId", (string) $class->id)
            ->set("participants.{$i}.firstName", $first)
            ->set("participants.{$i}.lastName", 'Karlsson')
            ->set("participants.{$i}.birthDate", $born)
            ->set("participants.{$i}.gender", $gender);
    }

    return $page->set('email', 'familjen@example.com')->set('acceptTerms', true);
}

function family(Collection $classes): array
{
    return [
        [$classes['women'], 'Anna', 'K', '1985-03-01'],
        [$classes['men'], 'Bo', 'M', '1984-06-12'],
        [$classes['girls'], 'Cilla', 'K', '2018-05-05'],
        [$classes['boys'], 'Dan', 'M', '2019-08-08'],
        [$classes['small'], 'Eva', 'K', '2022-01-20'],
    ];
}

function orderWebhook(array $event)
{
    $payload = json_encode(['object' => 'event'] + $event);
    $timestamp = time();

    return test()->call('POST', route('stripe.connect-webhook'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_connect'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

it('takes five participants in different classes to one Checkout with a line each and the fee per participant', function () {
    $classes = orderEvent();
    $sent = null;
    $this->mock(StripeCheckout::class)->shouldReceive('create')->once()
        ->andReturnUsing(function (Collection $order) use (&$sent) {
            $sent = $order;

            return ['id' => 'cs_family', 'url' => 'https://checkout.stripe.com/c/pay/cs_family'];
        });

    orderForm(family($classes))
        ->assertSee(__('registration.pay', ['price' => Money::format(65000, 'SEK')]))
        ->call('register')->assertHasNoErrors()->assertRedirect('https://checkout.stripe.com/c/pay/cs_family');

    $order = Registration::orderBy('id')->get();
    expect($order)->toHaveCount(5)
        ->and($order->pluck('order_ref')->unique())->toHaveCount(1)
        ->and($order->pluck('stripe_checkout_session_id')->unique()->all())->toBe(['cs_family'])
        ->and($order->pluck('email')->unique()->all())->toBe(['familjen@example.com'])
        ->and($order->pluck('first_name')->all())->toBe(['Anna', 'Bo', 'Cilla', 'Dan', 'Eva'])
        ->and($sent)->toHaveCount(5);

    $params = (new StripeCheckout)->params($order, 'https://ok', 'https://cancel');
    expect($params['line_items'])->toHaveCount(5)
        ->and(array_sum(array_map(fn ($line) => $line['price_data']['unit_amount'], $params['line_items'])))->toBe(65000)
        ->and($params['payment_intent_data'])->toBe(['application_fee_amount' => 5 * 300])
        ->and($params['metadata']['order_ref'])->toBe($order->first()->order_ref);
});

it('marks the whole order paid from the webhook, the confirmation page and reconciliation, with one receipt', function () {
    $classes = orderEvent();
    $this->mock(StripeCheckout::class)->shouldReceive('create')->andReturn(['id' => 'cs_family', 'url' => 'https://checkout.stripe.com/x']);
    orderForm(family($classes))->call('register');
    $first = Registration::orderBy('id')->first();

    $paid = ['id' => 'evt_1', 'type' => 'checkout.session.completed', 'account' => 'acct_hogby', 'data' => ['object' => [
        'id' => 'cs_family', 'object' => 'checkout.session', 'payment_status' => 'paid', 'payment_intent' => 'pi_family',
        'metadata' => ['registration_id' => (string) $first->id, 'order_ref' => $first->order_ref],
    ]]];
    orderWebhook($paid)->assertOk();
    orderWebhook($paid)->assertOk();

    expect(Registration::whereNull('paid_at')->count())->toBe(0)
        ->and(Registration::where('stripe_payment_intent_id', 'pi_family')->count())->toBe(5);
    Mail::assertQueued(RegistrationReceipt::class, 1);

    // The confirmation shows everyone in the order.
    $this->get($first->confirmationUrl())->assertOk()
        ->assertSee(__('registration.confirmation_text_many', ['count' => 5, 'event' => $first->raceClass->event->name]))
        ->assertSee(['Anna Karlsson', 'Eva Karlsson', 'Knatte 500 m', Money::format(65000, 'SEK')]);

    // A receipt lists the whole order.
    $receipt = (new RegistrationReceipt($first->fresh()))->render();
    expect($receipt)->toContain('Anna Karlsson')->toContain('Dan Karlsson')->toContain(Money::format(65000, 'SEK'));
});

it('asks Stripe once per order when reconciling', function () {
    $classes = orderEvent();
    $this->mock(StripeCheckout::class, function ($mock) {
        $mock->shouldReceive('create')->andReturn(['id' => 'cs_family', 'url' => 'https://checkout.stripe.com/x']);
        $mock->shouldReceive('isPaid')->once()->andReturnTrue();
    });
    orderForm(family($classes))->call('register');

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect(Registration::whereNull('paid_at')->count())->toBe(0);
    Mail::assertQueued(RegistrationReceipt::class, 1);
});

it('stops the order at an error and shows it at the right participant', function () {
    $classes = orderEvent();
    $this->mock(StripeCheckout::class)->shouldNotReceive('create');
    $people = family($classes);
    $people[1] = [$classes['women'], 'Bo', 'M', '1984-06-12'];

    orderForm($people)->call('register')
        ->assertHasErrors(['participants.1.raceClassId'])
        ->assertHasNoErrors(['participants.0.raceClassId', 'participants.2.raceClassId'])
        ->assertSee(__('registration.participant_n', ['n' => 2]).': '.__('registration.wrong_gender'));

    expect(Registration::count())->toBe(0);
});

it('marks an order of only free classes paid without Stripe', function () {
    $classes = orderEvent(childPrice: 0);
    $this->mock(StripeCheckout::class)->shouldNotReceive('create');

    orderForm(array_slice(family($classes), 2))->call('register')->assertHasNoErrors()->assertRedirectContains('/registrations/');

    expect(Registration::count())->toBe(3)->and(Registration::whereNull('paid_at')->count())->toBe(0);
    Mail::assertQueued(RegistrationReceipt::class, 1);
});

it('leaves a free participant out of Stripe but in the paid order', function () {
    $classes = orderEvent(childPrice: 0);
    $this->mock(StripeCheckout::class)->shouldReceive('create')->andReturn(['id' => 'cs_family', 'url' => 'https://checkout.stripe.com/x']);
    $people = family($classes);
    orderForm([$people[0], $people[2]])->call('register');

    $params = (new StripeCheckout)->params(Registration::orderBy('id')->get(), 'https://ok', 'https://cancel');
    expect($params['line_items'])->toHaveCount(1)->and($params['payment_intent_data'])->toBe(['application_fee_amount' => 300]);
});

it('refunds the whole order on a full refund', function () {
    $classes = orderEvent();
    $this->mock(StripeCheckout::class)->shouldReceive('create')->andReturn(['id' => 'cs_family', 'url' => 'https://checkout.stripe.com/x']);
    orderForm(family($classes))->call('register');
    Registration::orderBy('id')->first()->markPaid('stripe', 'pi_family');

    orderWebhook(['id' => 'evt_r', 'type' => 'charge.refunded', 'account' => 'acct_hogby', 'data' => ['object' => ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_family', 'refunded' => true]]])->assertOk();

    expect(Registration::whereNull('refunded_at')->count())->toBe(0);
});

it('adds and removes participants, at most twenty', function () {
    $classes = orderEvent();
    $page = Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026'])
        ->set('participants.0.raceClassId', (string) $classes['women']->id);

    $page->call('addParticipant')->assertSee(__('registration.participant_n', ['n' => 2]))
        ->set('participants.1.firstName', 'Bo')
        ->call('removeParticipant', 0)
        ->assertSet('participants.0.firstName', 'Bo')->assertCount('participants', 1);

    foreach (range(1, 25) as $n) {
        $page->call('addParticipant');
    }
    $page->assertCount('participants', 20)->assertDontSee(__('registration.add_participant'));
});
