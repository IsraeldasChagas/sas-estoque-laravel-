<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boleto_anexos')) {
            Schema::create('boleto_anexos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('boleto_id');
                $table->string('tipo', 20)->default('boleto');
                $table->string('path');
                $table->string('nome');
                $table->string('tipo_arquivo', 20)->nullable();
                $table->timestamps();

                $table->index(['boleto_id', 'tipo']);
            });
        }

        if (Schema::hasTable('boletos') && Schema::hasTable('boleto_anexos')) {
            $rows = DB::table('boletos')
                ->whereNotNull('anexo_path')
                ->where('anexo_path', '!=', '')
                ->get(['id', 'anexo_path', 'anexo_nome', 'anexo_tipo', 'created_at', 'updated_at']);

            foreach ($rows as $row) {
                $exists = DB::table('boleto_anexos')
                    ->where('boleto_id', $row->id)
                    ->where('path', $row->anexo_path)
                    ->exists();
                if ($exists) {
                    continue;
                }

                DB::table('boleto_anexos')->insert([
                    'boleto_id' => $row->id,
                    'tipo' => 'boleto',
                    'path' => $row->anexo_path,
                    'nome' => $row->anexo_nome ?: basename($row->anexo_path),
                    'tipo_arquivo' => $row->anexo_tipo,
                    'created_at' => $row->created_at ?: now(),
                    'updated_at' => $row->updated_at ?: now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('boleto_anexos');
    }
};
