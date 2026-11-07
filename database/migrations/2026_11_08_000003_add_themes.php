<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// An organizer's theme, and an upplaga's on top of it (#112): what is published, and what is being tried out.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['organizers', 'events'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->json('theme')->nullable()->comment('Published: brand, accent, fonts, shape, logo; see App\Support\Theme');
                $table->json('theme_draft')->nullable()->comment('Shown only in the preview until published');
            });
        }
    }

    public function down(): void
    {
        foreach (['organizers', 'events'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn(['theme', 'theme_draft']));
        }
    }
};
