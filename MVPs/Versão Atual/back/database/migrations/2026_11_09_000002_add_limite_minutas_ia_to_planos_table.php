<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * EDTAN-250: Adiciona limite de minutas geradas por IA por plano para controle de custos
     */
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->integer('limite_minutas_ia')->default(5)->after('limite_membros_equipe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn('limite_minutas_ia');
        });
    }
};

