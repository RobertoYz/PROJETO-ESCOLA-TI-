<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
    Schema::create('agencias', function (Blueprint $table) {
        $table->id();
        $table->string('nome');
        $table->foreignId('plano_id')->constrained('planos'); // Relacionamento com a tabela planos (constrained para limitar a somente planos existentes)
        $table->string('id_plataforma_pagamento')->nullable();
        $table->enum('status_pagamento', ['pendente', 'ativo', 'suspenso', 'cancelado'])->default('pendente');
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agencias');
    }
};
