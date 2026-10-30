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
        Schema::create('tributes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('memory_id')->constrained()->cascadeOnDelete();
    $table->text('story')->nullable();
    $table->text('poem')->nullable();
    $table->json('items')->nullable();
    $table->string('status')->default('pending'); // pending, working, done, failed
    $table->text('error')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tributes');
    }
};
