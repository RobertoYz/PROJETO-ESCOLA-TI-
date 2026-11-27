<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('startup_portal_acessos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('startup_id')->unique()->constrained('startups')->cascadeOnDelete();
            $table->foreignId('agencia_id')->constrained('agencias')->cascadeOnDelete();
            $table->string('login', 60)->unique();
            $table->string('password');
            $table->boolean('ativo')->default(true);
            $table->timestamp('ultimo_acesso_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startup_portal_acessos');
    }
};
