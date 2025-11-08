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
        Schema::create('login_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->string('device_type')->nullable(); // mobile, desktop, tablet
            $table->string('device_name')->nullable(); // Chrome on Windows, Safari on iPhone
            $table->string('browser')->nullable();
            $table->string('platform')->nullable(); // Windows, macOS, iOS, Android
            $table->string('location')->nullable(); // City, Country (from IP)
            $table->string('country_code', 2)->nullable();
            $table->enum('status', ['success', 'failed', 'blocked'])->default('success');
            $table->string('failure_reason')->nullable();
            $table->boolean('is_suspicious')->default(false);
            $table->timestamp('login_at');
            $table->timestamp('logout_at')->nullable();
            $table->timestamps();
            
            // Indexes for faster queries
            $table->index(['user_id', 'login_at']);
            $table->index(['ip_address']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_activities');
    }
};
