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
        Schema::create('developer_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('wallet_id')->unique(); // Virtual wallet ID (UUID)
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('pending_balance', 15, 2)->default(0); // In escrow
            $table->decimal('lifetime_earnings', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->string('transaction_pin_hash')->nullable(); // For withdrawal verification
            $table->boolean('auto_withdraw')->default(false);
            $table->string('withdrawal_account')->nullable(); // Bank account or mobile money
            $table->string('withdrawal_provider')->nullable(); // MTN, Vodafone, Bank name
            $table->integer('failed_withdrawal_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique('user_id');
            $table->index(['wallet_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('developer_wallets');
    }
};
