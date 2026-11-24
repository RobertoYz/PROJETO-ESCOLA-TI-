<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('termos_aceites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('agencia_id')->nullable()->constrained('agencias')->nullOnDelete();
            $table->string('titular_email_hmac', 64)->index();
            $table->string('hmac_chave_id', 32);
            $table->foreignId('documento_termos_id')->constrained('documentos_legais')->restrictOnDelete();
            $table->foreignId('documento_privacidade_id')->constrained('documentos_legais')->restrictOnDelete();
            $table->string('versao_termos', 20);
            $table->string('versao_privacidade', 20);
            $table->string('hash_termos', 64);
            $table->string('hash_privacidade', 64);
            $table->timestamp('aceito_em');
            $table->timestamp('retencao_ate')->nullable()->index();
            $table->boolean('preservacao_excepcional')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('termos_aceites');
    }
};
