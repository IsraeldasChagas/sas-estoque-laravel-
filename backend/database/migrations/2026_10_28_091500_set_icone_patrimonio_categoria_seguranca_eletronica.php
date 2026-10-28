<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patrimonio_categorias')) {
            return;
        }

        DB::table('patrimonio_categorias')
            ->where(function ($q) {
                $q->where('slug', 'like', 'seguranca-eletronica%')
                    ->orWhereRaw('LOWER(TRIM(nome)) IN (?, ?)', ['segurança eletrônica', 'seguranca eletronica']);
            })
            ->update([
                'icone' => '📹',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
    }
};
