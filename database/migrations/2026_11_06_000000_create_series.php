<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // An arrangemang (Sylvesterloppet) that comes back every year; each event is one upplaga of it.
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->unique(['organizer_id', 'slug']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('organizer_id')->constrained()->cascadeOnDelete();
        });

        // "Sylvesterloppet 2025" and "Sylvesterloppet 2026" become two upplagor of one arrangemang.
        foreach (DB::table('events')->orderBy('date')->get() as $event) {
            $name = preg_replace('/\s+\d{4}$/', '', trim($event->name));
            $slug = Str::slug($name);
            $id = DB::table('series')->where('organizer_id', $event->organizer_id)->where('slug', $slug)->value('id')
                ?? DB::table('series')->insertGetId(['organizer_id' => $event->organizer_id, 'name' => $name, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('events')->where('id', $event->id)->update(['series_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('series_id');
        });
        Schema::dropIfExists('series');
    }
};
