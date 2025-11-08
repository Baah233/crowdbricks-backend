<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_notifications')->default(true)->after('verification_id');
            $table->boolean('sms_notifications')->default(false)->after('email_notifications');
            $table->boolean('two_factor_enabled')->default(false)->after('sms_notifications');
            $table->string('two_factor_secret')->nullable()->after('two_factor_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_notifications', 'sms_notifications', 'two_factor_enabled', 'two_factor_secret']);
        });
    }
};
