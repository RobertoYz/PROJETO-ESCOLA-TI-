<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->unsignedInteger('limite_armazenamento_mb')->default(1024);
            $table->unsignedInteger('limite_arquivo_mb')->default(50);
        });
        foreach (['Standard' => 1024, 'Pro' => 5120, 'Enterprise' => 25600] as $nome => $mb) {
            DB::table('planos')->where('nome', $nome)->update(['limite_armazenamento_mb' => $mb, 'limite_arquivo_mb' => 50]);
        }
        Schema::create('kanban_exclusoes_pendentes', function (Blueprint $table) {
            $table->id();
            $table->string('disco');
            $table->string('caminho');
        });
        Schema::create('kanban_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('kanban_cards')->cascadeOnDelete();
            $table->text('nome_arquivo');
            $table->string('disco');
            $table->string('caminho');
            $table->unsignedBigInteger('tamanho_bytes')->default(0);
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_anexos');
        Schema::dropIfExists('kanban_exclusoes_pendentes');
        Schema::table('planos', fn (Blueprint $table) => $table->dropColumn(['limite_armazenamento_mb', 'limite_arquivo_mb']));
    }
};
