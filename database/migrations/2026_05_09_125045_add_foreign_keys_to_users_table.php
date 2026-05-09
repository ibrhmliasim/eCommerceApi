<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign(['default_billing_address_id'], 'fk_default_billing')->references(['id'])->on('addresses')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['default_shipping_address_id'], 'fk_default_shipping')->references(['id'])->on('addresses')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('fk_default_billing');
            $table->dropForeign('fk_default_shipping');
        });
    }
};
