<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('race_director')->nullable()->after('city');
            $table->string('weather')->nullable()->after('race_director');
            $table->string('contact_email')->nullable()->after('weather')->comment('Shown as "Synpunkter till" on result lists');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['race_director', 'weather', 'contact_email']));
    }
};
