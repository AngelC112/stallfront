<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Texts written by the seller in each language: notification messages and legal pages.
        Schema::create('localized_texts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100);
            $table->string('locale', 8);
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->timestamps();

            $table->unique(['key', 'locale']);
        });

        // Messages customised before languages existed were written in Italian.
        $rows = DB::table('notification_event_settings')
            ->where(fn ($query) => $query->whereNotNull('subject')->orWhereNotNull('body'))
            ->get();

        foreach ($rows as $row) {
            DB::table('localized_texts')->insert([
                'key' => 'notification.'.$row->event,
                'locale' => 'it',
                'title' => $row->subject,
                'body' => $row->body,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('notification_event_settings')->update(['subject' => null, 'body' => null]);
    }

    public function down(): void
    {
        $rows = DB::table('localized_texts')->where('key', 'like', 'notification.%')->where('locale', 'it')->get();

        foreach ($rows as $row) {
            DB::table('notification_event_settings')
                ->where('event', substr($row->key, strlen('notification.')))
                ->update(['subject' => $row->title, 'body' => $row->body]);
        }

        Schema::dropIfExists('localized_texts');
    }
};
