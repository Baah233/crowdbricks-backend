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
        // Investments table indexes
        Schema::table('investments', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'investments_user_status_idx');
            $table->index(['project_id', 'status'], 'investments_project_status_idx');
            $table->index('created_at', 'investments_created_at_idx');
        });

        // Projects table indexes
        Schema::table('projects', function (Blueprint $table) {
            $table->index('funding_status', 'projects_funding_status_idx');
            $table->index(['funding_status', 'created_at'], 'projects_status_created_idx');
        });

        // Wallet transactions table indexes
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->index(['user_id', 'type'], 'wallet_transactions_user_type_idx');
            $table->index(['user_id', 'created_at'], 'wallet_transactions_user_date_idx');
        });

        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index('email', 'users_email_idx');
            $table->index('role', 'users_role_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropIndex('investments_user_status_idx');
            $table->dropIndex('investments_project_status_idx');
            $table->dropIndex('investments_created_at_idx');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_funding_status_idx');
            $table->dropIndex('projects_status_created_idx');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex('wallet_transactions_user_type_idx');
            $table->dropIndex('wallet_transactions_user_date_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_email_idx');
            $table->dropIndex('users_role_idx');
        });
    }
};
