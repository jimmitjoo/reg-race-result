<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->boolean('stripe_details_submitted')->default(false)->after('stripe_account_id')->comment('Synced from Stripe, never set by a public route');
            $table->boolean('stripe_charges_enabled')->default(false)->after('stripe_details_submitted')->comment('Synced from Stripe; online payments need it');
            $table->unsignedSmallInteger('fee_basis_points')->default(0)->after('stripe_charges_enabled')->comment('Platform fee per registration, 100 = 1 %');
            $table->unsignedInteger('fee_fixed')->default(0)->after('fee_basis_points')->comment('Platform fee per registration in minor units');
        });
    }

    public function down(): void
    {
        Schema::table('organizers', fn (Blueprint $table) => $table->dropColumn(['stripe_details_submitted', 'stripe_charges_enabled', 'fee_basis_points', 'fee_fixed']));
    }
};
