<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agencia_id')->nullable()->constrained('agencias')->onDelete('cascade');
            $table->enum('perfil_acesso', ['admin_global', 'consultor_admin', 'consultor_padrao', 'startup'])->default('consultor_admin');
            $table->json('permissoes')->nullable();
            $table->enum('status', ['pendente', 'ativo', 'inativo'])->default('ativo');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('convite_token', 64)->nullable()->unique();
            $table->string('convite_codigo_hash', 255)->nullable();
            $table->timestamp('convite_expira_em')->nullable();
            $table->unsignedTinyInteger('convite_tentativas')->default(0);
            $table->unsignedBigInteger('convidado_por_id')->nullable()->index();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
