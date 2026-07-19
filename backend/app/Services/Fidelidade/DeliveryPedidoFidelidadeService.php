<?php

namespace App\Services\Fidelidade;

use App\Http\Controllers\Delivery\DeliveryFidelidadePublicController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Cartão fidelidade após compra na vitrine delivery (paridade reserva de mesa).
 */
final class DeliveryPedidoFidelidadeService
{
    public function __construct(
        private FidelidadeLedgerService $ledger,
        private FidelidadeIdentidadeService $identidade,
        private FidelidadePublicConsultaService $consulta,
    ) {}

    public function tabelasDisponiveis(): bool
    {
        return Schema::hasTable('fid_contas')
            && Schema::hasTable('fid_programas')
            && Schema::hasTable('fid_ledger');
    }

    public function unidadeFidelidade(object $config): int
    {
        return $this->consulta->unidadeFidelidade($config);
    }

    public function programaAtivo(object $config): ?object
    {
        if (! $this->tabelasDisponiveis()) {
            return null;
        }

        $unidadeFid = $this->unidadeFidelidade($config);

        return DB::table('fid_programas')
            ->where('unidade_id', $unidadeFid)
            ->where('ativo', 1)
            ->first();
    }

    public function dadosFidelidadeOk(object $pedido): bool
    {
        return trim((string) ($pedido->fidelidade_nome ?? '')) !== ''
            && trim((string) ($pedido->fidelidade_cpf ?? '')) !== ''
            && trim((string) ($pedido->fidelidade_email ?? '')) !== '';
    }

    public function seloCreditado(object $pedido): bool
    {
        return ! empty($pedido->fidelidade_selo_creditado_em);
    }

