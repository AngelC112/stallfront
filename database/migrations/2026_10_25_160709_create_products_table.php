<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();

            $table->string('short_description')->nullable();
            $table->text('description')->nullable();

            // Prezzo in centesimi
            $table->unsignedInteger('price_cents')->default(0);

            // Prezzo IVA inclusa
            $table->boolean('vat_included')->default(true);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_visible')->default(true);

            // Se false, il prodotto può essere venduto anche senza giacenza
            $table->boolean('track_stock')->default(true);

            // Giacenza base per prodotti semplici
            $table->integer('stock')->default(0);

            // Quantità temporaneamente impegnata nei carrelli/ordini
            $table->integer('reserved_stock')->default(0);

            // True se il prodotto ha varianti
            $table->boolean('has_variants')->default(false);

            // Se true ignora la stagionalità della categoria
            $table->boolean('always_available')->default(false);

            // Giorni di evasione extra quando il prodotto/categoria è fuori stagione
            $table->unsignedInteger('off_season_fulfillment_days')->nullable();

            // Soglia per evidenziare scarsa giacenza
            $table->unsignedInteger('low_stock_threshold')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
