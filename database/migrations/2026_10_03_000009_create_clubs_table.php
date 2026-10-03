<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('federation')->comment('e.g. SFIF');
            $table->string('external_id')->comment('The club id in the federation list');
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('district')->nullable();
            $table->string('municipality')->nullable();
            $table->timestamps();
            $table->unique(['federation', 'external_id']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable()->after('club')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->dropConstrainedForeignId('club_id'));
        Schema::dropIfExists('clubs');
    }
};
