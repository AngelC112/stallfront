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
            $table->id();
            $table->string('order_number', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 30)->nullable();
            $table->string('fulfillment_method', 20);
            $table->string('payment_method', 30);
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('status', 30)->default('received');
            $table->date('pickup_date')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('customer_note')->nullable();
            $table->unsignedInteger('items_subtotal_cents');
            $table->unsignedInteger('delivery_cost_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->timestamp('placed_at');
            $table->timestamps();

            $table->index(['user_id', 'placed_at']);
            $table->index(['fulfillment_method', 'status']);
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
