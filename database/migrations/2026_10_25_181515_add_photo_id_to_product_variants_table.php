<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Esegue la migrazione.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Aggiunge la colonna photo_id dopo 'is_active'
            $table->foreignId('photo_id')
                ->nullable()
                ->after('is_active')
                ->constrained('product_photos')
                ->nullOnDelete();
        });
    }

    /**
     * Reverte la migrazione.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Rimuove prima il vincolo FOREIGN KEY, poi la colonna
            $table->dropForeign(['photo_id']);
            $table->dropColumn('photo_id');
        });
    }
};
