<?php

use App\Mail\RegistrationReceipt;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use App\Payments\StripeCheckout;
use App\Support\Money;
use App\Support\Receipt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

// #10: the runner gets a receipt by email when the registration is paid, once.
beforeEach(function () {
    Mail::fake();
    config(['services.stripe.connect_webhook_secret' => 'whsec_connect']);
});

function receiptClass(array $event = []): RaceClass
{
    $organizer = Organizer::factory()->create(['name' => 'Högby IF', 'slug' => 'hogby-if', 'stripe_account_id' => 'acct_hogby']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Sylvesterloppet 2026', 'slug' => 'sylvesterloppet-2026', 'date' => '2026-12-31', 'timezone' => 'Europe/Stockholm'] + $event);
    $race = Race::factory()->for($event)->create(['name' => 'Sylvesterloppet']);

    return RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Kvinnor 10 km', 'start_at' => '2026-12-31 10:00:00']);
}

function receiptRegistration(RaceClass $class, array $attributes = []): Registration
{
    return Registration::factory()->for($class)->unpaid()->create($attributes + [
        'first_name' => 'Anna', 'last_name' => 'Karlsson', 'email' => 'anna@example.com', 'price' => 25000, 'stripe_checkout_session_id' => 'cs_1',
    ]);
}

it('sends one receipt when the webhook, reconciliation and confirmation page all see the payment', function () {
    $registration = receiptRegistration(receiptClass());
    $this->mock(StripeCheckout::class)->shouldReceive('isPaid')->andReturnTrue();

    $payload = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => 'checkout.session.completed', 'account' => 'acct_hogby',
        'data' => ['object' => ['id' => 'cs_1', 'object' => 'checkout.session', 'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'metadata' => ['registration_id' => (string) $registration->id]]]]);
    $t = time();
    $this->call('POST', route('stripe.connect-webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", 'whsec_connect'), 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();
    $this->artisan('payments:reconcile')->assertSuccessful();
    $this->get($registration->confirmationUrl())->assertOk();
    $registration->fresh()->markPaid('stripe');

    Mail::assertQueuedCount(1);
    Mail::assertQueued(RegistrationReceipt::class, fn ($mail) => $mail->hasTo('anna@example.com'));
});

it('sends the receipt from reconciliation and from the confirmation page too', function () {
    $class = receiptClass();
    $reconciled = receiptRegistration($class, ['email' => 'a@example.com', 'stripe_checkout_session_id' => 'cs_a']);
    $this->mock(StripeCheckout::class)->shouldReceive('isPaid')->andReturnUsing(fn (Registration $r) => $r->is($reconciled));
    $this->artisan('payments:reconcile');
    Mail::assertQueued(RegistrationReceipt::class, fn ($mail) => $mail->hasTo('a@example.com'));

    $confirmed = receiptRegistration($class, ['email' => 'b@example.com', 'stripe_checkout_session_id' => 'cs_b']);
    $this->mock(StripeCheckout::class)->shouldReceive('isPaid')->andReturnTrue();
    $this->get($confirmed->confirmationUrl())->assertOk();
    Mail::assertQueued(RegistrationReceipt::class, fn ($mail) => $mail->hasTo('b@example.com'));
});

it('sends nothing for unpaid registrations or on-site registrations without email', function () {
    $class = receiptClass();
    receiptRegistration($class);
    receiptRegistration($class, ['email' => null, 'stripe_checkout_session_id' => null])->markPaid('swish_onsite');

    Mail::assertNothingQueued();
});

it('writes the receipt in Swedish with the event details and the organizer as reply address', function () {
    $class = receiptClass(['contact_email' => 'tavling@hogbyif.se']);
    $registration = receiptRegistration($class, ['payment_method' => 'stripe']);
    $registration->update(['paid_at' => now(), 'payment_method' => 'stripe']);
    $mail = new RegistrationReceipt($registration->fresh());

    $mail->assertHasSubject('Din anmälan till Sylvesterloppet 2026')
        ->assertHasReplyTo('tavling@hogbyif.se')
        ->assertSeeInHtml('Tack för din anmälan!')
        ->assertSeeInHtml('Sylvesterloppet')
        ->assertSeeInHtml('Kvinnor 10 km')
        ->assertSeeInHtml('torsdag 31 december 2026')
        ->assertSeeInHtml('kl 11.00')
        ->assertSeeInHtml(e(Money::format(25000, 'SEK')))
        ->assertSeeInHtml(__('registrations.method.stripe'))
        ->assertSeeInHtml(e($registration->confirmationUrl()))
        ->assertSeeInHtml(route('public.terms', 'hogby-if'));
});

it('falls back to a club user as reply address when the event has no contact', function () {
    $class = receiptClass();
    User::factory()->create(['organizer_id' => $class->event->organizer_id, 'email' => 'kansli@hogbyif.se']);

    (new RegistrationReceipt(receiptRegistration($class)))->assertHasReplyTo('kansli@hogbyif.se');
});

// #132: a receipt that holds for the books and for friskvårdsbidrag.
it('numbers receipts per organizer, once per order, and shows the seller with its organisation number', function () {
    $class = receiptClass();
    $class->event->organizer->update(['org_number' => '802000-1239', 'address' => 'Box 12, 380 74 Löttorp']);
    $first = receiptRegistration($class, ['order_ref' => 'order-a']);
    receiptRegistration($class, ['order_ref' => 'order-a', 'first_name' => 'Bo']);
    $other = receiptRegistration($class, ['order_ref' => 'order-b']);

    $first->markPaid('stripe');
    $first->markPaid('stripe');
    $other->markPaid('swish_onsite');

    expect(Registration::where('order_ref', 'order-a')->pluck('receipt_number')->unique()->all())->toBe([1])
        ->and($other->fresh()->receipt_number)->toBe(2)
        ->and(tap(receiptRegistration(receiptClassFor(Organizer::factory()->create()), ['order_ref' => 'x']), fn ($r) => $r->markPaid('free'))->fresh()->receipt_number)->toBe(1);

    $receipt = Receipt::for($first->fresh());
    $this->get($receipt['url'])->assertOk()
        ->assertSee(__('receipt.title_number', ['number' => 1]))
        ->assertSee(['Högby IF', 'Org.nr 802000-1239', 'Box 12, 380 74 Löttorp', 'Anna Karlsson', 'anna@example.com', 'Bo Karlsson'])
        ->assertSee(__('receipt.vat_exempt'))
        ->assertSee(e(Money::format(50000, 'SEK')), false);
    (new RegistrationReceipt($first->fresh()))->assertSeeInHtml('802000-1239')->assertSeeInHtml(__('receipt.title_number', ['number' => 1]));
});

it('shows VAT for an organizer that charges it, and the refund', function () {
    $class = receiptClass();
    $class->event->organizer->update(['vat_percent' => 6]);
    $registration = receiptRegistration($class, ['order_ref' => 'order-a', 'price' => 10600]);
    $registration->markPaid('stripe');
    $registration->update(['refunded_at' => now()]);

    $this->get(Receipt::for($registration->fresh())['url'])
        ->assertSee(__('receipt.vat', ['percent' => 6, 'amount' => Money::format(600, 'SEK')]))
        ->assertSee(__('receipt.refunded'));
});

it('keeps the receipt behind a signed link, and only once paid', function () {
    $registration = receiptRegistration(receiptClass());
    $url = URL::signedRoute('public.receipt', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026', 'registration' => $registration]);

    $this->get($url)->assertNotFound();
    $this->get(route('public.receipt', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026', 'registration' => $registration]))->assertForbidden();
});

it('lets the club give its organisation number, checked with its check digit', function () {
    $user = User::factory()->create(['organizer_id' => receiptClass()->event->organizer_id]);
    $this->actingAs($user);

    Volt::test('payments.index')
        ->set('orgNumber', '802000-1234')->call('saveReceipt')->assertHasErrors(['orgNumber'])
        ->set('orgNumber', '8020001239')->set('vatPercent', '25')->call('saveReceipt')->assertHasNoErrors()
        ->assertSet('orgNumber', '802000-1239');

    expect($user->organizer->fresh())->org_number->toBe('802000-1239')->vat_percent->toBe(25);
});

function receiptClassFor(Organizer $organizer): RaceClass
{
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);

    return RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => Race::factory()->for($event)->create()->id]);
}
