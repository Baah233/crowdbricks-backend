<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // investor
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 10)->default('GHS');
            $table->string('payment_method')->nullable();
            $table->string('status')->default('pending'); // pending, received, confirmed, approved, failed
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};