<?php

namespace App\Support\OpenClaw;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações da integração OpenClaw (sistema_configuracoes + .env).
 */
final class OpenClawSettings
{
    public const CHAVES = [
        'openclaw_ativo',
        'openclaw_sas_token',
        'openclaw_url',
        'openclaw_unidades_permitidas',
        'openclaw_acoes_permitidas',
    ];

    /** Ações disponíveis nesta fase (somente estoque). */
    public const ACOES_DISPONIVEIS = [
        'estoque_baixo' => 'Consultar estoque abaixo do mínimo',
        'produtos_vencendo' => 'Consultar produtos/lotes vencendo',
        'produto' => 'Consultar produto por nome ou ID',
        'relatorio_unidade' => 'Relatório resumido da unidade',
        'lancar_perda' => 'Lançar perda de estoque (exige confirmação)',
        'cadastrar_compra' => 'Cadastrar lista de compra (exige confirmação)',
    ];

    /** Ações bloqueadas nesta fase. */
    public const ACOES_BLOQUEADAS = [
        'excluir_produto',
        'financeiro',
        'excluir',
        'deletar',
    ];

    public static function defaults(): array
    {
        return [
            'openclaw_ativo' => '0',
            'openclaw_sas_token' => '',
            'openclaw_url' => '',
            'openclaw_unidades_permitidas' => '[]',
            'openclaw_acoes_permitidas' => json_encode(array_keys(self::ACOES_DISPONIVEIS)),
        ];
    }

    public static function ler(): array
    {
        $defaults = self::defaults();
        if (! Schema::hasTable('sistema_configuracoes')) {
            return $defaults;
        }

        $rows = DB::table('sistema_configuracoes')
            ->whereIn('chave', self::CHAVES)
            ->pluck('valor', 'chave');

        $out = [];
        foreach (self::CHAVES as $k) {
            $out[$k] = (string) ($rows[$k] ?? $defaults[$k] ?? '');
        }

        return $out;
    }

    public static function salvarChave(string $chave, string $valor): void
    {
        if (! Schema::hasTable('sistema_configuracoes') || ! in_array($chave, self::CHAVES, true)) {
            return;
        }

        $exists = DB::table('sistema_configuracoes')->where('chave', $chave)->exists();
        if ($exists) {
            DB::table('sistema_configuracoes')->where('chave', $chave)->update([
                'valor' => $valor,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('sistema_configuracoes')->insert([
                'chave' => $chave,
                'valor' => $valor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public static function ativo(): bool
    {
        $cfg = self::ler();

        return in_array($cfg['openclaw_ativo'], ['1', 'true', 'sim', 'on'], true);
    }

    public static function tokenEfetivo(): string
    {
        $db = trim(self::ler()['openclaw_sas_token'] ?? '');
        if ($db !== '') {
            return $db;
        }

        return trim((string) config('openclaw.sas_token', ''));
    }

    public static function tokenConfigurado(): bool
    {
        return self::tokenEfetivo() !== '';
    }

    public static function mascararToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }
        if (strlen($token) <= 8) {
            return str_repeat('•', strlen($token));
        }

        return substr($token, 0, 4).'••••'.substr($token, -4);
    }

    /** @return int[] */
    public static function unidadesPermitidas(): array
    {
        $raw = self::ler()['openclaw_unidades_permitidas'] ?? '[]';
        $arr = json_decode($raw, true);

        if (! is_array($arr)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $arr), fn ($id) => $id > 0));
    }

    /** @return string[] */
    public static function acoesPermitidas(): array
    {
        $raw = self::ler()['openclaw_acoes_permitidas'] ?? '[]';
        $arr = json_decode($raw, true);
        if (! is_array($arr) || $arr === []) {
            return array_keys(self::ACOES_DISPONIVEIS);
        }

        return array_values(array_intersect(
            array_keys(self::ACOES_DISPONIVEIS),
            array_map('strval', $arr)
        ));
    }

    public static function acaoPermitida(string $acao): bool
    {
        $acao = strtolower(trim($acao));
        if (in_array($acao, self::ACOES_BLOQUEADAS, true)) {
            return false;
        }

        return in_array($acao, self::acoesPermitidas(), true);
    }

    public static function unidadePermitida(?int $unidadeId): bool
    {
        $permitidas = self::unidadesPermitidas();
        if ($permitidas === []) {
            return true;
        }
        if ($unidadeId === null || $unidadeId <= 0) {
            return false;
        }

        return in_array($unidadeId, $permitidas, true);
    }

    public static function gerarToken(): string
    {
        return 'oc_sas_'.bin2hex(random_bytes(24));
    }

    /** @return array<string, mixed> */
    public static function paraPainel(): array
    {
        $cfg = self::ler();
        $token = self::tokenEfetivo();

        return [
            'ativo' => self::ativo(),
            'token_mascarado' => self::mascararToken($token),
            'token_configurado' => $token !== '',
            'url' => $cfg['openclaw_url'],
            'unidades_permitidas' => self::unidadesPermitidas(),
            'acoes_permitidas' => self::acoesPermitidas(),
            'acoes_disponiveis' => self::ACOES_DISPONIVEIS,
            'api_base' => rtrim((string) config('app.url'), '/').'/api/ia',
        ];
    }
}
