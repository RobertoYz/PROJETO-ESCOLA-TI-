<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RF-26 (EDTAN-172): Tabela de Alertas de Match Inteligente de Editais Compatíveis
     */
    public function up(): void
    {
        Schema::create('alerta_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('startup_id')->constrained('startups')->cascadeOnDelete();
            $table->foreignId('edital_id')->constrained('editals')->cascadeOnDelete();
            $table->integer('score_match')->default(0);
            $table->string('titulo');
            $table->text('mensagem');
            $table->json('criterios')->nullable();
            $table->boolean('lido')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'startup_id', 'edital_id'], 'user_startup_edital_alerta_unique');
            $table->index(['user_id', 'lido']);
            $table->index(['startup_id', 'lido']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerta_matches');
    }
};
