<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // developer
            $table->string('title');
            $table->string('slug')->nullable()->unique();
            $table->string('short_description', 512)->nullable();
            $table->longText('description')->nullable();
            $table->unsignedBigInteger('minimum_investment')->default(0);
            $table->unsignedBigInteger('target_funding')->default(0);
            $table->unsignedInteger('expected_yield')->nullable();
            $table->string('timeline')->nullable();
            $table->string('location')->nullable();
            $table->json('categories')->nullable();
            $table->json('tags')->nullable();
            $table->string('approval_status')->default('draft'); // draft, pending, approved, rejected
            $table->string('funding_status')->default('funding'); // funding, funded, closed
            $table->unsignedBigInteger('current_funding')->default(0);
            $table->unsignedInteger('investors')->default(0);
            $table->timestamps();

            $table->index('approval_status');
            $table->index('funding_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};