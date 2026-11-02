<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('stripe_payment_intent_id')->nullable()->index()->after('stripe_checkout_session_id')->comment('Links a refund in Stripe back to the registration');
            $table->dateTime('refunded_at')->nullable()->after('paid_at')->comment('Fully refunded in Stripe; no longer a participant');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn(['stripe_payment_intent_id', 'refunded_at']));
    }
};
