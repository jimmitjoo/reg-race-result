<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// An arrangemang's theme (#112), kept from year to year: what an upplaga of it chose moves up to it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->json('theme')->nullable()->comment('On top of the organizer\'s; see App\Support\Theme');
            $table->json('theme_draft')->nullable()->comment('Shown in the preview until published');
        });

        // The latest upplaga's choice is the arrangemang's.
        foreach (['theme', 'theme_draft'] as $column) {
            $events = DB::table('events')->whereNotNull('series_id')->whereNotNull($column)->orderBy('date')->get(['id', 'series_id', $column]);
            foreach ($events as $event) {
                DB::table('series')->where('id', $event->series_id)->update([$column => $event->{$column}]);
            }
            DB::table('events')->whereIn('id', $events->pluck('id'))->update([$column => null]);
        }
    }

    public function down(): void
    {
        Schema::table('series', fn (Blueprint $table) => $table->dropColumn(['theme', 'theme_draft']));
    }
};
