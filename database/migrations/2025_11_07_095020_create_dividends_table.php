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
        Schema::create('dividends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('investment_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->decimal('investment_amount', 15, 2); // Original investment amount
            $table->decimal('percentage', 5, 2); // Dividend percentage
            $table->enum('type', ['quarterly', 'annual', 'project_completion', 'special'])->default('quarterly');
            $table->enum('status', ['pending', 'processing', 'paid', 'failed'])->default('pending');
            $table->string('payment_method')->nullable(); // bank, momo, reinvest
            $table->string('payment_reference')->nullable();
            $table->date('declaration_date'); // When dividend was declared
            $table->date('payment_date')->nullable(); // When dividend was/will be paid
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['project_id', 'declaration_date']);
            $table->index(['payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dividends');
    }
};
