<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('username');
            $table->text('app_password')->comment('Encrypted WordPress application password');
            $table->unsignedBigInteger('results_parent_page_id')->nullable()->comment('Result pages are created under this page');
            $table->timestamps();
        });

        Schema::create('event_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wordpress_site_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('media_id');
            $table->string('media_url');
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('page_url')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'wordpress_site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_publications');
        Schema::dropIfExists('wordpress_sites');
    }
};
