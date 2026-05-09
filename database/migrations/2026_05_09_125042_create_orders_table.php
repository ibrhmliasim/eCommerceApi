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
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('cart_id')->nullable()->index('cart_id');
            $table->string('shipping_first_name', 100);
            $table->string('shipping_last_name', 100);
            $table->string('shipping_email');
            $table->string('shipping_phone', 20)->nullable();
            $table->string('shipping_postal_code', 8);
            $table->string('shipping_prefecture', 100);
            $table->string('shipping_city', 100);
            $table->string('shipping_ward', 100)->nullable();
            $table->string('shipping_address_line1');
            $table->string('shipping_address_line2')->nullable();
            $table->decimal('subtotal', 10);
            $table->decimal('shipping_cost', 10)->default(0);
            $table->decimal('tax_amount', 10)->default(0);
            $table->decimal('total_amount', 10);
            $table->string('currency', 3)->default('JPY');
            $table->enum('status', ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'])->nullable()->default('pending');
            $table->string('tracking_number', 100)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable()->useCurrent();

            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index(['user_id', 'created_at'], 'idx_user_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
