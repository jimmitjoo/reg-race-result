<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The organizer's own forms, shown by a block on its page, and what people sent with them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('fields')->comment('[{id, label, type, required, options}]; see App\Models\Form::TYPES');
            $table->string('action')->default('both')->comment('email, store or both; see App\Models\Form::ACTIONS');
            $table->string('recipient')->nullable()->comment('Null sends to the club\'s first user');
            $table->string('button')->nullable();
            $table->string('thanks')->nullable();
            $table->timestamps();
        });

        Schema::create('form_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->json('answers')->comment('[{label, value}] as the form was when sent');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_responses');
        Schema::dropIfExists('forms');
    }
};
