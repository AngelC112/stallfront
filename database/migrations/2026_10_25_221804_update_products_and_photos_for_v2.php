<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('visibility', 20)->default('visible')->after('is_visible');
            $table->string('season_behavior', 30)->default('follow_category')->after('always_available');
            $table->unsignedInteger('own_fulfillment_days')->nullable()->after('season_behavior');
        });

        DB::table('products')->update([
            'visibility' => DB::raw("CASE WHEN is_active = 0 THEN 'hidden' WHEN is_visible = 0 THEN 'hidden' ELSE 'visible' END"),
            'season_behavior' => DB::raw("CASE WHEN always_available = 1 THEN 'purchasable_longer' ELSE 'follow_category' END"),
            'own_fulfillment_days' => DB::raw('off_season_fulfillment_days'),
        ]);

        Schema::table('product_photos', function (Blueprint $table) {
            $table->string('thumb_path')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['visibility', 'season_behavior', 'own_fulfillment_days']);
        });

        Schema::table('product_photos', function (Blueprint $table) {
            $table->dropColumn('thumb_path');
        });
    }
};
