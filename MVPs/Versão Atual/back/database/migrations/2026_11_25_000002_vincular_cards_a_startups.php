<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('kanban_cards', function (Blueprint $table) {
            $table->foreignId('startup_id')->nullable()->constrained('startups')->nullOnDelete();
        });

        DB::table('kanban_cards as c')
            ->join('kanban_boards as b', 'b.id', '=', 'c.board_id')
            ->whereNotNull('b.agencia_id')->whereNotNull('c.startup_name')
            ->select('c.id', 'c.startup_name', 'b.agencia_id')
            ->chunkById(200, function ($cards) {
                foreach ($cards as $card) {
                    $ids = DB::table('startups')->where('agencia_id', $card->agencia_id)
                        ->where(function ($query) use ($card) {
                            $query->where('nome_fantasia', $card->startup_name)
                                ->orWhere('razao_social', $card->startup_name);
                        })->limit(2)->pluck('id');
                    if ($ids->count() === 1) {
                        DB::table('kanban_cards')->where('id', $card->id)->update(['startup_id' => $ids->first()]);
                    }
                }
            }, 'c.id', 'id');
    }

    public function down(): void
    {
        Schema::table('kanban_cards', fn (Blueprint $table) => $table->dropConstrainedForeignId('startup_id'));
    }
};
