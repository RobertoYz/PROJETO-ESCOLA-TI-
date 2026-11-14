<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RF-11: Criação da tabela de persistência de editais favoritados
     */
    public function up(): void
    {
        Schema::create('edital_favoritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('edital_id')->constrained('editals')->cascadeOnDelete();
            $table->foreignId('startup_id')->nullable()->constrained('startups')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'edital_id']);
            $table->index(['user_id', 'startup_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edital_favoritos');
    }
};
