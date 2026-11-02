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
        Schema::create('minutas', function (Blueprint $table) {
            $table->id();
            
            // Relacionamentos e Auditoria (RF-34)
            $table->foreignId('startup_id')->constrained('startups')->onDelete('cascade');
            $table->foreignId('edital_id')->constrained('editals')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('kanban_card_id')->nullable()->constrained('kanban_cards')->onDelete('set null');
            
            // Versionamento de Histórico (RF-33)
            $table->unsignedInteger('versao')->default(1);
            $table->foreignId('parent_minuta_id')->nullable()->constrained('minutas')->onDelete('set null');

            // Conteúdo e Metadados do Projeto
            $table->string('titulo_projeto')->nullable();
            $table->text('ideia_projeto')->nullable(); // Input original do consultor
            $table->longText('conteudo_gerado'); // Texto completo / estruturado da minuta
            $table->json('secoes_estruturadas')->nullable(); // JSON com cada seção isolada para exibição/edição rica
            
            // Auditoria e Status
            $table->string('status')->default('concluido'); // gerando, concluido, revisado, erro
            $table->string('modelo_ia_usado')->nullable(); // Ex: Gemini, DeepSeek, Groq, Fallback
            $table->string('autor_nome')->nullable(); // Cache do nome do autor para auditoria rápida
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minutas');
    }
};
