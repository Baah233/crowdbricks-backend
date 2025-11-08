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
            // Add phone column if it doesn't exist
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            
            $table->boolean('phone_verified')->default(false)->after('phone');
            $table->string('phone_verification_code')->nullable()->after('phone_verified');
            $table->string('phone_change_request')->nullable()->after('phone_verification_code');
            $table->enum('phone_change_status', ['pending', 'approved', 'rejected'])->nullable()->after('phone_change_request');
            $table->string('profile_picture')->nullable()->after('two_factor_secret');
            $table->boolean('two_factor_required')->default(false)->after('two_factor_enabled');
            $table->integer('profile_completion')->default(0)->after('profile_picture');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone_verified', 
                'phone_verification_code', 
                'phone_change_request', 
                'phone_change_status',
                'profile_picture',
                'two_factor_required',
                'profile_completion'
            ]);
            // Note: we don't drop 'phone' as it might have existed before
        });
    }
};
