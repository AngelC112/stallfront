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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('market_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('market_name')->nullable()->after('pickup_date');
            $table->string('market_location')->nullable()->after('market_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['market_id']);
            $table->dropColumn(['market_id', 'market_name', 'market_location']);
        });
    }
};
