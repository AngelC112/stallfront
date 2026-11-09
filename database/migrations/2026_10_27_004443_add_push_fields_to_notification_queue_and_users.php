<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_queue', function (Blueprint $table) {
            $table->string('url', 500)->nullable()->after('body');
            $table->timestamp('awaiting_ack_until')->nullable()->after('sent_at');
            $table->string('delivered_via', 20)->nullable()->after('awaiting_ack_until');
        });

        Schema::table('users', function (Blueprint $table) {
            // Quando le notifiche del browser sono state disattivate in automatico (permesso revocato):
            // l'area personale mostra un avviso finché il cliente non lo chiude.
            $table->timestamp('push_revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notification_queue', function (Blueprint $table) {
            $table->dropColumn(['url', 'awaiting_ack_until', 'delivered_via']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('push_revoked_at');
        });
    }
};
