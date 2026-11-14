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
        Schema::create('agencias', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->foreignId('plano_id')->nullable()->constrained('planos')->nullOnDelete();
            $table->string('id_transacao')->nullable();
            $table->text('url_checkout')->nullable();
            $table->enum('status_pagamento', ['pendente', 'ativo', 'suspenso', 'cancelado'])->default('pendente');
            $table->timestamp('pagamento_expira_em')->nullable();
            $table->timestamp('assinatura_expira_em')->nullable();
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
