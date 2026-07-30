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
            'chaves_pix' => self::listarChavesPix(true),
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

        // A tela salva segurança e encargos em formulários separados. Atualize
        // somente as chaves enviadas para um formulário não zerar o outro.
        $payload = ['updated_at' => now()];
        foreach ([
            'exigir_nsu_cartao',
            'exigir_autorizacao_cartao',
            'exigir_bandeira_cartao',
            'exigir_identificador_pix',
        ] as $campo) {
            if (array_key_exists($campo, $data)) {
                $payload[$campo] = ! empty($data[$campo]);
            }
        }

        if (Schema::hasColumn('pdv_configuracoes', 'taxa_servico_ativa')) {
            foreach ([
                'taxa_servico_ativa',
                'taxa_servico_padrao_mesa',
                'taxa_servico_padrao_balcao',
                'pagamento_cantor_ativo',
                'pagamento_cantor_padrao_mesa',
                'pagamento_cantor_padrao_balcao',
            ] as $campo) {
                if (array_key_exists($campo, $data)) {
                    $payload[$campo] = ! empty($data[$campo]);
                }
            }
            foreach (['taxa_servico_modo', 'pagamento_cantor_modo'] as $campo) {
                if (array_key_exists($campo, $data)) {
                    $payload[$campo] = self::normalizarModoEncargo($data[$campo]);
                }
            }
            foreach (['taxa_servico_valor', 'pagamento_cantor_valor'] as $campo) {
                if (array_key_exists($campo, $data)) {
                    $payload[$campo] = max(0, round((float) $data[$campo], 2));
                }
            }
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
        if (array_key_exists('chaves_pix', $data)) {
            self::sincronizarChavesPix(is_array($data['chaves_pix']) ? $data['chaves_pix'] : []);
        }

        return self::opcoesPublicas(null);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarChavesPix(bool $somenteAtivas = true): array
    {
        if (! Schema::hasTable('pdv_chaves_pix')) {
            return [];
        }

        $q = DB::table('pdv_chaves_pix')->orderByDesc('padrao')->orderBy('ordem')->orderBy('id');
        if ($somenteAtivas) {
            $q->where('ativo', true);
        }

        return $q->get()->map(static function ($r) {
            return [
                'id' => (int) $r->id,
                'apelido' => $r->apelido ? (string) $r->apelido : null,
                'tipo_pessoa' => (string) ($r->tipo_pessoa ?: 'pj'),
                'tipo_chave' => (string) $r->tipo_chave,
                'chave' => (string) $r->chave,
                'beneficiario' => (string) $r->beneficiario,
                'cidade' => (string) ($r->cidade ?: 'BELEM'),
                'documento' => $r->documento ? (string) $r->documento : null,
                'padrao' => (bool) $r->padrao,
                'ativo' => (bool) $r->ativo,
                'rotulo' => self::rotuloChavePix($r),
            ];
        })->all();
    }

    public static function rotuloChavePix(object $r): string
    {
        $pessoa = strtoupper((string) ($r->tipo_pessoa ?: 'pj'));
        $apelido = trim((string) ($r->apelido ?: $r->beneficiario ?: 'PIX'));
        $tipo = strtoupper((string) $r->tipo_chave);
        $chave = (string) $r->chave;
        $mascara = mb_strlen($chave) > 18 ? mb_substr($chave, 0, 10).'…'.mb_substr($chave, -4) : $chave;

        return trim("{$apelido} ({$pessoa} · {$tipo} · {$mascara})");
    }

    /** @param list<array<string, mixed>> $itens */
    public static function sincronizarChavesPix(array $itens): void
    {
        if (! Schema::hasTable('pdv_chaves_pix')) {
            throw new \RuntimeException('Cadastro de chaves PIX indisponível (migração pendente).');
        }

        $mantidos = [];
        $ordem = 0;
        $temPadrao = false;

        foreach ($itens as $item) {
            if (! is_array($item)) {
                continue;
            }
            $tipoPessoa = mb_strtolower(trim((string) ($item['tipo_pessoa'] ?? 'pj')));
            if (! in_array($tipoPessoa, PdvPixEmvSupport::TIPOS_PESSOA, true)) {
                $tipoPessoa = 'pj';
            }
            $tipoChave = mb_strtolower(trim((string) ($item['tipo_chave'] ?? '')));
            if (! in_array($tipoChave, PdvPixEmvSupport::TIPOS_CHAVE, true)) {
                throw new \InvalidArgumentException('Tipo de chave PIX inválido.');
            }
            $chave = PdvPixEmvSupport::normalizarChave((string) ($item['chave'] ?? ''), $tipoChave);
            $beneficiario = trim((string) ($item['beneficiario'] ?? ''));
            if ($chave === '' || $beneficiario === '') {
                continue;
            }
            $ordem++;
            $padrao = ! empty($item['padrao']) && ! $temPadrao;
            if ($padrao) {
                $temPadrao = true;
            }
            $payload = [
                'apelido' => ($a = trim((string) ($item['apelido'] ?? ''))) !== '' ? mb_substr($a, 0, 80) : null,
                'tipo_pessoa' => $tipoPessoa,
                'tipo_chave' => $tipoChave,
                'chave' => mb_substr($chave, 0, 180),
                'beneficiario' => mb_substr($beneficiario, 0, 160),
                'cidade' => mb_substr(trim((string) ($item['cidade'] ?? 'BELEM')) ?: 'BELEM', 0, 40),
                'documento' => ($d = preg_replace('/\D+/', '', (string) ($item['documento'] ?? ''))) !== '' ? mb_substr($d, 0, 20) : null,
                'ativo' => true,
                'padrao' => $padrao,
                'ordem' => $ordem,
                'updated_at' => now(),
            ];

            $id = (int) ($item['id'] ?? 0);
            if ($id > 0 && DB::table('pdv_chaves_pix')->where('id', $id)->exists()) {
                DB::table('pdv_chaves_pix')->where('id', $id)->update($payload);
                $mantidos[] = $id;
            } else {
                $payload['created_at'] = now();
                $mantidos[] = (int) DB::table('pdv_chaves_pix')->insertGetId($payload);
            }
        }

        if ($mantidos === []) {
            DB::table('pdv_chaves_pix')->update(['ativo' => false, 'padrao' => false, 'updated_at' => now()]);

            return;
        }

        DB::table('pdv_chaves_pix')
            ->whereNotIn('id', $mantidos)
            ->update(['ativo' => false, 'padrao' => false, 'updated_at' => now()]);

        if (! $temPadrao) {
            $primeiro = $mantidos[0];
            DB::table('pdv_chaves_pix')->where('id', $primeiro)->update(['padrao' => true, 'updated_at' => now()]);
        }
    }

    /**
     * @return array{chave:array<string,mixed>, payload:string, qr_data_uri:?string, valor:float}
     */
    public static function gerarQrPix(?int $chaveId, float $valor, ?string $txid = null): array
    {
        if (! Schema::hasTable('pdv_chaves_pix')) {
            throw new \RuntimeException('Cadastro de chaves PIX indisponível (migração pendente).');
        }

        $q = DB::table('pdv_chaves_pix')->where('ativo', true);
        if ($chaveId && $chaveId > 0) {
            $row = $q->where('id', $chaveId)->first();
        } else {
            $row = (clone $q)->where('padrao', true)->orderBy('ordem')->first()
                ?: $q->orderByDesc('padrao')->orderBy('ordem')->orderBy('id')->first();
        }
        if (! $row) {
            throw new \RuntimeException('Nenhuma chave PIX cadastrada. Cadastre em Configurações do PDV.');
        }

        $valor = max(0, round($valor, 2));
        $payload = PdvPixEmvSupport::montarPayload([
            'chave' => (string) $row->chave,
            'tipo_chave' => (string) $row->tipo_chave,
            'beneficiario' => (string) $row->beneficiario,
            'cidade' => (string) ($row->cidade ?: 'BELEM'),
            'txid' => $txid ?: ('PDV'.now()->format('YmdHis')),
        ], $valor);

        $qr = null;
        if (class_exists(\App\Support\Delivery\GeradorQrCodePix::class)) {
            $qr = \App\Support\Delivery\GeradorQrCodePix::dataUriSvg($payload);
        }

        return [
            'chave' => [
                'id' => (int) $row->id,
                'apelido' => $row->apelido ? (string) $row->apelido : null,
                'tipo_pessoa' => (string) $row->tipo_pessoa,
                'tipo_chave' => (string) $row->tipo_chave,
                'chave' => (string) $row->chave,
                'beneficiario' => (string) $row->beneficiario,
                'cidade' => (string) ($row->cidade ?: 'BELEM'),
                'rotulo' => self::rotuloChavePix($row),
            ],
            'payload' => $payload,
            'qr_data_uri' => $qr,
            'valor' => $valor,
        ];
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
        if (Schema::hasColumn('vendas', 'pagamento_pix_chave_id') && isset($payload['pagamento_pix_chave_id'])) {
            $id = (int) $payload['pagamento_pix_chave_id'];
            $out['pagamento_pix_chave_id'] = $id > 0 ? $id : null;
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
