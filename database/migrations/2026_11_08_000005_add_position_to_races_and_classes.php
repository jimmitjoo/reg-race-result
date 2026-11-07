<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The organizer's own order of races and classes; null keeps the order by start and name.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('races', fn (Blueprint $table) => $table->unsignedInteger('position')->nullable());
        Schema::table('race_classes', fn (Blueprint $table) => $table->unsignedInteger('position')->nullable());
    }

    public function down(): void
    {
        Schema::table('races', fn (Blueprint $table) => $table->dropColumn('position'));
        Schema::table('race_classes', fn (Blueprint $table) => $table->dropColumn('position'));
    }
};
