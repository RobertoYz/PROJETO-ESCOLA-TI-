<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencias', function (Blueprint $table) {
            if (!Schema::hasColumn('agencias', 'bloqueio_administrativo')) {
                $table->boolean('bloqueio_administrativo')->default(false)->after('status_pagamento');
            }
            if (!Schema::hasColumn('agencias', 'motivo_bloqueio')) {
                $table->string('motivo_bloqueio')->nullable()->after('bloqueio_administrativo');
            }
            if (!Schema::hasColumn('agencias', 'bloqueado_em')) {
                $table->timestamp('bloqueado_em')->nullable()->after('motivo_bloqueio');
            }
            if (!Schema::hasColumn('agencias', 'bloqueado_por_id')) {
                $table->unsignedBigInteger('bloqueado_por_id')->nullable()->after('bloqueado_em');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agencias', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('agencias', 'bloqueio_administrativo')) $cols[] = 'bloqueio_administrativo';
            if (Schema::hasColumn('agencias', 'motivo_bloqueio')) $cols[] = 'motivo_bloqueio';
            if (Schema::hasColumn('agencias', 'bloqueado_em')) $cols[] = 'bloqueado_em';
            if (Schema::hasColumn('agencias', 'bloqueado_por_id')) $cols[] = 'bloqueado_por_id';

            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
