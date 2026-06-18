<?php

/**
 * Remove cadastros provisórios de funcionários (RECUPERAR / CPF 000.000 / 999.999).
 * Não remove cadastros reais do RH.
 *
 * Uso:
 *   php limpar-funcionarios-provisorios.php
 *
 * Preferível: POST /funcionarios/limpar-provisorios (ADMIN) — grava auditoria.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\Rh\RhFuncionarioUnicidade;

echo "=== Limpeza de funcionarios provisorios ===\n";

$resultado = RhFuncionarioUnicidade::limparCadastrosProvisorios();

if ($resultado['removidos'] === 0) {
    echo "Nenhum cadastro provisorio encontrado.\n";
    exit(0);
}

echo "Removidos: {$resultado['removidos']}\n";
echo "IDs: " . implode(', ', $resultado['ids']) . "\n";
echo "Proventos antigos que referenciam esses IDs permanecem no historico (sem cadastro fantasma no RH).\n";
