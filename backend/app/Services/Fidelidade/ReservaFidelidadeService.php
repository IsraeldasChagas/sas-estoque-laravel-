<?php

namespace App\Services\Fidelidade;

use App\Models\ReservaMesa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Ponte Reserva de Mesa ↔ Fidelidade (cartão por telefone, selo e resgate).
 */
final class ReservaFidelidadeService
{
    public function __construct(
        private FidelidadeLedgerService $ledger,
        private FidelidadeResgateService $resgate
    ) {}

    public function tabelasDisponiveis(): bool
    {
        return Schema::hasTable('fid_contas')
            && Schema::hasTable('fid_programas')
            && Schema::hasTable('fid_ledger');
    }

    /**
     * @return array{
     *   disponivel:bool,
     *   programa_ativo:bool,
     *   programa:?object,
     *   conta:?object,
     *   telefone_ok:bool,
     *   selo_ja_creditado:bool,
     *   meta_selos:int,
     *   mensagem:?string
     * }
     */
    public function snapshot(ReservaMesa $reserva): array
    {
        if (! $this->tabelasDisponiveis()) {
            return $this->vazio('Módulo de fidelidade não instalado.');
        }

        $unidadeId = (int) $reserva->unidade_id;
        $programa = DB::table('fid_programas')->where('unidade_id', $unidadeId)->first();
        $programaAtivo = $programa && (bool) $programa->ativo;

        $tel = FidelidadeNormalizer::telefone($reserva->telefone_cliente);
        if ($tel === '' || strlen($tel) < 10) {
            return [
                'disponivel' => true,
                'programa_ativo' => $programaAtivo,
                'programa' => $programa,
                'conta' => null,
                'telefone_ok' => false,
                'selo_ja_creditado' => false,
                'meta_selos' => (int) ($programa->pedidos_meta ?? 10),
                'mensagem' => 'Informe um telefone válido na reserva para usar fidelidade.',
            ];
        }

        $conta = DB::table('fid_contas')
            ->where('unidade_id', $unidadeId)
            ->where('telefone_normalizado', $tel)
            ->first();

        $seloJa = false;
        if ($conta && Schema::hasTable('fid_ledger')) {
            $seloJa = DB::table('fid_ledger')
                ->where('conta_id', $conta->id)
                ->where('tipo', 'selo')
                ->where('referencia_tipo', 'reserva_mesa')
                ->where('referencia_id', (int) $reserva->id)
                ->whereNull('reverso_de_id')
                ->exists();
        }

        return [
            'disponivel' => true,
            'programa_ativo' => $programaAtivo,
            'programa' => $programa,
            'conta' => $conta,
            'telefone_ok' => true,
            'selo_ja_creditado' => $seloJa,
            'meta_selos' => (int) ($programa->pedidos_meta ?? 10),
            'mensagem' => $programaAtivo
                ? null
                : 'Programa de fidelidade inativo nesta unidade. Ative em Fidelidade → Programa.',
        ];
    }

