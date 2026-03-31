<?php

/**
 * Garante cadastro de funcionario para usuarios operacionais ativos
 * que ainda nao possuem vinculo em funcionarios.usuario_id.
 *
 * Importante: usuario e funcionario sao entidades distintas.
 * Este script NAO cria vinculo de acesso automaticamente.
 *
 * Uso:
 *   php reconcile-funcionarios-usuarios.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Reconciliacao usuarios -> funcionarios ===\n";

if (!Schema::hasTable('usuarios') || !Schema::hasTable('funcionarios')) {
    echo "ERRO: tabelas obrigatorias nao encontradas.\n";
    exit(1);
}

$perfisOperacionais = ['ATENDENTE', 'ATENDENTE_CAIXA', 'COZINHA', 'ESTOQUISTA'];

$usuariosSemFuncionario = DB::table('usuarios as u')
    ->leftJoin('funcionarios as f', 'f.usuario_id', '=', 'u.id')
    ->where('u.ativo', 1)
    ->whereIn(DB::raw('UPPER(u.perfil)'), $perfisOperacionais)
    ->whereNull('f.id')
    ->select('u.id', 'u.nome', 'u.email', 'u.perfil', 'u.unidade_id')
    ->orderBy('u.id')
    ->get();

if ($usuariosSemFuncionario->isEmpty()) {
    echo "Nada para criar. Todos os usuarios operacionais ativos ja possuem funcionario.\n";
    exit(0);
}

$criados = 0;

foreach ($usuariosSemFuncionario as $u) {
    $cpf = sprintf('999.999.%03d-%02d', $u->id % 1000, $u->id % 100);
    $tentativas = 0;

    while (DB::table('funcionarios')->where('cpf', $cpf)->exists()) {
        $tentativas++;
        if ($tentativas > 1000) {
            echo "Falha ao gerar CPF provisório unico para usuario {$u->id}. Pulando.\n";
            continue 2;
        }
        $cpf = sprintf('998.998.%03d-%02d', ($u->id + $tentativas) % 1000, ($u->id + $tentativas) % 100);
    }

    DB::table('funcionarios')->insert([
        'nome_completo' => $u->nome,
        'cpf' => $cpf,
        'cargo' => strtolower(str_replace('_', ' ', (string) $u->perfil)),
        'unidade_id' => $u->unidade_id,
        'email' => $u->email,
        'status' => 'ativo',
        'possui_acesso' => 0,
        'usuario_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $criados++;
    echo "Criado funcionario para usuario {$u->id} - {$u->nome}\n";
}

echo "Concluido. Funcionarios criados: {$criados}.\n";

