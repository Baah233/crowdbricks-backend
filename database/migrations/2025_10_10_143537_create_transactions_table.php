<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('GHS');
            $table->string('gateway')->nullable();
            $table->string('reference')->unique();
            $table->enum('status', ['pending','success','failed','cancelled','refunded'])->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['reference']);
            $table->index(['project_id']);
        });
    }
    public function down() {
        Schema::dropIfExists('transactions');
    }
};
