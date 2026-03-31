<?php

/**
 * Restaura cadastros básicos de funcionários a partir de IDs
 * referenciados em proventos e proventos_logs.
 *
 * Uso:
 *   php restore-funcionarios-basico.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Restauracao basica de funcionarios ===\n";

if (!Schema::hasTable('funcionarios')) {
    echo "ERRO: tabela funcionarios nao existe.\n";
    exit(1);
}

$idsProventos = DB::table('proventos')
    ->whereNotNull('funcionario_id')
    ->pluck('funcionario_id')
    ->toArray();

$idsLogs = DB::table('proventos_logs')
    ->whereNotNull('funcionario_id')
    ->pluck('funcionario_id')
    ->toArray();

$ids = array_values(array_unique(array_map('intval', array_merge($idsProventos, $idsLogs))));
sort($ids);

if (empty($ids)) {
    echo "Nenhum ID de funcionario encontrado em proventos/proventos_logs.\n";
    exit(0);
}

echo "IDs encontrados para restauracao: " . implode(', ', $ids) . "\n";

$restaurados = 0;
$jaExistiam = 0;

foreach ($ids as $id) {
    $existe = DB::table('funcionarios')->where('id', $id)->exists();
    if ($existe) {
        $jaExistiam++;
        continue;
    }

    $cpf = sprintf('000.000.%03d-%02d', $id % 1000, $id % 100);

    DB::table('funcionarios')->insert([
        'id' => $id,
        'nome_completo' => "RECUPERAR FUNCIONARIO ID {$id}",
        'cpf' => $cpf,
        'cargo' => 'A DEFINIR',
        'status' => 'ativo',
        'possui_acesso' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $restaurados++;
    echo "Restaurado ID {$id} com dados provisarios.\n";
}

echo "Concluido. Restaurados: {$restaurados}. Ja existiam: {$jaExistiam}.\n";
echo "IMPORTANTE: atualize nome/cpf/cargo reais desses cadastros.\n";

