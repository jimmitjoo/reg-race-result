<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('phone');
            $table->timestamp('hidden_at')->nullable()->after('terms_accepted_at')->comment('Shown as anonymous in public lists on request');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn(['terms_accepted_at', 'hidden_at']));
    }
};
