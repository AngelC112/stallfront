<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('back_in_stock_subscriptions', function (Blueprint $table) {
            // Language of the visitor who asked for the notice.
            $table->string('locale', 8)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('back_in_stock_subscriptions', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
