<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'agencia_id')) {
                    $table->unsignedBigInteger('agencia_id')->nullable()->after('id')->index();
                }

                if (!Schema::hasColumn('users', 'perfil_acesso')) {
                    $table->enum('perfil_acesso', ['admin_global', 'consultor_admin', 'consultor_padrao', 'startup'])
                        ->default('consultor_admin')
                        ->after('agencia_id');
                }

                if (!Schema::hasColumn('users', 'permissoes')) {
                    $table->json('permissoes')->nullable()->after('perfil_acesso');
                }

                if (!Schema::hasColumn('users', 'status')) {
                    $table->enum('status', ['pendente', 'ativo', 'inativo'])
                        ->default('ativo')
                        ->after('permissoes');
                }

                if (!Schema::hasColumn('users', 'convite_token')) {
                    $table->string('convite_token', 64)->nullable()->unique()->after('password');
                }

                if (!Schema::hasColumn('users', 'convite_codigo_criptografado')) {
                    $table->text('convite_codigo_criptografado')->nullable()->after('convite_token');
                }

                if (!Schema::hasColumn('users', 'convite_expira_em')) {
                    $table->timestamp('convite_expira_em')->nullable()->after('convite_codigo_criptografado');
                }

                if (!Schema::hasColumn('users', 'convite_tentativas')) {
                    $table->unsignedTinyInteger('convite_tentativas')->default(0)->after('convite_expira_em');
                }

                if (!Schema::hasColumn('users', 'convidado_por_id')) {
                    $table->unsignedBigInteger('convidado_por_id')->nullable()->index()->after('convite_tentativas');
                }
            });
        }

        if (Schema::hasTable('agencias')) {
            Schema::table('agencias', function (Blueprint $table) {
                if (!Schema::hasColumn('agencias', 'owner_id')) {
                    $table->unsignedBigInteger('owner_id')->nullable()->index()->after('nome');
                }

                if (!Schema::hasColumn('agencias', 'forma_pagamento')) {
                    $table->enum('forma_pagamento', ['PIX', 'CARD'])->default('PIX')->after('plano_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $colunasParaRemover = [];
                foreach ([
                    'convidado_por_id',
                    'convite_tentativas',
                    'convite_expira_em',
                    'convite_codigo_criptografado',
                    'convite_token',
                    'permissoes',
                    'perfil_acesso',
                ] as $coluna) {
                    if (Schema::hasColumn('users', $coluna)) {
                        $colunasParaRemover[] = $coluna;
                    }
                }

                if (!empty($colunasParaRemover)) {
                    $table->dropColumn($colunasParaRemover);
                }
            });
        }
    }
};
