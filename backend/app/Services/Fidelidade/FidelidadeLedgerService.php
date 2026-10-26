<?php

namespace App\Services\Fidelidade;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class FidelidadeLedgerService
{
    public const TIPOS = [
        'geracao',
        'selo',
        'credito',
        'debito_resgate',
        'ajuste',
        'status',
        'reversao',
        'expiracao',
    ];

    /**
     * Append-only movement with lock, non-negative balances and idempotent replay.
     *
     * @param  array{
     *   conta_id:int,
     *   tipo:string,
     *   delta_selos?:int,
     *   delta_pontos?:int,
     *   descricao?:?string,
     *   referencia_tipo?:?string,
     *   referencia_id?:?int,
     *   idempotency_key?:?string,
     *   reverso_de_id?:?int,
     *   expira_em?:?\DateTimeInterface|string|null,
     *   usuario_id?:?int,
     *   permitir_bloqueado?:bool
     * }  $params
     * @return array{ledger:object, conta:object, replayed:bool}
     */
    public function aplicar(array $params): array
    {
        $tipo = (string) ($params['tipo'] ?? '');
        if (! in_array($tipo, self::TIPOS, true)) {
            throw ValidationException::withMessages(['tipo' => 'Tipo de movimento inválido.']);
        }

        return DB::transaction(function () use ($params, $tipo) {
            $contaId = (int) $params['conta_id'];
            $conta = DB::table('fid_contas')->where('id', $contaId)->lockForUpdate()->first();
            if (! $conta) {
                throw ValidationException::withMessages(['conta_id' => 'Cartão não encontrado.']);
            }

            $key = $this->normalizeKey($params['idempotency_key'] ?? null);
            if ($key !== null) {
                $existing = DB::table('fid_ledger')
                    ->where('unidade_id', $conta->unidade_id)
                    ->where('idempotency_key', $key)
                    ->first();
                if ($existing) {
                    $fresh = DB::table('fid_contas')->where('id', $contaId)->first();

                    return ['ledger' => $existing, 'conta' => $fresh, 'replayed' => true];
                }
            }

            $status = (string) ($conta->status ?? 'ativo');
            $permitirBloqueado = (bool) ($params['permitir_bloqueado'] ?? false);
            if ($status === 'bloqueado' && ! $permitirBloqueado && ! in_array($tipo, ['status', 'reversao'], true)) {
                throw ValidationException::withMessages(['status' => 'Cartão bloqueado.']);
            }
            if ($status === 'inativo' && ! in_array($tipo, ['status', 'geracao', 'reversao'], true)) {
                throw ValidationException::withMessages(['status' => 'Cartão inativo.']);
            }

            $deltaSelos = (int) ($params['delta_selos'] ?? 0);
            $deltaPontos = (int) ($params['delta_pontos'] ?? 0);
            $novoSelos = (int) $conta->saldo_selos + $deltaSelos;
            $novoPontos = (int) $conta->saldo_pontos + $deltaPontos;

            if ($novoSelos < 0 || $novoPontos < 0) {
                throw ValidationException::withMessages([
                    'saldo' => 'Saldo insuficiente para esta operação.',
                ]);
            }

            $agora = now();
            $ledgerId = DB::table('fid_ledger')->insertGetId([
                'unidade_id' => (int) $conta->unidade_id,
                'conta_id' => $contaId,
                'tipo' => $tipo,
                'delta_selos' => $deltaSelos,
                'delta_pontos' => $deltaPontos,
                'saldo_selos_apos' => $novoSelos,
                'saldo_pontos_apos' => $novoPontos,
                'descricao' => $params['descricao'] ?? null,
                'referencia_tipo' => $params['referencia_tipo'] ?? null,
                'referencia_id' => isset($params['referencia_id']) ? (int) $params['referencia_id'] : null,
                'idempotency_key' => $key,
                'reverso_de_id' => isset($params['reverso_de_id']) ? (int) $params['reverso_de_id'] : null,
                'expira_em' => $params['expira_em'] ?? null,
                'usuario_id' => isset($params['usuario_id']) ? (int) $params['usuario_id'] : null,
                'created_at' => $agora,
            ]);

            $update = [
                'saldo_selos' => $novoSelos,
                'saldo_pontos' => $novoPontos,
                'updated_at' => $agora,
            ];
            if ($tipo === 'debito_resgate') {
                $update['total_resgates'] = (int) $conta->total_resgates + 1;
            }

            DB::table('fid_contas')->where('id', $contaId)->update($update);

            $ledger = DB::table('fid_ledger')->where('id', $ledgerId)->first();
            $fresh = DB::table('fid_contas')->where('id', $contaId)->first();

            return ['ledger' => $ledger, 'conta' => $fresh, 'replayed' => false];
        });
    }

    /**
     * Compensating entry only — never mutates the original ledger row.
     *
     * @return array{ledger:object, conta:object, replayed:bool}
     */
    public function estornar(int $ledgerId, ?int $usuarioId = null, ?string $descricao = null, ?string $idempotencyKey = null): array
    {
        return DB::transaction(function () use ($ledgerId, $usuarioId, $descricao, $idempotencyKey) {
            $original = DB::table('fid_ledger')->where('id', $ledgerId)->first();
            if (! $original) {
                throw ValidationException::withMessages(['ledger_id' => 'Movimento não encontrado.']);
            }

            $ja = DB::table('fid_ledger')->where('reverso_de_id', $ledgerId)->exists();
            if ($ja) {
                throw ValidationException::withMessages(['ledger_id' => 'Movimento já estornado.']);
            }

            if ((string) $original->tipo === 'reversao') {
                throw ValidationException::withMessages(['ledger_id' => 'Não é possível estornar uma reversão.']);
            }

            $result = $this->aplicar([
                'conta_id' => (int) $original->conta_id,
                'tipo' => 'reversao',
                'delta_selos' => -1 * (int) $original->delta_selos,
                'delta_pontos' => -1 * (int) $original->delta_pontos,
                'descricao' => $descricao ?: ('Estorno #'.$ledgerId),
                'referencia_tipo' => 'estorno',
                'referencia_id' => $ledgerId,
                'idempotency_key' => $idempotencyKey,
                'reverso_de_id' => $ledgerId,
                'usuario_id' => $usuarioId,
                'permitir_bloqueado' => true,
            ]);

            if (! $result['replayed'] && (string) $original->tipo === 'debito_resgate') {
                DB::table('fid_resgates')
                    ->where('ledger_id', $ledgerId)
                    ->whereIn('status', ['pendente', 'entregue'])
                    ->update([
                        'status' => 'estornado',
                        'updated_at' => now(),
                    ]);

                DB::table('fid_contas')->where('id', $original->conta_id)->update([
                    'total_resgates' => DB::raw('CASE WHEN total_resgates > 0 THEN total_resgates - 1 ELSE 0 END'),
                    'updated_at' => now(),
                ]);

                $result['conta'] = DB::table('fid_contas')->where('id', $original->conta_id)->first();
            }

            return $result;
        });
    }

    public function normalizeKey(mixed $key): ?string
    {
        $key = trim((string) ($key ?? ''));

        return $key !== '' ? mb_substr($key, 0, 128) : null;
    }

    /** Guard: ledger is append-only. */
    public function assertAppendOnly(): void
    {
        throw new RuntimeException('fid_ledger é imutável: use estorno/reversão.');
    }
}
