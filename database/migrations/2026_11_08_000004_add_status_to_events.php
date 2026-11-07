<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A cancelled or moved upplaga says so first of all (#117), on its page and in schema.org's eventStatus.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('cancelled_at')->nullable()->after('date');
            $table->date('moved_from')->nullable()->after('cancelled_at')->comment('The first date, when the upplaga has been moved');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['cancelled_at', 'moved_from']));
    }
};
