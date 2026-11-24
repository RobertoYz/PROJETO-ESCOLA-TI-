<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documentos_legais', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 40);
            $table->string('versao', 20);
            $table->unique(['tipo', 'versao']);
            $table->string('titulo', 180);
            $table->longText('conteudo_json');
            $table->string('hash_sha256', 64);
            $table->string('vigente_tipo', 40)->nullable()->unique();
            $table->timestamp('publicado_em')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_legais');
    }
};
