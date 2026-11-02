<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agencia_id')->constrained('agencias')->onDelete('cascade');
            
            // Dados da Receita Federal
            $table->string('cnpj', 18);
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->date('data_abertura')->nullable();
            
            // Contato
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            
            // Endereço detalhado
            $table->string('cep')->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            
            // Dados para o Match Inteligente
            $table->string('porte')->nullable(); // Ex: ME, EPP, Grande Porte
            $table->string('regime_tributario')->nullable(); // Ex: Simples Nacional
            $table->string('nicho_atuacao')->nullable(); // Ex: Agtech, Healthtech
            
            // Pitch da Startup
            $table->text('pitch')->nullable(); 

            $table->timestamps();

            // Uma agencia não pode cadastrar 2 vezes a mesma startup
            $table->unique(['agencia_id', 'cnpj']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startups');
    }
};
