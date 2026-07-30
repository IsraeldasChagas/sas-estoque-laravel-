<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContaAssinadaSupport
{
    public static function moduloAtivo(): bool
    {
        return Schema::hasTable('pdv_contas_assinadas');
    }

    public static function isFormaContaAssinada(string $forma): bool
    {
        $n = mb_strtolower(trim($forma));

        return in_array($n, ['conta assinada', 'conta_assinada', 'assinada', 'fiado'], true);
    }

    /** @return list<object> */
    public static function listar(?int $unidadeId = null, bool $somenteAtivas = true, ?string $busca = null): array
    {
        if (! self::moduloAtivo()) {
            return [];
        }

        $q = DB::table('pdv_contas_assinadas as c')
            ->leftJoin('funcionarios as f', 'f.id', '=', 'c.funcionario_id')
            ->select([
                'c.id',
                'c.unidade_id',
                'c.nome',
                'c.funcionario_id',
                'c.telefone',
                'c.observacao',
                'c.ativo',
                'c.created_at',
                'f.nome_completo as funcionario_nome',
                'f.cargo as funcionario_cargo',
            ])
            ->orderBy('c.nome');

        if ($somenteAtivas) {
            $q->where('c.ativo', 1);
        }
        if ($unidadeId) {
            $q->where(function ($w) use ($unidadeId) {
                $w->whereNull('c.unidade_id')->orWhere('c.unidade_id', $unidadeId);
            });
        }
        $busca = trim((string) $busca);
        if ($busca !== '') {
            $like = '%'.$busca.'%';
            $q->where(function ($w) use ($like) {
                $w->where('c.nome', 'like', $like)
                    ->orWhere('f.nome_completo', 'like', $like)
                    ->orWhere('c.telefone', 'like', $like);
            });
        }

        $rows = $q->limit(400)->get();
        $saldos = self::saldosPorConta($rows->pluck('id')->all());

        return $rows->map(function ($r) use ($saldos) {
            $r->saldo_aberto = round((float) ($saldos[(int) $r->id] ?? 0), 2);
            $r->origem = $r->funcionario_id ? 'funcionario' : 'avulsa';
            $r->rotulo = $r->funcionario_id
                ? trim(($r->funcionario_nome ?: $r->nome).($r->funcionario_cargo ? ' · '.$r->funcionario_cargo : ''))
                : (string) $r->nome;

            return $r;
        })->all();
    }

    /** @param list<int|string> $ids @return array<int, float> */
    public static function saldosPorConta(array $ids): array
    {
        if (! Schema::hasTable('pdv_conta_assinada_lancamentos') || ! count($ids)) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $rows = DB::table('pdv_conta_assinada_lancamentos')
            ->selectRaw("conta_id, SUM(CASE WHEN tipo = 'consumo' THEN valor ELSE -valor END) AS saldo")
            ->whereIn('conta_id', $ids)
            ->groupBy('conta_id')
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->conta_id] = round((float) $r->saldo, 2);
        }

        return $out;
    }

    public static function obter(int $id): ?object
    {
        if (! self::moduloAtivo()) {
            return null;
        }
        $lista = self::listar(null, false);
        foreach ($lista as $c) {
            if ((int) $c->id === $id) {
                return $c;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    public static function criar(array $data, int $usuarioId): object
    {
        if (! self::moduloAtivo()) {
            throw new \RuntimeException('Módulo de contas assinadas não migrado.');
        }

        $funcionarioId = ! empty($data['funcionario_id']) ? (int) $data['funcionario_id'] : null;
        $unidadeId = ! empty($data['unidade_id']) ? (int) $data['unidade_id'] : null;
        $nome = trim((string) ($data['nome'] ?? ''));

        if ($funcionarioId) {
            if (! Schema::hasTable('funcionarios')) {
                throw new \RuntimeException('Cadastro de funcionários indisponível.');
            }
            $func = DB::table('funcionarios')->where('id', $funcionarioId)->first();
            if (! $func) {
                throw new \RuntimeException('Funcionário não encontrado.');
            }
            $nome = trim((string) ($func->nome_completo ?? $nome));
            if ($nome === '') {
                throw new \RuntimeException('Funcionário sem nome.');
            }
            $dup = DB::table('pdv_contas_assinadas')
                ->where('funcionario_id', $funcionarioId)
                ->where('ativo', 1)
                ->when($unidadeId, fn ($q) => $q->where(function ($w) use ($unidadeId) {
                    $w->whereNull('unidade_id')->orWhere('unidade_id', $unidadeId);
                }))
                ->first();
            if ($dup) {
                throw new \RuntimeException('Já existe conta assinada ativa para este funcionário.');
            }
        } else {
            if ($nome === '') {
                throw new \RuntimeException('Informe o nome da conta assinada.');
            }
        }

        $id = DB::table('pdv_contas_assinadas')->insertGetId([
            'unidade_id' => $unidadeId ?: null,
            'nome' => mb_substr($nome, 0, 160),
            'funcionario_id' => $funcionarioId,
            'telefone' => isset($data['telefone']) && trim((string) $data['telefone']) !== ''
                ? mb_substr(trim((string) $data['telefone']), 0, 40)
                : null,
            'observacao' => isset($data['observacao']) && trim((string) $data['observacao']) !== ''
                ? mb_substr(trim((string) $data['observacao']), 0, 300)
                : null,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
            'criado_por' => $usuarioId > 0 ? $usuarioId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return self::obter($id) ?? (object) ['id' => $id, 'nome' => $nome];
    }

    /** @param array<string, mixed> $data */
    public static function atualizar(int $id, array $data): object
    {
        $row = DB::table('pdv_contas_assinadas')->where('id', $id)->first();
        if (! $row) {
            throw new \RuntimeException('Conta assinada não encontrada.');
        }
        $upd = ['updated_at' => now()];
        if (array_key_exists('nome', $data) && empty($row->funcionario_id)) {
            $nome = trim((string) $data['nome']);
            if ($nome === '') {
                throw new \RuntimeException('Informe o nome.');
            }
            $upd['nome'] = mb_substr($nome, 0, 160);
        }
        if (array_key_exists('telefone', $data)) {
            $t = trim((string) ($data['telefone'] ?? ''));
            $upd['telefone'] = $t !== '' ? mb_substr($t, 0, 40) : null;
        }
        if (array_key_exists('observacao', $data)) {
            $o = trim((string) ($data['observacao'] ?? ''));
            $upd['observacao'] = $o !== '' ? mb_substr($o, 0, 300) : null;
        }
        if (array_key_exists('ativo', $data)) {
            $upd['ativo'] = (bool) $data['ativo'];
        }
        if (array_key_exists('unidade_id', $data)) {
            $upd['unidade_id'] = ! empty($data['unidade_id']) ? (int) $data['unidade_id'] : null;
        }
        DB::table('pdv_contas_assinadas')->where('id', $id)->update($upd);

        return self::obter($id) ?? $row;
    }

    public static function validarParaPagamento(array $payload): ?string
    {
        $forma = (string) ($payload['forma_pagamento'] ?? '');
        if (! self::isFormaContaAssinada($forma)) {
            return null;
        }
        if (! self::moduloAtivo()) {
            return 'Contas assinadas ainda não estão disponíveis (migração pendente).';
        }
        $contaId = (int) ($payload['conta_assinada_id'] ?? 0);
        if ($contaId <= 0) {
            return 'Selecione a conta assinada.';
        }
        $conta = DB::table('pdv_contas_assinadas')->where('id', $contaId)->first();
        if (! $conta || ! (int) $conta->ativo) {
            return 'Conta assinada inválida ou inativa.';
        }

        return null;
    }

    /**
     * Registra consumo na conta após a venda.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $vendaResult
     */
    public static function registrarConsumoVenda(array $payload, array $vendaResult, int $usuarioId): void
    {
        $forma = (string) ($payload['forma_pagamento'] ?? '');
        if (! self::isFormaContaAssinada($forma)) {
            return;
        }
        if (! self::moduloAtivo() || ! Schema::hasTable('pdv_conta_assinada_lancamentos')) {
            return;
        }
        $contaId = (int) ($payload['conta_assinada_id'] ?? 0);
        $vendaId = (int) ($vendaResult['venda_id'] ?? 0);
        $valor = round((float) ($vendaResult['valor_liquido'] ?? 0), 2);
        if ($contaId <= 0 || $valor <= 0) {
            return;
        }
        if (! empty($vendaResult['replayed']) && $vendaId > 0) {
            $ja = DB::table('pdv_conta_assinada_lancamentos')
                ->where('venda_id', $vendaId)
                ->where('tipo', 'consumo')
                ->exists();
            if ($ja) {
                return;
            }
        }

        DB::table('pdv_conta_assinada_lancamentos')->insert([
            'conta_id' => $contaId,
            'unidade_id' => ! empty($payload['unidade_id']) ? (int) $payload['unidade_id'] : null,
            'venda_id' => $vendaId > 0 ? $vendaId : null,
            'tipo' => 'consumo',
            'valor' => $valor,
            'observacao' => $vendaId > 0 ? ('PDV venda #'.$vendaId) : 'Consumo PDV',
            'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($vendaId > 0 && Schema::hasColumn('vendas', 'conta_assinada_id')) {
            DB::table('vendas')->where('id', $vendaId)->update([
                'conta_assinada_id' => $contaId,
                'updated_at' => now(),
            ]);
        }
    }

    /** @return list<object> */
    public static function lancamentos(int $contaId, int $limit = 80): array
    {
        if (! Schema::hasTable('pdv_conta_assinada_lancamentos')) {
            return [];
        }

        return DB::table('pdv_conta_assinada_lancamentos')
            ->where('conta_id', $contaId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    /** @param array<string, mixed> $data */
    public static function quitar(int $contaId, array $data, int $usuarioId): object
    {
        $conta = DB::table('pdv_contas_assinadas')->where('id', $contaId)->first();
        if (! $conta || ! (int) $conta->ativo) {
            throw new \RuntimeException('Conta assinada inválida.');
        }
        $saldo = (float) (self::saldosPorConta([$contaId])[$contaId] ?? 0);
        $valor = isset($data['valor']) ? round((float) $data['valor'], 2) : $saldo;
        if ($valor <= 0) {
            throw new \RuntimeException('Informe um valor de quitação válido.');
        }
        if ($valor > $saldo + 0.009) {
            throw new \RuntimeException('Valor maior que o saldo em aberto ('.number_format($saldo, 2, ',', '.').').');
        }
        DB::table('pdv_conta_assinada_lancamentos')->insert([
            'conta_id' => $contaId,
            'unidade_id' => $conta->unidade_id,
            'venda_id' => null,
            'tipo' => 'quitacao',
            'valor' => $valor,
            'observacao' => isset($data['observacao']) ? mb_substr(trim((string) $data['observacao']), 0, 300) : null,
            'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return self::obter($contaId) ?? $conta;
    }
}
