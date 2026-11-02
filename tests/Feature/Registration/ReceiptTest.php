<?php

use App\Mail\RegistrationReceipt;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use App\Payments\StripeCheckout;
use Illuminate\Support\Facades\Mail;

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
        ->assertSeeInHtml('2026-12-31')
        ->assertSeeInHtml('11:00')
        ->assertSeeInHtml(e(App\Support\Money::format(25000, 'SEK')))
        ->assertSeeInHtml(__('registrations.method.stripe'))
        ->assertSeeInHtml(e($registration->confirmationUrl()))
        ->assertSeeInHtml(route('public.terms', 'hogby-if'));
});

it('falls back to a club user as reply address when the event has no contact', function () {
    $class = receiptClass();
    User::factory()->create(['organizer_id' => $class->event->organizer_id, 'email' => 'kansli@hogbyif.se']);

    (new RegistrationReceipt(receiptRegistration($class)))->assertHasReplyTo('kansli@hogbyif.se');
});
