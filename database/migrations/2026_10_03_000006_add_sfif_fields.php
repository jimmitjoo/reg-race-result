<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('city')->nullable()->after('slug');
        });

        Schema::table('races', function (Blueprint $table) {
            $table->string('type')->default('Väg')->after('name')->comment('Väg, Terräng or Trail (SFIF)');
            $table->string('course_measurer')->nullable()->after('type');
            $table->date('measured_on')->nullable()->after('course_measurer');
            $table->boolean('age_groups')->default(false)->after('measured_on')->comment('The race awards age group placings (M35, F15 …)');
        });
    }

    public function down(): void
    {
        Schema::table('races', fn (Blueprint $table) => $table->dropColumn(['type', 'course_measurer', 'measured_on', 'age_groups']));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('city'));
    }
};
