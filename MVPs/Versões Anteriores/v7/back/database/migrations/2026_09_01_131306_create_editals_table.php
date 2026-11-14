<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('editals', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->unique();
            $table->string('title');
            $table->text('source_url')->nullable();
            $table->text('original_file_path')->nullable();

            $table->decimal('min_budget', 15, 2)->nullable();
            $table->decimal('max_budget', 15, 2)->nullable();
            $table->dateTime('deadline')->nullable();
            $table->dateTime('open_date')->nullable();
            $table->dateTime('result_date')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->enum('status', ['active', 'closed'])->default('active');

            $table->boolean('ai_analyzed')->default(false);
            $table->integer('ai_match')->nullable();
            $table->string('ai_trl')->nullable();
            $table->string('ai_faturamento')->nullable();
            $table->string('ai_nicho')->nullable();
            $table->json('ai_diagnosis')->nullable();

            $table->text('objetivo')->nullable();
            $table->longText('conteudo_completo')->nullable();
            $table->string('condicao_financiamento')->nullable();
            $table->string('operacao')->nullable();
            $table->string('publico')->nullable();
            $table->string('fonte')->default('FINEP');

            $table->json('documentos')->nullable();
            $table->string('regiao')->nullable();
            $table->string('nicho_tema')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('editals');
    }
};
