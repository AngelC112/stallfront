<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Anni per la stagionalità (facoltativi: se null, si intende ogni anno)
            $table->unsignedSmallInteger('season_start_year')->nullable()->after('always_available');
            $table->unsignedSmallInteger('season_end_year')->nullable()->after('season_start_day');

            // Comportamento fuori periodo
            // long: acquistabile con tempi più lunghi
            // visible: visibile ma non acquistabile
            // hidden: nascosta
            $table->string('off_season_behavior', 20)->default('long')->after('season_end_year');

            // Giorni extra di evasione quando fuori stagione
            $table->unsignedTinyInteger('off_season_days')->default(10)->after('off_season_behavior');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'season_start_year',
                'season_end_year',
                'off_season_behavior',
                'off_season_days',
            ]);
        });
    }
};
