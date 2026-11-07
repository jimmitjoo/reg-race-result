<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The organizer's own page as blocks (#115, #116): what is published, and the draft being edited.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->json('page')->nullable()->comment('Published blocks; see App\Support\Page. Null is the default page');
            $table->json('page_draft')->nullable()->comment('Shown in the editor and the preview until published');
        });
    }

    public function down(): void
    {
        Schema::table('organizers', fn (Blueprint $table) => $table->dropColumn(['page', 'page_draft']));
    }
};
