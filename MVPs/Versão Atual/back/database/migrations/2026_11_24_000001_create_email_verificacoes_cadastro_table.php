<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verificacoes_cadastro', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('codigo_hash')->nullable();
            $table->timestamp('codigo_expira_em')->nullable();
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->timestamp('ultimo_envio_em')->nullable();
            $table->string('verification_token_hash', 64)->nullable()->index();
            $table->timestamp('token_expira_em')->nullable();
            $table->timestamp('verificado_em')->nullable();
            $table->timestamp('consumido_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verificacoes_cadastro');
    }
};
