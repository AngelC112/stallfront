<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_option_value', function (Blueprint $table) {
            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();

            $table->foreignId('option_value_id')
                ->constrained('product_option_values')
                ->cascadeOnDelete();

            $table->unique(['variant_id', 'option_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_value');
    }
};