    /**
     * Garante cartão ativo para o telefone da reserva.
     */
    public function garantirConta(ReservaMesa $reserva, ?int $usuarioId): object
    {
        if (! $this->tabelasDisponiveis()) {
            throw ValidationException::withMessages(['fidelidade' => 'Módulo de fidelidade não instalado.']);
        }

        $tel = FidelidadeNormalizer::telefone($reserva->telefone_cliente);
        if ($tel === '' || strlen($tel) < 10) {
            throw ValidationException::withMessages(['telefone' => 'Telefone da reserva inválido para fidelidade.']);
        }

        $unidadeId = (int) $reserva->unidade_id;
        $existente = DB::table('fid_contas')
            ->where('unidade_id', $unidadeId)
            ->where('telefone_normalizado', $tel)
            ->first();

        if ($existente) {
            if ((string) $existente->status !== 'ativo') {
                DB::table('fid_contas')->where('id', $existente->id)->update([
                    'status' => 'ativo',
                    'nome' => FidelidadeNormalizer::nome($reserva->nome_cliente) ?: $existente->nome,
                    'updated_at' => now(),
                ]);
            } elseif (! $existente->nome && $reserva->nome_cliente) {
                DB::table('fid_contas')->where('id', $existente->id)->update([
                    'nome' => FidelidadeNormalizer::nome($reserva->nome_cliente),
                    'updated_at' => now(),
                ]);
            }

            return DB::table('fid_contas')->where('id', $existente->id)->first();
        }

        $agora = now();
        $id = DB::table('fid_contas')->insertGetId([
            'unidade_id' => $unidadeId,
            'telefone_normalizado' => $tel,
            'cpf_normalizado' => null,
            'email' => null,
            'nome' => FidelidadeNormalizer::nome($reserva->nome_cliente),
            'codigo_fidelidade' => FidelidadeCodigoService::gerar(),
            'status' => 'ativo',
            'saldo_selos' => 0,
            'saldo_pontos' => 0,
            'total_resgates' => 0,
            'origem_tipo' => 'reserva_mesa',
            'origem_id' => (int) $reserva->id,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $this->ledger->aplicar([
            'conta_id' => $id,
            'tipo' => 'geracao',
            'delta_selos' => 0,
            'delta_pontos' => 0,
            'descricao' => 'Cadastro via reserva de mesa #'.$reserva->id,
            'usuario_id' => $usuarioId,
            'idempotency_key' => 'geracao-conta-'.$id,
            'referencia_tipo' => 'reserva_mesa',
            'referencia_id' => (int) $reserva->id,
        ]);

        return DB::table('fid_contas')->where('id', $id)->first();
    }

    /**
     * Credita 1 selo pela reserva (idempotente por reserva).
     *
     * @return array{conta:object, ledger:object, replayed:bool, criado_conta:bool}
     */
    public function creditarSelo(ReservaMesa $reserva, ?int $usuarioId, bool $criarConta = true): array
    {
        $snap = $this->snapshot($reserva);
        if (! $snap['disponivel']) {
            throw ValidationException::withMessages(['fidelidade' => $snap['mensagem'] ?: 'Fidelidade indisponível.']);
        }
        if (! $snap['programa_ativo']) {
            throw ValidationException::withMessages(['programa' => 'Programa de fidelidade inativo nesta unidade.']);
        }
        if (! $snap['telefone_ok']) {
            throw ValidationException::withMessages(['telefone' => 'Telefone da reserva inválido.']);
        }

        $criado = false;
        $conta = $snap['conta'];
        if (! $conta) {
            if (! $criarConta) {
                throw ValidationException::withMessages(['conta' => 'Cliente sem cartão fidelidade.']);
            }
            $conta = $this->garantirConta($reserva, $usuarioId);
            $criado = true;
        }

        $programa = $snap['programa'];
        $pontosPorSelo = (int) ($programa->pontos_por_selo ?? 1);
        $expiraEm = null;
        if ($programa && ! empty($programa->dias_expiracao_credito)) {
            $expiraEm = now()->addDays((int) $programa->dias_expiracao_credito);
        }

        $result = $this->ledger->aplicar([
            'conta_id' => (int) $conta->id,
            'tipo' => 'selo',
            'delta_selos' => 1,
            'delta_pontos' => $pontosPorSelo,
            'descricao' => 'Selo pela reserva #'.$reserva->id.' ('.$reserva->nome_cliente.')',
            'referencia_tipo' => 'reserva_mesa',
            'referencia_id' => (int) $reserva->id,
            'idempotency_key' => 'reserva-'.$reserva->id.'-selo',
            'expira_em' => $expiraEm,
            'usuario_id' => $usuarioId,
        ]);

        return [
            'conta' => $result['conta'],
            'ledger' => $result['ledger'],
            'replayed' => $result['replayed'],
            'criado_conta' => $criado,
        ];
    }

    /**
     * @return array<int, object>
     */
    public function listarRecompensas(int $unidadeId): array
    {
        if (! Schema::hasTable('fid_recompensas')) {
            return [];
        }

        return DB::table('fid_recompensas')
            ->where('unidade_id', $unidadeId)
            ->where('ativo', 1)
            ->orderBy('titulo')
            ->limit(100)
            ->get()
            ->all();
    }

    /**
     * Resgata com selos e marca como entregue (pagamento no salão).
     *
     * @return array{resgate:object, ledger:object, conta:object, replayed:bool}
     */
    public function pagarComSelos(
        ReservaMesa $reserva,
        ?int $recompensaId,
        ?int $usuarioId,
        ?string $observacao = null
    ): array {
        $snap = $this->snapshot($reserva);
        if (! $snap['disponivel'] || ! $snap['programa_ativo']) {
            throw ValidationException::withMessages(['programa' => 'Programa de fidelidade indisponível.']);
        }
        if (! $snap['telefone_ok']) {
            throw ValidationException::withMessages(['telefone' => 'Telefone da reserva inválido.']);
        }

        $conta = $snap['conta'] ?: $this->garantirConta($reserva, $usuarioId);
        $obs = $observacao ?: ('Resgate na reserva #'.$reserva->id);
        $key = 'reserva-'.$reserva->id.'-resgate-'.($recompensaId ?: 'meta').'-'.substr(md5($obs.(string) microtime(true)), 0, 8);

        $result = $this->resgate->resgatar(
            (int) $conta->id,
            $recompensaId,
            $usuarioId,
            $key,
            $obs
        );

        if ($result['resgate'] && (string) $result['resgate']->status === 'pendente' && Schema::hasTable('fid_resgates')) {
            DB::table('fid_resgates')->where('id', $result['resgate']->id)->update([
                'status' => 'entregue',
                'observacao' => trim(($result['resgate']->observacao ?: '').' · Entregue na reserva #'.$reserva->id),
                'updated_at' => now(),
            ]);
            $result['resgate'] = DB::table('fid_resgates')->where('id', $result['resgate']->id)->first();
        }

        return $result;
    }

    /**
     * @return array{
     *   disponivel:bool,
     *   programa_ativo:bool,
     *   programa:?object,
     *   conta:?object,
     *   telefone_ok:bool,
     *   selo_ja_creditado:bool,
     *   meta_selos:int,
     *   mensagem:?string
     * }
     */
    private function vazio(string $mensagem): array
    {
        return [
            'disponivel' => false,
            'programa_ativo' => false,
            'programa' => null,
            'conta' => null,
            'telefone_ok' => false,
            'selo_ja_creditado' => false,
            'meta_selos' => 10,
            'mensagem' => $mensagem,
        ];
    }
}
