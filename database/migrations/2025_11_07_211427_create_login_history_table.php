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
        Schema::create('login_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->string('device_type')->nullable(); // mobile, tablet, desktop
            $table->string('device_name')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable(); // iOS, Android, Windows, macOS
            $table->string('location')->nullable(); // City, Country from IP geolocation
            $table->string('session_id')->nullable();
            $table->enum('status', ['success', 'failed', 'blocked'])->default('success');
            $table->string('failure_reason')->nullable(); // wrong_password, 2fa_failed, etc.
            $table->boolean('is_suspicious')->default(false);
            $table->timestamp('logout_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['ip_address', 'status']);
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_history');
    }
};
