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
        Schema::create('two_factor_auth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('enabled')->default(false);
            $table->string('method')->default('app'); // app, sms, email
            $table->string('secret')->nullable(); // Encrypted TOTP secret
            $table->text('recovery_codes')->nullable(); // JSON array of backup codes
            $table->string('phone_number')->nullable(); // For SMS 2FA
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->integer('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
            
            $table->unique('user_id');
            $table->index(['user_id', 'enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_auth');
    }
};
