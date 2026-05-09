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
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->index('idx_order');
            $table->string('transaction_id')->unique('transaction_id');
            $table->string('idempotency_key')->nullable()->unique('idempotency_key');
            $table->string('payment_method', 50);
            $table->decimal('amount', 10);
            $table->string('currency', 3)->default('JPY');
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'refunded', 'cancelled'])->index('idx_status');
            $table->string('error_code', 50)->nullable();
            $table->string('error_message')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
