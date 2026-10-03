<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->after('locale')->comment('Connected account the organizer is paid to; null = platform account');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->string('stripe_checkout_session_id')->nullable()->index()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn('stripe_checkout_session_id'));
        Schema::table('organizers', fn (Blueprint $table) => $table->dropColumn('stripe_account_id'));
    }
};
