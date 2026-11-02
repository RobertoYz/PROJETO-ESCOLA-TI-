<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
    Schema::create('planos', function (Blueprint $table) {
        $table->id();
        $table->string('nome'); // Tipos de plano
        $table->decimal('preco', 8, 2); 
        $table->string('id_plataforma_pagamento')->nullable(); // ID q é retornado pelo AbacatePay
        $table->integer('limite_startups')->default(1);
        $table->integer('limite_membros_equipe')->default(1);
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planos');
    }
};
