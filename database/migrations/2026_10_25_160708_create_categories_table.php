<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            // true = disponibile tutto l'anno
            // false = disponibile solo nel periodo indicato
            $table->boolean('always_available')->default(true);

            $table->unsignedTinyInteger('season_start_month')->nullable();
            $table->unsignedTinyInteger('season_start_day')->nullable();

            $table->unsignedTinyInteger('season_end_month')->nullable();
            $table->unsignedTinyInteger('season_end_day')->nullable();

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
