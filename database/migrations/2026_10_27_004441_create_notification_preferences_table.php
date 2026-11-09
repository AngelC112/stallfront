<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canale scelto dal cliente (email o browser) per gli eventi "a scelta".
     * Senza riga vale l'email.
     */
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 60);
            $table->string('channel', 20)->default('email');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
