<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Capacidade flexível + composição de mesas.
 * Preserva coluna `capacidade` legada. Não remove colunas antigas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mesas')) {
            Schema::table('mesas', function (Blueprint $table) {
                if (! Schema::hasColumn('mesas', 'capacidade_base')) {
                    $table->unsignedInteger('capacidade_base')->nullable()->after('capacidade');
                }
                if (! Schema::hasColumn('mesas', 'permite_cadeiras_extras')) {
                    $table->boolean('permite_cadeiras_extras')->default(false)->after('capacidade_base');
                }
                if (! Schema::hasColumn('mesas', 'cadeiras_extras_max')) {
                    $table->unsignedInteger('cadeiras_extras_max')->default(0)->after('permite_cadeiras_extras');
                }
                if (! Schema::hasColumn('mesas', 'capacidade_maxima')) {
                    $table->unsignedInteger('capacidade_maxima')->nullable()->after('cadeiras_extras_max');
                }
                if (! Schema::hasColumn('mesas', 'grupo_composicao')) {
                    $table->string('grupo_composicao', 100)->nullable()->after('pode_separar');
                }
                if (! Schema::hasColumn('mesas', 'cadastro_emergencial')) {
                    $table->boolean('cadastro_emergencial')->default(false)->after('grupo_composicao');
                }
                if (! Schema::hasColumn('mesas', 'cadastrado_pela_ayla')) {
                    $table->boolean('cadastrado_pela_ayla')->default(false)->after('cadastro_emergencial');
                }
                if (! Schema::hasColumn('mesas', 'cadastrado_por_usuario_id')) {
                    $table->unsignedBigInteger('cadastrado_por_usuario_id')->nullable()->after('cadastrado_pela_ayla');
                }
                if (! Schema::hasColumn('mesas', 'motivo_cadastro')) {
                    $table->string('motivo_cadastro', 255)->nullable()->after('cadastrado_por_usuario_id');
                }
            });

            // Migração de dados: capacidade → capacidade_base / capacidade_maxima
            if (Schema::hasColumn('mesas', 'capacidade_base')) {
                DB::table('mesas')->whereNull('capacidade_base')->orderBy('id')->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $base = (int) ($row->capacidade ?? 1);
                        $extras = Schema::hasColumn('mesas', 'cadeiras_extras_max')
                            ? (int) ($row->cadeiras_extras_max ?? 0)
                            : 0;
                        DB::table('mesas')->where('id', $row->id)->update([
                            'capacidade_base' => $base,
                            'capacidade_maxima' => $base + $extras,
                        ]);
                    }
                });
            }

            if (Schema::hasColumn('mesas', 'grupo_composicao')) {
                try {
                    Schema::table('mesas', function (Blueprint $table) {
                        $table->index('grupo_composicao');
                    });
                } catch (\Throwable $e) {
                    // índice pode já existir
                }
            }
        }

        if (! Schema::hasTable('reserva_mesas')) {
            Schema::create('reserva_mesas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reserva_id');
                $table->unsignedBigInteger('mesa_id');
                $table->unsignedInteger('capacidade_utilizada')->default(0);
                $table->unsignedInteger('cadeiras_extras_utilizadas')->default(0);
                $table->boolean('principal')->default(false);
                $table->boolean('configuracao_emergencial')->default(false);
                $table->timestamps();

                $table->index('reserva_id');
                $table->index('mesa_id');
                $table->unique(['reserva_id', 'mesa_id']);
            });
        }

        // Compatibilidade: vincular reservas antigas (mesa principal = mesa_id).
        if (Schema::hasTable('reserva_mesas') && Schema::hasTable('reservas_mesas')) {
            $existentes = DB::table('reserva_mesas')->pluck('reserva_id')->all();
            DB::table('reservas_mesas')
                ->when($existentes !== [], fn ($q) => $q->whereNotIn('id', $existentes))
                ->whereNotNull('mesa_id')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    $now = now();
                    $insert = [];
                    foreach ($rows as $row) {
                        $insert[] = [
                            'reserva_id' => (int) $row->id,
                            'mesa_id' => (int) $row->mesa_id,
                            'capacidade_utilizada' => (int) ($row->qtd_pessoas ?? 0),
                            'cadeiras_extras_utilizadas' => 0,
                            'principal' => true,
                            'configuracao_emergencial' => false,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    if ($insert !== []) {
                        DB::table('reserva_mesas')->insert($insert);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_mesas');

        if (Schema::hasTable('mesas')) {
            Schema::table('mesas', function (Blueprint $table) {
                foreach ([
                    'motivo_cadastro',
                    'cadastrado_por_usuario_id',
                    'cadastrado_pela_ayla',
                    'cadastro_emergencial',
                    'grupo_composicao',
                    'capacidade_maxima',
                    'cadeiras_extras_max',
                    'permite_cadeiras_extras',
                    'capacidade_base',
                ] as $col) {
                    if (Schema::hasColumn('mesas', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
