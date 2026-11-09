<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->after('id');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');

            $table->string('phone', 32)->nullable()->unique()->after('password');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');

            $table->string('address')->nullable()->after('phone_verified_at');
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            $table->timestamp('privacy_accepted_at')->nullable()->after('longitude');
            $table->timestamp('terms_accepted_at')->nullable()->after('privacy_accepted_at');

            $table->boolean('delivery_enabled')->default(false)->after('terms_accepted_at');
            $table->unsignedInteger('no_show_count')->default(0)->after('delivery_enabled');
            $table->timestamp('blocked_at')->nullable()->after('no_show_count');
            $table->timestamp('last_login_at')->nullable()->after('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'first_name',
                'last_name',
                'phone',
                'phone_verified_at',
                'address',
                'latitude',
                'longitude',
                'privacy_accepted_at',
                'terms_accepted_at',
                'delivery_enabled',
                'no_show_count',
                'blocked_at',
                'last_login_at',
            ]);
        });
    }
};
