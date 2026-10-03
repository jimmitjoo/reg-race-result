<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dateTime('manual_finish_at', precision: 3)->nullable()->after('status')->comment('Finish time entered by hand (UTC); replaces reads');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn('manual_finish_at'));
    }
};
