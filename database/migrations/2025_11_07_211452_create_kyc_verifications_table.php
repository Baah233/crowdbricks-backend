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
        Schema::create('kyc_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('document_type', ['national_id', 'passport', 'drivers_license', 'business_registration', 'land_title', 'tax_certificate'])->nullable();
            $table->string('document_number')->nullable();
            $table->string('document_front_path')->nullable(); // Encrypted storage path
            $table->string('document_back_path')->nullable();
            $table->string('selfie_path')->nullable(); // For liveness check
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected', 'expired'])->default('pending');
            $table->string('verification_method')->nullable(); // manual, smile_id, trulioo, etc.
            $table->string('third_party_reference')->nullable(); // External API reference ID
            $table->text('rejection_reason')->nullable();
            $table->json('verification_data')->nullable(); // Additional data from third-party API
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // KYC expiration (e.g., 2 years)
            $table->integer('trust_score')->default(0); // 0-100 calculated score
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['document_type', 'status']);
            $table->index('third_party_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kyc_verifications');
    }
};