    public function precisaFormulario(object $pedido): bool
    {
        return (bool) ($pedido->participa_fidelidade ?? false) && ! $this->seloCreditado($pedido);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(object $pedido, object $config): array
    {
        $programa = $this->programaAtivo($config);
        $unidadeFid = $this->unidadeFidelidade($config);
        $tel = FidelidadeNormalizer::telefone($pedido->fidelidade_whatsapp ?? $pedido->cliente_whatsapp ?? $pedido->cliente_telefone);
        $conta = ($this->tabelasDisponiveis() && $tel !== '' && strlen($tel) >= 10)
            ? $this->consulta->buscarContaAtivaNaUnidade($unidadeFid, $tel)
            : null;

        return [
            'disponivel' => $this->tabelasDisponiveis() && Schema::hasColumn('dlv_pedidos', 'participa_fidelidade'),
            'programa_ativo' => $programa !== null,
            'participa_fidelidade' => (bool) ($pedido->participa_fidelidade ?? false),
            'precisa_formulario' => $this->precisaFormulario($pedido),
            'dados_ok' => $this->dadosFidelidadeOk($pedido),
            'selo_creditado' => $this->seloCreditado($pedido),
            'fidelidade_nome' => $pedido->fidelidade_nome ?? null,
            'fidelidade_cpf' => $pedido->fidelidade_cpf ?? null,
            'fidelidade_email' => $pedido->fidelidade_email ?? null,
            'fidelidade_whatsapp' => $pedido->fidelidade_whatsapp ?? $pedido->cliente_whatsapp ?? $pedido->cliente_telefone,
            'meta_selos' => (int) ($programa->pedidos_meta ?? 10),
            'saldo_selos' => $conta ? (int) ($conta->saldo_selos ?? 0) : 0,
        ];
    }

    /**
     * Salva identidade, aceite LGPD, garante cartão e credita 1 selo pelo pedido.
     *
     * @return array<string, mixed>
     */
    public function concluirCadastro(
        object $pedido,
        object $config,
        ?string $nome,
        ?string $cpf,
        ?string $email,
        ?string $whatsapp,
        bool $lgpdAutorizo,
        ?string $ip
    ): array {
        if (! $this->tabelasDisponiveis()) {
            throw ValidationException::withMessages(['fidelidade' => 'Módulo de fidelidade não instalado.']);
        }
        if (! (bool) ($pedido->participa_fidelidade ?? false)) {
            throw ValidationException::withMessages(['fidelidade' => 'Este pedido não solicitou cartão fidelidade.']);
        }
        if ($this->seloCreditado($pedido)) {
            throw ValidationException::withMessages(['fidelidade' => 'Cartão fidelidade já ativado para este pedido.']);
        }
        if (! $lgpdAutorizo) {
            throw ValidationException::withMessages(['lgpd_autorizo' => 'Marque o termo LGPD para continuar.']);
        }

        $programa = $this->programaAtivo($config);
        if (! $programa) {
            throw ValidationException::withMessages(['programa' => 'Programa de fidelidade inativo nesta unidade.']);
        }

        $tel = FidelidadeNormalizer::telefone($whatsapp ?: $pedido->cliente_whatsapp ?: $pedido->cliente_telefone);
        if ($tel === '' || strlen($tel) < 10) {
            throw ValidationException::withMessages(['fidelidade_whatsapp' => 'Informe um WhatsApp válido com DDD.']);
        }

        $unidadeFid = $this->unidadeFidelidade($config);
        $contaId = DB::table('fid_contas')
            ->where('unidade_id', $unidadeFid)
            ->where('telefone_normalizado', $tel)
            ->value('id');

        $dados = $this->identidade->validarCadastro(
            $unidadeFid,
            $tel,
            $nome,
            $cpf,
            $email,
            $contaId ? (int) $contaId : null
        );

        $agora = now();
        $update = [
            'fidelidade_nome' => $dados['nome'],
            'fidelidade_cpf' => $dados['cpf'],
            'fidelidade_email' => $dados['email'],
            'fidelidade_whatsapp' => $tel,
            'fidelidade_lgpd_aceite_em' => $agora,
            'updated_at' => $agora,
        ];
        if (Schema::hasColumn('dlv_pedidos', 'cliente_whatsapp') && empty($pedido->cliente_whatsapp)) {
            $update['cliente_whatsapp'] = $tel;
        }
        DB::table('dlv_pedidos')->where('id', $pedido->id)->update($update);
        $pedido = DB::table('dlv_pedidos')->where('id', $pedido->id)->first();

        $conta = $this->garantirConta($pedido, $config, $unidadeFid, $tel, $dados);
        FidelidadeLgpdService::registrarAceite((int) $conta->id, $ip);
        DeliveryFidelidadePublicController::limparOrigemCompraSessao((int) $config->unidade_id);

        $credito = $this->creditarSelo($pedido, $config, $programa, $conta);

        DB::table('dlv_pedidos')->where('id', $pedido->id)->update([
            'fidelidade_selo_creditado_em' => $agora,
            'updated_at' => $agora,
        ]);

        return [
            'mensagem' => 'Cartão fidelidade ativado! Você ganhou 1 selo nesta compra.',
            'saldo_selos' => (int) ($credito['conta']->saldo_selos ?? 0),
            'meta_selos' => (int) ($programa->pedidos_meta ?? 10),
            'codigo_fidelidade' => (string) ($credito['conta']->codigo_fidelidade ?? ''),
        ];
    }

    /**
     * @param  array{nome:string,cpf:string,email:string}  $dados
     */
    private function garantirConta(object $pedido, object $config, int $unidadeFid, string $tel, array $dados): object
    {
        $existente = DB::table('fid_contas')
            ->where('unidade_id', $unidadeFid)
            ->where('telefone_normalizado', $tel)
            ->first();

        if ($existente) {
            DB::table('fid_contas')->where('id', $existente->id)->update([
                'status' => 'ativo',
                'nome' => $dados['nome'],
                'cpf_normalizado' => $dados['cpf'],
                'email' => $dados['email'],
                'updated_at' => now(),
            ]);

            return DB::table('fid_contas')->where('id', $existente->id)->first();
        }

        $agora = now();
        $id = DB::table('fid_contas')->insertGetId([
            'unidade_id' => $unidadeFid,
            'telefone_normalizado' => $tel,
            'cpf_normalizado' => $dados['cpf'],
            'email' => $dados['email'],
            'nome' => $dados['nome'],
            'codigo_fidelidade' => FidelidadeCodigoService::gerar(),
            'status' => 'ativo',
            'saldo_selos' => 0,
            'saldo_pontos' => 0,
            'total_resgates' => 0,
            'origem_tipo' => 'delivery_pedido',
            'origem_id' => (int) $pedido->id,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $this->ledger->aplicar([
            'conta_id' => $id,
            'tipo' => 'geracao',
            'delta_selos' => 0,
            'delta_pontos' => 0,
            'descricao' => 'Cadastro via pedido delivery '.$pedido->codigo_publico,
            'usuario_id' => null,
            'idempotency_key' => 'geracao-conta-'.$id,
            'referencia_tipo' => 'delivery_pedido',
            'referencia_id' => (int) $pedido->id,
        ]);

        return DB::table('fid_contas')->where('id', $id)->first();
    }

    /**
     * @return array{conta:object,replayed:bool}
     */
    private function creditarSelo(object $pedido, object $config, object $programa, object $conta): array
    {
        $pontosPorSelo = (int) ($programa->pontos_por_selo ?? 1);
        $expiraEm = ! empty($programa->dias_expiracao_credito)
            ? now()->addDays((int) $programa->dias_expiracao_credito)
            : null;

        $result = $this->ledger->aplicar([
            'conta_id' => (int) $conta->id,
            'tipo' => 'selo',
            'delta_selos' => 1,
            'delta_pontos' => $pontosPorSelo,
            'descricao' => 'Selo pelo pedido '.($pedido->codigo_publico ?? $pedido->id),
            'referencia_tipo' => 'delivery_pedido',
            'referencia_id' => (int) $pedido->id,
            'idempotency_key' => 'delivery-pedido-'.$pedido->id.'-selo',
            'expira_em' => $expiraEm,
            'usuario_id' => null,
        ]);

        return [
            'conta' => $result['conta'],
            'replayed' => $result['replayed'],
        ];
    }
}
