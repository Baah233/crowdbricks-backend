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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // Link project to user (developer)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Core project details
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description');
            $table->longText('full_description');
            $table->decimal('target_amount', 12, 2);
            $table->decimal('raised_amount', 12, 2)->default(0);
            $table->string('category');
            $table->string('image_path')->nullable(); // optional

            // New required fields
            $table->string('location');
            $table->string('type');
            $table->decimal('minimum_investment', 15, 2);
            $table->decimal('expected_yield', 5, 2);
            $table->string('timeline');
            $table->string('funding_status');

            // Developer details (required where applicable)
            $table->string('developer_name');
            $table->boolean('developer_verified')->default(false);
            $table->decimal('developer_rating', 3, 2)->default(0.00);
            $table->integer('developer_completed_projects')->default(0);

            // Status fields
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('draft');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
