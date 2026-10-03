<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('country', 2);
            $table->string('timezone');
            $table->char('currency', 3);
            $table->string('locale', 10);
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained();
            $table->string('name');
            $table->date('date');
            $table->string('timezone');
            $table->timestamps();
        });

        Schema::create('race_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('distance_meters')->nullable();
            $table->char('gender', 1)->nullable();
            $table->boolean('timed')->default(true);
            $table->dateTime('start_at');
            $table->unsignedInteger('min_time_seconds')->default(0);
            $table->timestamps();
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_class_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('bib')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('status')->default('registered');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('chips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->unsignedInteger('bib');
            $table->timestamps();
            $table->unique(['event_id', 'code']);
            $table->unique(['event_id', 'bib']);
        });

        Schema::create('chip_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('reader');
            $table->string('chip');
            $table->dateTime('read_at', precision: 3);
            $table->unsignedSmallInteger('unit');
            $table->unsignedSmallInteger('antenna');
            $table->timestamp('created_at')->nullable();
            $table->unique(['event_id', 'reader', 'chip', 'read_at', 'unit', 'antenna'], 'chip_reads_line_unique');
        });
    }

    public function down(): void
    {
        foreach (['chip_reads', 'chips', 'registrations', 'race_classes', 'events', 'organizers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
