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
        Schema::create('order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->index('idx_order');
            $table->unsignedBigInteger('variant_id')->nullable()->index('idx_variant');
            $table->string('product_name');
            $table->string('sku', 50);
            $table->string('color', 50);
            $table->string('size', 20);
            $table->decimal('price_at_purchase', 10);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
