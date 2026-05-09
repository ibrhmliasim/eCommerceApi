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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 50)->unique('sku');
            $table->string('color', 50);
            $table->string('size', 20);
            $table->decimal('price', 12);
            $table->decimal('compare_at_price', 12)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable()->useCurrent();

            $table->index(['product_id', 'color', 'size'], 'idx_variants_product_filter');
            $table->unique(['product_id', 'color', 'size'], 'uq_variant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
