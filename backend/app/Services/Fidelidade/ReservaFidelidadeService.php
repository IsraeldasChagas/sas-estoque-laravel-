<?php

namespace App\Services\Fidelidade;

use App\Models\ReservaMesa;
use App\Services\Reservas\ReservaMeioPagamentoService;
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
        private FidelidadeResgateService $resgate,
        private ReservaMeioPagamentoService $meiosPagamento,
    ) {}

    public function tabelasDisponiveis(): bool
    {
        return Schema::hasTable('fid_contas')
            && Schema::hasTable('fid_programas')
            && Schema::hasTable('fid_ledger');
    }

    /**
     * @param  mixed  $raw
     * @return list<array{meio_id:int,tipo:string,tipo_label:string,meio_nome:string,label:string,valor:float,rotulo:?string,forma:string}>
     */
    public function normalizarPagamentosConta(int $unidadeId, mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            throw ValidationException::withMessages(['pagamentos' => 'Informe ao menos uma forma de pagamento.']);
        }

        $out = [];
        foreach ($raw as $i => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages(['pagamentos' => 'Pagamento #'.($i + 1).' inválido.']);
            }
            if (! array_key_exists('valor', $item) || ! is_numeric($item['valor'])) {
                throw ValidationException::withMessages(['pagamentos' => 'Informe o valor na linha '.($i + 1).'.']);
            }
            $valor = round((float) $item['valor'], 2);
            if ($valor <= 0) {
                throw ValidationException::withMessages(['pagamentos' => 'Valor deve ser maior que zero na linha '.($i + 1).'.']);
            }

            $meioId = (int) ($item['meio_id'] ?? 0);
            if ($meioId <= 0) {
                throw ValidationException::withMessages(['pagamentos' => 'Selecione a forma de pagamento na linha '.($i + 1).'.']);
            }

            $meio = $this->meiosPagamento->buscarMeio($unidadeId, $meioId);
            if (! $meio) {
                throw ValidationException::withMessages(['pagamentos' => 'Forma de pagamento inválida na linha '.($i + 1).'. Cadastre em Reserva → Forma de pagamento.']);
            }

            $rotulo = trim((string) ($item['rotulo'] ?? ''));

            $out[] = [
                'meio_id' => (int) $meio->id,
                'tipo' => (string) $meio->tipo,
                'tipo_label' => (string) $meio->tipo_label,
                'meio_nome' => (string) $meio->nome,
                'label' => (string) $meio->label,
                'valor' => $valor,
                'rotulo' => $rotulo !== '' ? $rotulo : null,
                'forma' => (string) $meio->tipo,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{forma:string,valor:float,maquina:?string}>  $pagamentos
     */
    public function validarTotalPagamentos(float $valorConta, array $pagamentos): void
    {
        $soma = round(array_sum(array_column($pagamentos, 'valor')), 2);
        $total = round($valorConta, 2);
        if (abs($soma - $total) > 0.009) {
            throw ValidationException::withMessages([
                'pagamentos' => 'A soma dos pagamentos (R$ '.number_format($soma, 2, ',', '.').') deve bater com o valor da conta (R$ '.number_format($total, 2, ',', '.').').',
            ]);
        }
    }

    /**
     * @return list<array{forma:string,forma_label:string,valor:float,meio_id:?int,meio_nome:?string,rotulo:?string,maquina:?string}>
     */
    public function pagamentosContaReserva(ReservaMesa $reserva): array
    {
        $raw = $reserva->pagamentos_conta;
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $valor = round((float) ($item['valor'] ?? 0), 2);
            if ($valor <= 0) {
                continue;
            }
            $tipo = strtolower(trim((string) ($item['tipo'] ?? $item['forma'] ?? '')));
            $tipoLabel = trim((string) ($item['tipo_label'] ?? ''));
            if ($tipoLabel === '' && $tipo !== '') {
                $tipoLabel = \App\Models\ReservaMeioPagamento::TIPOS[$tipo] ?? $tipo;
            }
            $meioNome = trim((string) ($item['meio_nome'] ?? $item['maquina'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '' && $tipoLabel !== '' && $meioNome !== '') {
                $label = $tipoLabel.' — '.$meioNome;
            }
            $rotulo = trim((string) ($item['rotulo'] ?? ''));
            $out[] = [
                'meio_id' => isset($item['meio_id']) ? (int) $item['meio_id'] : null,
                'tipo' => $tipo !== '' ? $tipo : null,
                'tipo_label' => $tipoLabel !== '' ? $tipoLabel : null,
                'meio_nome' => $meioNome !== '' ? $meioNome : null,
                'label' => $label !== '' ? $label : null,
                'valor' => $valor,
                'rotulo' => $rotulo !== '' ? $rotulo : null,
                'forma' => $tipo !== '' ? $tipo : null,
                'forma_label' => $tipoLabel !== '' ? $tipoLabel : null,
                'maquina' => $meioNome !== '' ? $meioNome : null,
            ];
        }

        return $out;
    }

    /**
     * @return list<object>
     */
    public function formasPagamentoCadastro(int $unidadeId): array
    {
        return $this->meiosPagamento->listarAtivos($unidadeId);
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
                'participa_fidelidade' => (bool) ($reserva->participa_fidelidade ?? false),
                'conta_paga' => (bool) ($reserva->conta_paga ?? false),
                'valor_conta' => $reserva->valor_conta !== null ? (float) $reserva->valor_conta : null,
                'conta_paga_em' => $reserva->conta_paga_em
                    ? (string) (is_string($reserva->conta_paga_em) ? $reserva->conta_paga_em : $reserva->conta_paga_em->toDateTimeString())
                    : null,
                'pagamentos_conta' => $this->pagamentosContaReserva($reserva),
                'formas_pagamento_cadastro' => $this->formasPagamentoCadastro($unidadeId),
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
            'participa_fidelidade' => (bool) ($reserva->participa_fidelidade ?? false),
            'conta_paga' => (bool) ($reserva->conta_paga ?? false),
            'valor_conta' => $reserva->valor_conta !== null ? (float) $reserva->valor_conta : null,
            'conta_paga_em' => $reserva->conta_paga_em
                ? (string) (is_string($reserva->conta_paga_em) ? $reserva->conta_paga_em : $reserva->conta_paga_em->toDateTimeString())
                : null,
            'pagamentos_conta' => $this->pagamentosContaReserva($reserva),
            'formas_pagamento_cadastro' => $this->formasPagamentoCadastro($unidadeId),
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
            'participa_fidelidade' => false,
            'conta_paga' => false,
            'valor_conta' => null,
            'conta_paga_em' => null,
            'pagamentos_conta' => [],
            'formas_pagamento_cadastro' => [],
            'meta_selos' => 10,
            'mensagem' => $mensagem,
        ];
    }

    /**
     * Marca conta paga na reserva: gera cartão (se preciso) e libera 1 selo.
     *
     * @return array{reserva:ReservaMesa,conta:object,ledger:object,replayed:bool,criado_conta:bool}
     */
    public function registrarContaPaga(ReservaMesa $reserva, float $valorConta, array $pagamentos, ?int $usuarioId): array
    {
        if ($valorConta < 0) {
            throw ValidationException::withMessages(['valor_conta' => 'Informe o valor da conta (0 ou maior).']);
        }

        $pagamentos = $this->normalizarPagamentosConta((int) $reserva->unidade_id, $pagamentos);
        $this->validarTotalPagamentos($valorConta, $pagamentos);

        if ($reserva->conta_paga) {
            $snap = $this->snapshot($reserva);

            return [
                'reserva' => $reserva->fresh(['mesa', 'usuario']),
                'conta' => $snap['conta'],
                'ledger' => null,
                'replayed' => true,
                'criado_conta' => false,
            ];
        }

        $participa = (bool) ($reserva->participa_fidelidade ?? false);

        if (! $participa) {
            $reserva->conta_paga = true;
            $reserva->valor_conta = round($valorConta, 2);
            $reserva->conta_paga_em = now();
            $reserva->pagamentos_conta = $pagamentos;
            $reserva->save();

            return [
                'reserva' => $reserva->fresh(['mesa', 'usuario']),
                'conta' => null,
                'ledger' => null,
                'replayed' => false,
                'criado_conta' => false,
            ];
        }

        $snap = $this->snapshot($reserva);
        if (! $snap['disponivel']) {
            throw ValidationException::withMessages(['fidelidade' => $snap['mensagem'] ?: 'Fidelidade indisponível.']);
        }
        if (! $snap['programa_ativo']) {
            throw ValidationException::withMessages(['programa' => 'Programa de fidelidade inativo nesta unidade.']);
        }
        if (! $snap['telefone_ok']) {
            throw ValidationException::withMessages(['telefone' => 'Telefone da reserva inválido para gerar o cartão.']);
        }

        $criado = false;
        $conta = $snap['conta'];
        if (! $conta) {
            $conta = $this->garantirConta($reserva, $usuarioId);
            $criado = true;
        }

        $credito = $this->creditarSelo($reserva, $usuarioId, false);

        $reserva->conta_paga = true;
        $reserva->valor_conta = round($valorConta, 2);
        $reserva->conta_paga_em = now();
        $reserva->pagamentos_conta = $pagamentos;
        $reserva->save();

        return [
            'reserva' => $reserva->fresh(['mesa', 'usuario']),
            'conta' => $credito['conta'],
            'ledger' => $credito['ledger'],
            'replayed' => $credito['replayed'],
            'criado_conta' => $criado || $credito['criado_conta'],
        ];
    }
}
