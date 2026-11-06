<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A registration in a team class is the whole team (#31): its contact, a team name and the members.
        Schema::table('race_classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('team_size')->nullable()->after('gender')->comment('Members per team; null for runners on their own');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->string('team_name')->nullable()->after('last_name');
            $table->json('team_members')->nullable()->after('team_name')->comment('Names of the members, in running order');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn(['team_name', 'team_members']));
        Schema::table('race_classes', fn (Blueprint $table) => $table->dropColumn('team_size'));
    }
};
