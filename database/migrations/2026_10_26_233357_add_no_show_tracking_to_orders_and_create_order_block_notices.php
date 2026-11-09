<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('no_show_counted_at')->nullable()->after('placed_at');
        });

        Schema::create('order_block_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('previous_status', 30);
            $table->string('reason');
            $table->timestamp('seen_at')->nullable();
            $table->foreignId('seen_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_block_notices');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('no_show_counted_at');
        });
    }
};
