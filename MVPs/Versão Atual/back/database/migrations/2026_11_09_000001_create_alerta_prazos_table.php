<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RF-47 / EDTAN-171: Módulo de Alertas de Prazos Críticos de Editais
     */
    public function up(): void
    {
        Schema::create('alerta_prazos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('startup_id')->nullable()->constrained('startups')->nullOnDelete();
            $table->foreignId('edital_id')->constrained('editals')->cascadeOnDelete();
            $table->foreignId('kanban_card_id')->nullable()->constrained('kanban_cards')->nullOnDelete();
            $table->string('tipo_prazo')->default('submissao'); // submissao, encerramento, fase
            $table->dateTime('data_limite');
            $table->integer('dias_restantes');
            $table->string('nivel_urgencia'); // critico (<=3d), urgente (<=7d), atencao (<=15d)
            $table->string('titulo');
            $table->text('mensagem');
            $table->boolean('lido')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'edital_id', 'nivel_urgencia'], 'user_edital_urgencia_unique');
            $table->index(['user_id', 'lido']);
            $table->index(['startup_id', 'lido']);
            $table->index(['nivel_urgencia']);
            $table->index(['data_limite']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerta_prazos');
    }
};
