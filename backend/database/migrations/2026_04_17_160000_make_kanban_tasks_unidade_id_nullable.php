<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kanban_tasks')) {
            return;
        }
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE kanban_tasks MODIFY unidade_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('kanban_tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('unidade_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('kanban_tasks')) {
            return;
        }
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE kanban_tasks MODIFY unidade_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('kanban_tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('unidade_id')->nullable(false)->change();
            });
        }
    }
};
