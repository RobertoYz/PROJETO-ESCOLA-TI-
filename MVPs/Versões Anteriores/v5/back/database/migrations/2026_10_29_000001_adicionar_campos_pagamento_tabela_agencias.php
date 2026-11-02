<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa as migrações no banco de dados.
     */
    public function up(): void
    {
        Schema::table('agencias', function (Blueprint $tabela) {
            if (!Schema::hasColumn('agencias', 'checkout_url')) {
                $tabela->text('checkout_url')->nullable()->after('status_pagamento');
            }
            if (!Schema::hasColumn('agencias', 'url_checkout')) {
                $tabela->text('url_checkout')->nullable()->after('checkout_url');
            }
            if (!Schema::hasColumn('agencias', 'billing_id')) {
                $tabela->string('billing_id')->nullable()->after('url_checkout');
            }
            if (!Schema::hasColumn('agencias', 'id_cobranca')) {
                $tabela->string('id_cobranca')->nullable()->after('billing_id');
            }
            if (!Schema::hasColumn('agencias', 'pagamento_expira_em')) {
                $tabela->timestamp('pagamento_expira_em')->nullable()->after('id_cobranca');
            }
            if (!Schema::hasColumn('agencias', 'pagamento_ativo')) {
                $tabela->boolean('pagamento_ativo')->default(false)->after('pagamento_expira_em');
            }
            if (Schema::hasColumn('agencias', 'plano_id')) {
                $tabela->foreignId('plano_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverte as migrações do banco de dados.
     */
    public function down(): void
    {
        Schema::table('agencias', function (Blueprint $tabela) {
            $colunasParaRemover = [];

            if (Schema::hasColumn('agencias', 'checkout_url')) {
                $colunasParaRemover[] = 'checkout_url';
            }
            if (Schema::hasColumn('agencias', 'url_checkout')) {
                $colunasParaRemover[] = 'url_checkout';
            }
            if (Schema::hasColumn('agencias', 'billing_id')) {
                $colunasParaRemover[] = 'billing_id';
            }
            if (Schema::hasColumn('agencias', 'id_cobranca')) {
                $colunasParaRemover[] = 'id_cobranca';
            }
            if (Schema::hasColumn('agencias', 'pagamento_expira_em')) {
                $colunasParaRemover[] = 'pagamento_expira_em';
            }
            if (Schema::hasColumn('agencias', 'pagamento_ativo')) {
                $colunasParaRemover[] = 'pagamento_ativo';
            }

            if (!empty($colunasParaRemover)) {
                $tabela->dropColumn($colunasParaRemover);
            }
        });
    }
};
