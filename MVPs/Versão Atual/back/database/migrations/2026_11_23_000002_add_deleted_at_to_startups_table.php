<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('startups') && !Schema::hasColumn('startups', 'deleted_at')) {
            Schema::table('startups', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('status', ['pendente', 'ativo', 'inativo'])->default('ativo')->after('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('startups') && Schema::hasColumn('startups', 'deleted_at')) {
            Schema::table('startups', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
