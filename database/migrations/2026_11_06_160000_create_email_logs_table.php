<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mailable', 190)->nullable();
            $table->string('message_id', 190)->nullable()->index();
            $table->string('from_address', 190)->nullable();
            $table->text('to_addresses');
            $table->text('cc_addresses')->nullable();
            $table->text('bcc_addresses')->nullable();
            $table->string('subject', 250)->nullable();
            $table->string('status', 20)->default('sending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->string('raw_path', 190)->nullable();
            $table->timestamp('stored_in_sent_at')->nullable();
            $table->unsignedSmallInteger('store_attempts')->default(0);
            $table->text('store_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
