<?php

namespace App\Support\Ayla;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leitura centralizada das configurações da Ayla.
 * O token nunca é logado nem retornado em respostas.
 */
final class AylaSettings
{
    public static function ativo(): bool
    {
        return (bool) config('ayla.enabled', true);
    }

    public static function somenteLeitura(): bool
    {
        return (bool) config('ayla.read_only', true);
    }

    public static function rateLimit(): int
    {
        return max(1, (int) config('ayla.rate_limit', 60));
    }

    public static function versao(): string
    {
        return (string) config('ayla.version', 'v1');
    }

    /** @return int[] */
    public static function unidadesPermitidas(): array
    {
        return array_values(array_filter(array_map('intval', (array) config('ayla.allowed_units', []))));
    }

    /**
     * Token efetivo: primeiro o .env; fallback opcional em sistema_configuracoes.
     * Envolto em try/catch para nunca quebrar a requisição por falha de banco.
     */
    public static function tokenEfetivo(): string
    {
        $env = trim((string) config('ayla.token', ''));
        if ($env !== '') {
            return $env;
        }

        try {
            if (Schema::hasTable('sistema_configuracoes')) {
                $valor = DB::table('sistema_configuracoes')->where('chave', 'ayla_sas_token')->value('valor');

                return trim((string) $valor);
            }
        } catch (\Throwable $e) {
            // Ignora: token simplesmente fica vazio (retorna 503 na autenticação).
        }

        return '';
    }

    /**
     * Verifica se a unidade solicitada é permitida (modo sem usuário identificado).
     * Sem lista configurada => todas liberadas. Sem unidade informada => permitido.
     */
    public static function unidadePermitida(?int $unidadeId): bool
    {
        $permitidas = self::unidadesPermitidas();
        if ($permitidas === []) {
            return true;
        }
        if ($unidadeId === null || $unidadeId < 1) {
            return true;
        }

        return in_array($unidadeId, $permitidas, true);
    }

    /** @return string[] */
    public static function modulosLiberados(): array
    {
        return [
            'unidades',
            'produtos',
            'estoque',
            'movimentacoes',
            'lotes',
            'compras',
            'fornecedores',
            'dashboard',
            'relatorios',
        ];
    }
}
