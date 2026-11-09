<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indirizzo scomposto, compilato dall'elenco dei suggerimenti di OpenStreetMap.
     * La colonna "address" resta come testo completo (e per gli indirizzi scritti in passato).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('street')->nullable()->after('address');
            $table->string('street_number', 20)->nullable()->after('street');
            $table->string('postal_code', 10)->nullable()->after('street_number');
            $table->string('city')->nullable()->after('postal_code');
            $table->string('province')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['street', 'street_number', 'postal_code', 'city', 'province']);
        });
    }
};
