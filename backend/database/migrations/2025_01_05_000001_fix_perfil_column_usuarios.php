<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verifica se a tabela usuarios existe
        if (Schema::hasTable('usuarios')) {
            // Altera a coluna perfil para VARCHAR(50) se for ENUM ou muito pequena
            // Isso permite todos os perfis incluindo BAR
            DB::statement("ALTER TABLE usuarios MODIFY COLUMN perfil VARCHAR(50) NOT NULL DEFAULT 'VISUALIZADOR'");
            
            // Atualiza qualquer valor NULL para VISUALIZADOR
            DB::table('usuarios')->whereNull('perfil')->update(['perfil' => 'VISUALIZADOR']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não fazemos rollback para não perder dados
        // Se necessário, pode ser feito manualmente
    }
};





