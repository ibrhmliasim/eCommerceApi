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
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->enum('old_status', ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'])->nullable();
            $table->enum('new_status', ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'returned']);
            $table->string('comment')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->enum('change_source', ['system', 'admin', 'user', 'webhook'])->nullable()->default('system');
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['order_id', 'created_at'], 'idx_order_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
