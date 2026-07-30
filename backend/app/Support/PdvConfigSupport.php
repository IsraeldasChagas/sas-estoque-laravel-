<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PdvConfigSupport
{
    public const MODOS_ENCARGO = ['percentual', 'fixo'];

    /** @return array<string, mixed> */
    public static function opcoesPublicas(?object $usuario = null): array
    {
        return array_merge(self::carregar(), [
            'bandeiras_cartao' => self::listarBandeirasAtivas(),
            'encargos_pdv' => self::encargosPublicos(),
            'pode_editar' => self::usuarioPodeEditar($usuario),
        ]);
    }

    /** @return array<string, mixed> */
    public static function encargosPublicos(): array
    {
        $c = self::carregar();

        return [
            'taxa_servico' => [
                'ativa' => (bool) ($c['taxa_servico_ativa'] ?? false),
                'modo' => $c['taxa_servico_modo'] ?? 'percentual',
                'valor' => (float) ($c['taxa_servico_valor'] ?? 0),
                'padrao_mesa' => (bool) ($c['taxa_servico_padrao_mesa'] ?? true),
                'padrao_balcao' => (bool) ($c['taxa_servico_padrao_balcao'] ?? true),
            ],
            'pagamento_cantor' => [
                'ativo' => (bool) ($c['pagamento_cantor_ativo'] ?? false),
                'modo' => $c['pagamento_cantor_modo'] ?? 'percentual',
                'valor' => (float) ($c['pagamento_cantor_valor'] ?? 0),
                'padrao_mesa' => (bool) ($c['pagamento_cantor_padrao_mesa'] ?? true),
                'padrao_balcao' => (bool) ($c['pagamento_cantor_padrao_balcao'] ?? true),
            ],
            'modos' => self::MODOS_ENCARGO,
        ];
    }

    /** @return array{exigir_nsu_cartao: bool, exigir_autorizacao_cartao: bool, exigir_bandeira_cartao: bool, exigir_identificador_pix: bool} */
    public static function carregar(): array
    {
        if (! Schema::hasTable('pdv_configuracoes')) {
            return self::defaults();
        }

        $row = DB::table('pdv_configuracoes')->orderBy('id')->first();
        if (! $row) {
            return self::defaults();
        }

        return [
            'exigir_nsu_cartao' => (bool) ($row->exigir_nsu_cartao ?? false),
            'exigir_autorizacao_cartao' => (bool) ($row->exigir_autorizacao_cartao ?? false),
            'exigir_bandeira_cartao' => (bool) ($row->exigir_bandeira_cartao ?? false),
            'exigir_identificador_pix' => (bool) ($row->exigir_identificador_pix ?? false),
            'taxa_servico_ativa' => (bool) ($row->taxa_servico_ativa ?? false),
            'taxa_servico_modo' => self::normalizarModoEncargo($row->taxa_servico_modo ?? 'percentual'),
            'taxa_servico_valor' => (float) ($row->taxa_servico_valor ?? 10),
            'taxa_servico_padrao_mesa' => (bool) ($row->taxa_servico_padrao_mesa ?? true),
            'taxa_servico_padrao_balcao' => (bool) ($row->taxa_servico_padrao_balcao ?? true),
            'pagamento_cantor_ativo' => (bool) ($row->pagamento_cantor_ativo ?? false),
            'pagamento_cantor_modo' => self::normalizarModoEncargo($row->pagamento_cantor_modo ?? 'percentual'),
            'pagamento_cantor_valor' => (float) ($row->pagamento_cantor_valor ?? 0),
            'pagamento_cantor_padrao_mesa' => (bool) ($row->pagamento_cantor_padrao_mesa ?? true),
            'pagamento_cantor_padrao_balcao' => (bool) ($row->pagamento_cantor_padrao_balcao ?? true),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function salvar(array $data, ?int $usuarioId = null): array
    {
        if (! Schema::hasTable('pdv_configuracoes')) {
            throw new \RuntimeException('Configuração PDV indisponível (migração pendente).');
        }

        $payload = [
            'exigir_nsu_cartao' => ! empty($data['exigir_nsu_cartao']),
            'exigir_autorizacao_cartao' => ! empty($data['exigir_autorizacao_cartao']),
            'exigir_bandeira_cartao' => ! empty($data['exigir_bandeira_cartao']),
            'exigir_identificador_pix' => ! empty($data['exigir_identificador_pix']),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('pdv_configuracoes', 'taxa_servico_ativa')) {
            $payload['taxa_servico_ativa'] = ! empty($data['taxa_servico_ativa']);
            $payload['taxa_servico_modo'] = self::normalizarModoEncargo($data['taxa_servico_modo'] ?? 'percentual');
            $payload['taxa_servico_valor'] = max(0, round((float) ($data['taxa_servico_valor'] ?? 0), 2));
            $payload['taxa_servico_padrao_mesa'] = ! empty($data['taxa_servico_ativa']);
            $payload['taxa_servico_padrao_balcao'] = ! empty($data['taxa_servico_ativa']);
            $payload['pagamento_cantor_ativo'] = ! empty($data['pagamento_cantor_ativo']);
            $payload['pagamento_cantor_modo'] = self::normalizarModoEncargo($data['pagamento_cantor_modo'] ?? 'percentual');
            $payload['pagamento_cantor_valor'] = max(0, round((float) ($data['pagamento_cantor_valor'] ?? 0), 2));
            $payload['pagamento_cantor_padrao_mesa'] = ! empty($data['pagamento_cantor_ativo']);
            $payload['pagamento_cantor_padrao_balcao'] = ! empty($data['pagamento_cantor_ativo']);
        }
        if ($usuarioId > 0 && Schema::hasColumn('pdv_configuracoes', 'updated_by')) {
            $payload['updated_by'] = $usuarioId;
        }

        $row = DB::table('pdv_configuracoes')->orderBy('id')->first();
        if ($row) {
            DB::table('pdv_configuracoes')->where('id', $row->id)->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table('pdv_configuracoes')->insert($payload);
        }

        if (array_key_exists('bandeiras_cartao', $data)) {
            self::sincronizarBandeiras(is_array($data['bandeiras_cartao']) ? $data['bandeiras_cartao'] : []);
        }

        return self::opcoesPublicas(null);
    }

    /** @return list<array{id: int, nome: string}> */
    public static function listarBandeirasAtivas(): array
    {
        if (! Schema::hasTable('pdv_bandeiras_cartao')) {
            return [];
        }

        return DB::table('pdv_bandeiras_cartao')
            ->where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(static fn ($r) => ['id' => (int) $r->id, 'nome' => (string) $r->nome])
            ->all();
    }

    /** @param list<string> $nomes */
    public static function sincronizarBandeiras(array $nomes): void
    {
        if (! Schema::hasTable('pdv_bandeiras_cartao')) {
            throw new \RuntimeException('Cadastro de bandeiras indisponível (migração pendente).');
        }

        $limpos = [];
        foreach ($nomes as $nome) {
            $n = trim((string) $nome);
            if ($n === '') {
                continue;
            }
            $n = mb_substr($n, 0, 40);
            $key = mb_strtolower($n);
            if (! isset($limpos[$key])) {
                $limpos[$key] = $n;
            }
        }

        $existentes = DB::table('pdv_bandeiras_cartao')->get(['id', 'nome']);
        $porNome = [];
        foreach ($existentes as $row) {
            $porNome[mb_strtolower((string) $row->nome)] = (int) $row->id;
        }

        $ordem = 0;
        $mantidos = [];
        foreach ($limpos as $nome) {
            $key = mb_strtolower($nome);
            $ordem++;
            if (isset($porNome[$key])) {
                $id = $porNome[$key];
                DB::table('pdv_bandeiras_cartao')->where('id', $id)->update([
                    'nome' => $nome,
                    'ativo' => true,
                    'ordem' => $ordem,
                    'updated_at' => now(),
                ]);
                $mantidos[] = $id;
            } else {
                $mantidos[] = (int) DB::table('pdv_bandeiras_cartao')->insertGetId([
                    'nome' => $nome,
                    'ativo' => true,
                    'ordem' => $ordem,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if ($mantidos === []) {
            DB::table('pdv_bandeiras_cartao')->update(['ativo' => false, 'updated_at' => now()]);

            return;
        }

        DB::table('pdv_bandeiras_cartao')
            ->whereNotIn('id', $mantidos)
            ->update(['ativo' => false, 'updated_at' => now()]);
    }

    public static function isFormaCartao(string $forma): bool
    {
        $f = mb_strtolower(trim($forma));

        return str_contains($f, 'crédito') || str_contains($f, 'credito')
            || str_contains($f, 'débito') || str_contains($f, 'debito')
            || str_contains($f, 'cartão') || str_contains($f, 'cartao');
    }

    public static function isFormaPix(string $forma): bool
    {
        return mb_strtolower(trim($forma)) === 'pix';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function validarDadosPagamento(string $forma, array $payload, ?array $config = null): ?string
    {
        $cfg = $config ?? self::carregar();
        $forma = trim($forma);

        if (self::isFormaCartao($forma)) {
            if (! empty($cfg['exigir_nsu_cartao']) && trim((string) ($payload['pagamento_nsu'] ?? '')) === '') {
                return 'Informe o NSU do cartão (exigido pela configuração do PDV).';
            }
            if (! empty($cfg['exigir_autorizacao_cartao']) && trim((string) ($payload['pagamento_autorizacao'] ?? '')) === '') {
                return 'Informe o código de autorização do cartão (exigido pela configuração do PDV).';
            }
            $bandeira = trim((string) ($payload['pagamento_bandeira'] ?? ''));
            if (! empty($cfg['exigir_bandeira_cartao']) && $bandeira === '') {
                return 'Selecione a bandeira do cartão (exigida pela configuração do PDV).';
            }
            if ($bandeira !== '' && ! self::bandeiraPermitida($bandeira)) {
                return 'Bandeira do cartão inválida. Cadastre-a em Comercial → Configurações do PDV.';
            }
        }

        if (self::isFormaPix($forma) && ! empty($cfg['exigir_identificador_pix'])
            && trim((string) ($payload['pagamento_pix_id'] ?? '')) === '') {
            return 'Informe o identificador da transação PIX (exigido pela configuração do PDV).';
        }

        return null;
    }

    /** @param  array<string, mixed>  $payload */
    public static function extrairCamposPagamentoVenda(array $payload): array
    {
        $out = [];
        if (Schema::hasColumn('vendas', 'pagamento_nsu') && isset($payload['pagamento_nsu'])) {
            $v = trim((string) $payload['pagamento_nsu']);
            $out['pagamento_nsu'] = $v !== '' ? mb_substr($v, 0, 32) : null;
        }
        if (Schema::hasColumn('vendas', 'pagamento_autorizacao') && isset($payload['pagamento_autorizacao'])) {
            $v = trim((string) $payload['pagamento_autorizacao']);
            $out['pagamento_autorizacao'] = $v !== '' ? mb_substr($v, 0, 32) : null;
        }
        if (Schema::hasColumn('vendas', 'pagamento_bandeira') && isset($payload['pagamento_bandeira'])) {
            $v = trim((string) $payload['pagamento_bandeira']);
            $out['pagamento_bandeira'] = $v !== '' ? mb_substr($v, 0, 40) : null;
        }
        if (Schema::hasColumn('vendas', 'pagamento_parcelas') && isset($payload['pagamento_parcelas'])) {
            $p = (int) $payload['pagamento_parcelas'];
            $out['pagamento_parcelas'] = $p > 0 ? min(99, $p) : null;
        }
        if (Schema::hasColumn('vendas', 'pagamento_pix_id') && isset($payload['pagamento_pix_id'])) {
            $v = trim((string) $payload['pagamento_pix_id']);
            $out['pagamento_pix_id'] = $v !== '' ? mb_substr($v, 0, 120) : null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $flags
     * @return array{taxa_servico: float, pagamento_cantor: float}
     */
    public static function calcularEncargos(float $baseSubtotal, array $flags, ?array $config = null): array
    {
        $cfg = $config ?? self::carregar();
        $base = max(0, round($baseSubtotal, 2));
        $taxa = 0.0;
        $cantor = 0.0;

        if (! empty($flags['aplicar_taxa_servico']) && ! empty($cfg['taxa_servico_ativa'])) {
            $taxa = self::calcularValorEncargo(
                (string) ($cfg['taxa_servico_modo'] ?? 'percentual'),
                (float) ($cfg['taxa_servico_valor'] ?? 0),
                $base
            );
        }
        if (! empty($flags['aplicar_pagamento_cantor']) && ! empty($cfg['pagamento_cantor_ativo'])) {
            $cantor = self::calcularValorEncargo(
                (string) ($cfg['pagamento_cantor_modo'] ?? 'percentual'),
                (float) ($cfg['pagamento_cantor_valor'] ?? 0),
                $base
            );
        }

        return [
            'taxa_servico' => round($taxa, 2),
            'pagamento_cantor' => round($cantor, 2),
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public static function extrairEncargosVenda(float $baseSubtotal, array $payload, ?array $config = null): array
    {
        $flags = [
            'aplicar_taxa_servico' => ! empty($payload['aplicar_taxa_servico']),
            'aplicar_pagamento_cantor' => ! empty($payload['aplicar_pagamento_cantor']),
        ];
        $calc = self::calcularEncargos($baseSubtotal, $flags, $config);
        $out = [];
        if (Schema::hasColumn('vendas', 'taxa_servico')) {
            $out['taxa_servico'] = $calc['taxa_servico'];
        }
        if (Schema::hasColumn('vendas', 'pagamento_cantor')) {
            $out['pagamento_cantor'] = $calc['pagamento_cantor'];
        }

        return $out;
    }

    public static function calcularValorEncargo(string $modo, float $valor, float $base): float
    {
        $valor = max(0, $valor);
        if (self::normalizarModoEncargo($modo) === 'percentual') {
            return round($base * $valor / 100, 2);
        }

        return round($valor, 2);
    }

    public static function normalizarModoEncargo(?string $modo): string
    {
        $m = mb_strtolower(trim((string) $modo));

        return in_array($m, self::MODOS_ENCARGO, true) ? $m : 'percentual';
    }

    public static function rotuloEncargo(string $modo, float $valor): string
    {
        if (self::normalizarModoEncargo($modo) === 'percentual') {
            return rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',') . '%';
        }

        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    public static function usuarioPodeEditar(?object $usuario): bool
    {
        if (! $usuario) {
            return false;
        }
        $p = strtoupper(trim((string) ($usuario->perfil ?? '')));

        return in_array($p, ['ADMIN', 'ADMINISTRADOR', 'GERENTE'], true);
    }

    public static function bandeiraPermitida(string $nome): bool
    {
        $nome = trim($nome);
        if ($nome === '') {
            return false;
        }

        foreach (self::listarBandeirasAtivas() as $b) {
            if (mb_strtolower($b['nome']) === mb_strtolower($nome)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{exigir_nsu_cartao: bool, exigir_autorizacao_cartao: bool, exigir_bandeira_cartao: bool, exigir_identificador_pix: bool} */
    private static function defaults(): array
    {
        return [
            'exigir_nsu_cartao' => false,
            'exigir_autorizacao_cartao' => false,
            'exigir_bandeira_cartao' => false,
            'exigir_identificador_pix' => false,
            'taxa_servico_ativa' => false,
            'taxa_servico_modo' => 'percentual',
            'taxa_servico_valor' => 10.0,
            'taxa_servico_padrao_mesa' => true,
            'taxa_servico_padrao_balcao' => true,
            'pagamento_cantor_ativo' => false,
            'pagamento_cantor_modo' => 'percentual',
            'pagamento_cantor_valor' => 0.0,
            'pagamento_cantor_padrao_mesa' => true,
            'pagamento_cantor_padrao_balcao' => true,
        ];
    }
}
