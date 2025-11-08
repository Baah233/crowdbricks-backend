<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('actor'); // actor_type, actor_id
            $table->string('action');
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};