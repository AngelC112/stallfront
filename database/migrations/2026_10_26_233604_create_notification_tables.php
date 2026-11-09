<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_event_settings', function (Blueprint $table) {
            $table->string('event', 60)->primary();
            $table->boolean('is_active')->default(true);
            $table->string('importance', 20)->default('always_email');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_queue', function (Blueprint $table) {
            $table->id();
            $table->string('event', 60);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('recipient_email');
            $table->string('channel', 20)->default('email');
            $table->string('subject');
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
            $table->index(['event', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_queue');
        Schema::dropIfExists('notification_event_settings');
    }
};
