<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// An upplaga's page as blocks, like the organizer's (#115): what is published, and the draft being edited.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('page')->nullable()->comment('Published blocks; see App\Support\Page. Null is the default page');
            $table->json('page_draft')->nullable()->comment('Shown in the editor and the preview until published');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['page', 'page_draft']));
    }
};
