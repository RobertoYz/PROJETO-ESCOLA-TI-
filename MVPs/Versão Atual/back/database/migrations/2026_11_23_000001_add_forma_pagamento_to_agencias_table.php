<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencias', function (Blueprint $table) {
            if (!Schema::hasColumn('agencias', 'owner_id')) {
                $table->unsignedBigInteger('owner_id')->nullable()->index()->after('nome');
            }
            if (!Schema::hasColumn('agencias', 'forma_pagamento')) {
                $table->enum('forma_pagamento', ['PIX', 'CARD'])->default('PIX')->after('plano_id');
            }
            if (!Schema::hasColumn('agencias', 'alerta_vencimento_proximo_enviado_em')) {
                $table->timestamp('alerta_vencimento_proximo_enviado_em')->nullable()->after('assinatura_expira_em');
            }
            if (!Schema::hasColumn('agencias', 'alerta_falha_pagamento_enviado_em')) {
                $table->timestamp('alerta_falha_pagamento_enviado_em')->nullable()->after('alerta_vencimento_proximo_enviado_em');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agencias', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('agencias', 'owner_id')) $cols[] = 'owner_id';
            if (Schema::hasColumn('agencias', 'forma_pagamento')) $cols[] = 'forma_pagamento';
            if (Schema::hasColumn('agencias', 'alerta_vencimento_proximo_enviado_em')) $cols[] = 'alerta_vencimento_proximo_enviado_em';
            if (Schema::hasColumn('agencias', 'alerta_falha_pagamento_enviado_em')) $cols[] = 'alerta_falha_pagamento_enviado_em';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
