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
            $table->boolean('ai_analyzed')->default(false)->after('status');
            $table->integer('ai_match')->nullable()->after('ai_analyzed');
            $table->string('ai_trl')->nullable()->after('ai_match');
            $table->json('ai_diagnosis')->nullable()->after('ai_trl');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('editals', function (Blueprint $table) {
            $table->dropColumn(['ai_analyzed', 'ai_match', 'ai_trl', 'ai_diagnosis']);
        });
    }
};
