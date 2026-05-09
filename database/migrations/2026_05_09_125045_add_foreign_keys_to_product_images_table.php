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
        Schema::table('product_images', function (Blueprint $table) {
            $table->foreign(['product_id'], 'product_images_ibfk_1')->references(['id'])->on('products')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['variant_id'], 'product_images_ibfk_2')->references(['id'])->on('product_variants')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropForeign('product_images_ibfk_1');
            $table->dropForeign('product_images_ibfk_2');
        });
    }
};
