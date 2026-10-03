<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('races', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('onsite_price')->nullable()->comment('Minor units (öre/cent), organizer currency');
            $table->timestamps();
        });

        Schema::create('price_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_id')->constrained()->cascadeOnDelete();
            $table->date('until')->comment('Valid through this date in the event timezone');
            $table->unsignedInteger('amount')->comment('Minor units (öre/cent), organizer currency');
            $table->timestamps();
        });

        Schema::table('race_classes', function (Blueprint $table) {
            $table->foreignId('race_id')->nullable()->after('event_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->unsignedInteger('price')->nullable()->after('status')->comment('Price paid, minor units');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn('price'));
        Schema::table('race_classes', fn (Blueprint $table) => $table->dropConstrainedForeignId('race_id'));
        Schema::dropIfExists('price_steps');
        Schema::dropIfExists('races');
    }
};
