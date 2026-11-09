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
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->string('location');
            $table->unsignedTinyInteger('weekday');
            $table->unsignedTinyInteger('minimum_order_days')->default(3);
            $table->time('order_cutoff_time')->nullable();
            $table->time('pickup_cutoff_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();

            $table->index(['is_active', 'weekday']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('markets');
    }
};
