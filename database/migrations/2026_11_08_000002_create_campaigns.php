<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Campaigns (#85): company and club registration with own prices, codes and invoice instead of card.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable()->comment('The shared code, upper case; null when the campaign has single-use codes');
            $table->boolean('single_use')->default(false);
            $table->json('prices')->nullable()->comment('{race_id: amount in minor units}; a race not in it keeps its own price');
            $table->unsignedInteger('cap')->nullable()->comment('Places in the campaign');
            $table->boolean('invoice')->default(false)->comment('Billed by the club outside Stripe');
            $table->string('invoice_name')->nullable();
            $table->string('invoice_org_number', 11)->nullable();
            $table->string('invoice_reference')->nullable();
            $table->string('invoice_email')->nullable();
            $table->dateTime('invoice_paid_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'code']);
        });

        Schema::create('campaign_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->date('valid_until')->nullable();
            $table->string('applies_to')->default('both')->comment('individual, team or both');
            $table->foreignId('registration_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('reserved_at')->nullable()->comment('Free again 30 minutes later unless the registration is paid');
            $table->dateTime('used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('race_class_id')->constrained()->nullOnDelete();
            $table->string('campaign_code')->nullable()->after('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn('campaign_code');
        });
        Schema::dropIfExists('campaign_codes');
        Schema::dropIfExists('campaigns');
    }
};
