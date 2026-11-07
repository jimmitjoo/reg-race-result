<?php

use App\Models\Organizer;

it('refuses to make a sandbox account without a test key', function () {
    $organizer = Organizer::factory()->unconnected()->create(['slug' => 'hogby-if']);
    config(['services.stripe.secret' => 'sk_live_123']);

    $this->artisan('stripe:sandbox-account', ['organizer' => 'hogby-if'])->assertFailed();

    expect($organizer->fresh()->stripe_account_id)->toBeNull();
});
