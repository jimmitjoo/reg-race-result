<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->string('slug')->unique()->after('name');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('slug')->after('name');
            $table->unique(['organizer_id', 'slug']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('last_name');
            $table->char('gender', 1)->nullable()->after('birth_date');
            $table->string('club')->nullable()->after('gender')->comment('Club or home town, free text until #11');
            $table->char('country', 3)->nullable()->after('club');
            $table->string('email')->nullable()->after('country');
            $table->string('phone')->nullable()->after('email');
            $table->timestamp('paid_at')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn(['birth_date', 'gender', 'club', 'country', 'email', 'phone', 'paid_at']));
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['organizer_id', 'slug']);
            $table->dropColumn('slug');
        });
        Schema::table('organizers', fn (Blueprint $table) => $table->dropColumn('slug'));
    }
};
