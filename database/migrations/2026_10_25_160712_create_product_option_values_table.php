<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('option_type_id')
                ->constrained('product_option_types')
                ->cascadeOnDelete();

            $table->string('value');
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->unique(['option_type_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_values');
    }
};
