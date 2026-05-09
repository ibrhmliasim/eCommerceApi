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
        Schema::create('product_images', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable()->index('idx_variant');
            $table->string('url');
            $table->boolean('is_main')->nullable()->default(false);
            $table->integer('sort_order')->nullable()->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['product_id', 'sort_order'], 'idx_product_sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
