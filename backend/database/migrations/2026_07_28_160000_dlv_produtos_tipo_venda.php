<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dlv_produtos') && ! Schema::hasColumn('dlv_produtos', 'tipo_venda')) {
            Schema::table('dlv_produtos', function (Blueprint $table) {
                $table->string('tipo_venda', 20)->default('revenda')->after('estoque_produto_id');
            });
        }

        if (Schema::hasTable('dlv_produtos') && Schema::hasTable('fichas_tecnicas') && Schema::hasColumn('fichas_tecnicas', 'produto_final_id')) {
            DB::table('dlv_produtos')
                ->whereNotNull('estoque_produto_id')
                ->orderBy('id')
                ->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        $pid = (int) $row->estoque_produto_id;
                        if ($pid <= 0) {
                            continue;
                        }
                        $temFicha = DB::table('fichas_tecnicas')->where('produto_final_id', $pid)->exists();
                        if ($temFicha) {
                            DB::table('dlv_produtos')->where('id', $row->id)->update(['tipo_venda' => 'prato']);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dlv_produtos') && Schema::hasColumn('dlv_produtos', 'tipo_venda')) {
            Schema::table('dlv_produtos', function (Blueprint $table) {
                $table->dropColumn('tipo_venda');
            });
        }
    }
};
