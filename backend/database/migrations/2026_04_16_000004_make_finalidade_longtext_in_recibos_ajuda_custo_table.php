<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recibos_ajuda_custo')) return;
        if (!Schema::hasColumn('recibos_ajuda_custo', 'finalidade')) return;

        $driver = Schema::getConnection()->getDriverName();

        // A coluna era string(80) com index. Para suportar múltiplas finalidades (JSON),
        // movemos para LONGTEXT/TEXT e removemos o índice.
        try {
            if ($driver === 'mysql') {
                // índice criado pela migration inicial
                try {
                    DB::statement('ALTER TABLE recibos_ajuda_custo DROP INDEX recibos_ajuda_custo_finalidade_index');
                } catch (\Throwable $e) {
                    // ignora se já foi removido / nome diferente
                }
                DB::statement('ALTER TABLE recibos_ajuda_custo MODIFY finalidade LONGTEXT NOT NULL');
            } elseif ($driver === 'pgsql') {
                // Postgres: índice pode existir, mas alterar para TEXT é suficiente
                DB::statement('ALTER TABLE recibos_ajuda_custo ALTER COLUMN finalidade TYPE TEXT');
            } else {
                // sqlite / outros: alteração automática pode não ser suportada sem rebuild da tabela
                // Mantém como está; a API ainda aceita múltiplas finalidades em formato string legado.
            }
        } catch (\Throwable $e) {
            // Não falha a migração por divergências de engine/DDL.
        }
    }

    public function down(): void
    {
        // Não reverte automaticamente (pode haver dados JSON maiores que 80 chars).
    }
};

