<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencias', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->foreignId('plano_id')->nullable()->constrained('planos')->nullOnDelete();
            $table->foreignId('plano_upgrade_pendente_id')->nullable()->constrained('planos')->nullOnDelete();
            $table->string('upgrade_transacao_id')->nullable();
            $table->unsignedInteger('upgrade_dias_extras')->default(0);
            $table->string('upgrade_forma_pagamento', 20)->nullable();
            $table->foreignId('plano_agendado_id')->nullable()->constrained('planos')->nullOnDelete();
            $table->enum('forma_pagamento', ['PIX', 'CARD'])->default('PIX');
            $table->string('id_transacao')->nullable();
            $table->text('url_checkout')->nullable();
            $table->enum('status_pagamento', ['pendente', 'ativo', 'suspenso', 'cancelado'])->default('pendente');
            $table->timestamp('pagamento_expira_em')->nullable();
            $table->timestamp('assinatura_expira_em')->nullable();
            $table->timestamp('alerta_vencimento_proximo_enviado_em')->nullable();
            $table->timestamp('alerta_falha_pagamento_enviado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agencias');
    }
};