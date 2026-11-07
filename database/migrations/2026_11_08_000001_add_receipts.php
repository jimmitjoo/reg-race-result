<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A receipt that holds for bookkeeping and friskvårdsbidrag (#132): the seller with its organisation number, and a number.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->string('org_number', 11)->nullable()->after('name')->comment('Swedish organisationsnummer, NNNNNN-NNNN');
            $table->string('address')->nullable()->after('org_number');
            $table->unsignedTinyInteger('vat_percent')->nullable()->after('address')->comment('VAT included in prices; null when exempt, as for a non-profit sports club');
            $table->unsignedInteger('receipt_counter')->default(0)->after('vat_percent');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->unsignedInteger('receipt_number')->nullable()->after('order_ref')->comment('Per organizer, the same for every registration in an order');
        });
    }

    public function down(): void
    {
        Schema::table('organizers', fn (Blueprint $table) => $table->dropColumn(['org_number', 'address', 'vat_percent', 'receipt_counter']));
        Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn('receipt_number'));
    }
};
