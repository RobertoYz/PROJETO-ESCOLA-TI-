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
        Schema::table('editals', function (Blueprint $table) {
            $table->string('ai_faturamento')->nullable()->after('ai_trl');
            $table->string('ai_nicho')->nullable()->after('ai_faturamento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('editals', function (Blueprint $table) {
            $table->dropColumn(['ai_faturamento', 'ai_nicho']);
        });
    }
};
