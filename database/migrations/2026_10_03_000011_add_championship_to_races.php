<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->string('championship')->nullable()->after('age_groups')->comment('DM or SM');
            $table->json('championship_districts')->nullable()->after('championship')->comment('DM: districts in the federation club list that count');
            $table->boolean('championship_veterans')->default(false)->after('championship_districts')->comment('Also veteran placings (VDM/VSM)');
        });
    }

    public function down(): void
    {
        Schema::table('races', fn (Blueprint $table) => $table->dropColumn(['championship', 'championship_districts', 'championship_veterans']));
    }
};
