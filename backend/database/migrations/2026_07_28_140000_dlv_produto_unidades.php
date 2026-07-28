<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dlv_produto_unidades')) {
            Schema::create('dlv_produto_unidades', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('produto_id');
                $table->unsignedBigInteger('unidade_id');
                $table->timestamps();

                $table->unique(['produto_id', 'unidade_id']);
                $table->index('unidade_id');
                $table->index('produto_id');
            });
        }

        if (Schema::hasTable('dlv_produtos') && Schema::hasTable('dlv_produto_unidades')) {
            $existentes = DB::table('dlv_produto_unidades')->pluck('produto_id')->unique();
            DB::table('dlv_produtos')
                ->select('id', 'unidade_id')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($existentes) {
                    $now = now();
                    foreach ($rows as $row) {
                        if ($existentes->contains((int) $row->id)) {
                            continue;
                        }
                        if ((int) $row->unidade_id <= 0) {
                            continue;
                        }
                        DB::table('dlv_produto_unidades')->insert([
                            'produto_id' => (int) $row->id,
                            'unidade_id' => (int) $row->unidade_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dlv_produto_unidades');
    }
};
