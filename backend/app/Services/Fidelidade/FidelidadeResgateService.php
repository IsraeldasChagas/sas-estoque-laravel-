<?php

namespace App\Services\Fidelidade;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FidelidadeResgateService
{
    public function __construct(
        private FidelidadeLedgerService $ledger,
        private FidelidadeCatalogoConsultaService $catalogoConsulta,
    ) {}

    /**
     * Redeem program meta or a catalog reward.
     *
     * @param  list<int>|list<array{produto_id?:int,id?:int,qtd?:int}>|null  $catalogoEscolhas
     * @return array{resgate:object, ledger:object, conta:object, replayed:bool}
     */
    public function resgatar(
        int $contaId,
        ?int $recompensaId,
        ?int $usuarioId,
        ?string $idempotencyKey,
        ?string $observacao = null,
        ?array $catalogoEscolhas = null,
    ): array {
        return DB::transaction(function () use ($contaId, $recompensaId, $usuarioId, $idempotencyKey, $observacao, $catalogoEscolhas) {
            $conta = DB::table('fid_contas')->where('id', $contaId)->lockForUpdate()->first();
            if (! $conta) {
                throw ValidationException::withMessages(['conta_id' => 'Cartão não encontrado.']);
            }
            if ((string) $conta->status !== 'ativo') {
                throw ValidationException::withMessages(['status' => 'Somente cartões ativos podem resgatar.']);
            }

            $key = $this->ledger->normalizeKey($idempotencyKey);
            if ($key !== null) {
                $existingLedger = DB::table('fid_ledger')
                    ->where('unidade_id', $conta->unidade_id)
                    ->where('idempotency_key', $key)
                    ->first();
                if ($existingLedger) {
                    $resgate = DB::table('fid_resgates')->where('ledger_id', $existingLedger->id)->first();
                    $fresh = DB::table('fid_contas')->where('id', $contaId)->first();

                    return [
                        'resgate' => $resgate,
                        'ledger' => $existingLedger,
                        'conta' => $fresh,
                        'replayed' => true,
                    ];
                }
            }

            $programa = DB::table('fid_programas')->where('unidade_id', $conta->unidade_id)->first();
            if (! $programa || ! $programa->ativo) {
                throw ValidationException::withMessages(['programa' => 'Programa de fidelidade inativo ou inexistente.']);
            }

            $titulo = (string) ($programa->texto_recompensa ?: $programa->nome_exibicao);
            $tipo = (string) $programa->tipo_recompensa_padrao;
            if ($tipo === 'produto') {
                $tipo = 'catalogo_consulta';
            }
            $custoSelos = 0;
            $custoPontos = 0;
            $recompensa = null;
            $catalogoEscolhasJson = null;

            if ($recompensaId) {
                $recompensa = DB::table('fid_recompensas')
                    ->where('id', $recompensaId)
                    ->where('unidade_id', $conta->unidade_id)
                    ->where('ativo', 1)
                    ->first();
                if (! $recompensa) {
                    throw ValidationException::withMessages(['recompensa_id' => 'Recompensa não encontrada.']);
                }
                $titulo = (string) $recompensa->titulo;
                $tipo = (string) $recompensa->tipo;
                $custoSelos = (int) $recompensa->custo_selos;
                $custoPontos = (int) $recompensa->custo_pontos;
            } else {
                $modo = (string) ($programa->modo ?? 'selos');
                if ($modo === 'pontos') {
                    $custoPontos = (int) $programa->pedidos_meta;
                } else {
                    $custoSelos = (int) $programa->pedidos_meta;
                }
            }

            if (! $recompensaId && $tipo === 'catalogo_consulta') {
                if ($catalogoEscolhas === null || $catalogoEscolhas === []) {
                    throw ValidationException::withMessages([
                        'catalogo_escolhas' => ['Escolha o(s) produto(s) da recompensa antes de resgatar.'],
                    ]);
                }
                $normalizado = $this->catalogoConsulta->normalizarEscolhasResgate(
                    $programa,
                    (int) $conta->unidade_id,
                    $catalogoEscolhas
                );
                $titulo = $normalizado['titulo'];
                if ($this->catalogoConsulta->resgateColunasDisponiveis()) {
                    $catalogoEscolhasJson = $normalizado['json'];
                }
            }

            if ($custoSelos <= 0 && $custoPontos <= 0) {
                throw ValidationException::withMessages(['custo' => 'Custo de resgate inválido.']);
            }

            if ((int) $conta->saldo_selos < $custoSelos || (int) $conta->saldo_pontos < $custoPontos) {
                throw ValidationException::withMessages([
                    'saldo' => 'Saldo insuficiente para resgate.',
                ]);
            }

            $expiraEm = null;
            if (! empty($programa->dias_expiracao_credito)) {
                // not used on debit; kept for symmetry
            }

            $movimento = $this->ledger->aplicar([
                'conta_id' => $contaId,
                'tipo' => 'debito_resgate',
                'delta_selos' => -1 * $custoSelos,
                'delta_pontos' => -1 * $custoPontos,
                'descricao' => $observacao ?: ('Resgate: '.$titulo),
                'referencia_tipo' => $recompensa ? 'recompensa' : 'programa',
                'referencia_id' => $recompensa ? (int) $recompensa->id : (int) $programa->id,
                'idempotency_key' => $key,
                'usuario_id' => $usuarioId,
            ]);

            if ($movimento['replayed']) {
                $resgate = DB::table('fid_resgates')->where('ledger_id', $movimento['ledger']->id)->first();

                return [
                    'resgate' => $resgate,
                    'ledger' => $movimento['ledger'],
                    'conta' => $movimento['conta'],
                    'replayed' => true,
                ];
            }

            $agora = now();
            $insert = [
                'unidade_id' => (int) $conta->unidade_id,
                'conta_id' => $contaId,
                'recompensa_id' => $recompensa ? (int) $recompensa->id : null,
                'ledger_id' => (int) $movimento['ledger']->id,
                'status' => 'pendente',
                'titulo_snapshot' => $titulo,
                'tipo_snapshot' => $tipo,
                'custo_selos' => $custoSelos,
                'custo_pontos' => $custoPontos,
                'usuario_id' => $usuarioId,
                'observacao' => $observacao,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
            if ($catalogoEscolhasJson !== null && $this->catalogoConsulta->resgateColunasDisponiveis()) {
                $insert['catalogo_escolhas_json'] = $catalogoEscolhasJson;
            }
            $resgateId = DB::table('fid_resgates')->insertGetId($insert);

            $resgate = DB::table('fid_resgates')->where('id', $resgateId)->first();

            return [
                'resgate' => $resgate,
                'ledger' => $movimento['ledger'],
                'conta' => $movimento['conta'],
                'replayed' => false,
            ];
        });
    }
}
