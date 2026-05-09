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
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('email')->unique('email');
            $table->string('password');
            $table->rememberToken();
            $table->string('phone', 20)->nullable()->unique('phone');
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->enum('role', ['user', 'admin'])->nullable()->default('user');
            $table->timestamp('email_verified_at')->nullable();
            $table->unsignedBigInteger('default_shipping_address_id')->nullable()->index('fk_default_shipping');
            $table->unsignedBigInteger('default_billing_address_id')->nullable()->index('fk_default_billing');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable()->useCurrent();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
