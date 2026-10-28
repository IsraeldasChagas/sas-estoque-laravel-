<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ELETRONICO_SUBS = ['TV', 'Celular', 'Tablet', 'Informática', 'Outros'];

    public function up(): void
    {
        if (Schema::hasTable('patrimonio_categorias') && ! Schema::hasColumn('patrimonio_categorias', 'subcategorias')) {
            Schema::table('patrimonio_categorias', function (Blueprint $table) {
                $table->text('subcategorias')->nullable();
            });
        }
        if (Schema::hasTable('patrimonios') && ! Schema::hasColumn('patrimonios', 'subcategoria')) {
            Schema::table('patrimonios', function (Blueprint $table) {
                $table->string('subcategoria', 120)->nullable();
            });
        }
        if (! Schema::hasTable('patrimonio_categorias')) {
            return;
        }

        $subs = json_encode(self::ELETRONICO_SUBS, JSON_UNESCAPED_UNICODE);
        $existente = DB::table('patrimonio_categorias')
            ->where('slug', 'like', 'eletronico%')
            ->orWhereRaw('LOWER(TRIM(nome)) IN (?, ?)', ['eletrônico', 'eletronico'])
            ->first();

        if ($existente) {
            if (trim((string) ($existente->subcategorias ?? '')) === '') {
                DB::table('patrimonio_categorias')->where('id', $existente->id)->update([
                    'subcategorias' => $subs,
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        DB::table('patrimonio_categorias')->insert([
            'nome' => 'Eletrônico',
            'slug' => 'eletronico',
            'icone' => '📺',
            'ordem' => 10,
            'ativo' => true,
            'tipo_campos' => 'geral',
            'subcategorias' => $subs,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('patrimonios') && Schema::hasColumn('patrimonios', 'subcategoria')) {
            Schema::table('patrimonios', function (Blueprint $table) {
                $table->dropColumn('subcategoria');
            });
        }
        if (Schema::hasTable('patrimonio_categorias') && Schema::hasColumn('patrimonio_categorias', 'subcategorias')) {
            Schema::table('patrimonio_categorias', function (Blueprint $table) {
                $table->dropColumn('subcategorias');
            });
        }
    }
};
