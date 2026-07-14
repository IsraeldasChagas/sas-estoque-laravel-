<?php

namespace App\Support\Ayla;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leitura centralizada das configurações da Ayla.
 * O token nunca é logado nem retornado em respostas.
 *
 * Fontes:
 *  - config/ayla.php (via .env) para padrões de API;
 *  - sistema_configuracoes para o painel administrativo (chaves ayla_*).
 */
final class AylaSettings
{
    /** Chaves editáveis pelo painel administrativo (nunca inclui o token). */
    public const CHAVES_PAINEL = [
        'ayla_ativa',
        'ayla_api_url',
        'ayla_gateway_url',
        'ayla_read_only',
        'ayla_rate_limit',
        'ayla_unidades_globais',
        'ayla_msg_nao_autorizado',
        'ayla_msg_boas_vindas',
        'ayla_telegram_ativo',
        'ayla_telegram_bot_username',
        'ayla_audio_ativo',
        'ayla_audio_provider',
        'ayla_audio_voice',
        'ayla_audio_inbound_only',
        'ayla_ultimo_teste_em',
        'ayla_ultimo_status',
    ];

    /** Chave secreta do token (armazenada em separado, nunca retornada). */
    public const CHAVE_TOKEN = 'ayla_sas_token';

    // ------------------------------------------------------------------
    // API (config/ayla.php + override de painel)
    // ------------------------------------------------------------------

    public static function ativo(): bool
    {
        $db = self::lerChave('ayla_ativa');
        if ($db !== null && $db !== '') {
            return in_array($db, ['1', 'true', 'sim', 'on'], true);
        }

        return (bool) config('ayla.enabled', true);
    }

    public static function somenteLeitura(): bool
    {
        $db = self::lerChave('ayla_read_only');
        if ($db !== null && $db !== '') {
            return in_array($db, ['1', 'true', 'sim', 'on'], true);
        }

        return (bool) config('ayla.read_only', true);
    }

    public static function rateLimit(): int
    {
        $db = self::lerChave('ayla_rate_limit');
        if ($db !== null && $db !== '' && ctype_digit((string) $db)) {
            return max(1, (int) $db);
        }

        return max(1, (int) config('ayla.rate_limit', 60));
    }

    public static function versao(): string
    {
        return (string) config('ayla.version', 'v1');
    }

    /** @return int[] */
    public static function unidadesPermitidas(): array
    {
        $db = self::lerChave('ayla_unidades_globais');
        if ($db !== null && $db !== '') {
            $arr = json_decode($db, true);
            if (is_array($arr)) {
                return array_values(array_filter(array_map('intval', $arr), fn ($v) => $v > 0));
            }
        }

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

        return (string) (self::lerChave(self::CHAVE_TOKEN) ?? '');
    }

    public static function tokenConfigurado(): bool
    {
        return self::tokenEfetivo() !== '';
    }

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
            'dashboard', 'unidades', 'produtos', 'estoque', 'movimentacoes',
            'lotes', 'compras', 'fornecedores', 'relatorios', 'kanban', 'patrimonio', 'reservas',
        ];
    }

    // ------------------------------------------------------------------
    // Token: geração e máscara (painel administrativo)
    // ------------------------------------------------------------------

    public static function gerarToken(): string
    {
        return 'ayla_sas_'.bin2hex(random_bytes(24));
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

        return substr($token, 0, 6).'••••'.substr($token, -4);
    }

    // ------------------------------------------------------------------
    // Persistência (sistema_configuracoes)
    // ------------------------------------------------------------------

    public static function lerChave(string $chave): ?string
    {
        try {
            if (! Schema::hasTable('sistema_configuracoes')) {
                return null;
            }
            $valor = DB::table('sistema_configuracoes')->where('chave', $chave)->value('valor');

            return $valor === null ? null : (string) $valor;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Salva uma chave do painel (o token usa chave própria e permitida). */
    public static function salvarChave(string $chave, string $valor): bool
    {
        if (! in_array($chave, self::CHAVES_PAINEL, true) && $chave !== self::CHAVE_TOKEN) {
            return false;
        }
        try {
            if (! Schema::hasTable('sistema_configuracoes')) {
                return false;
            }
            $existe = DB::table('sistema_configuracoes')->where('chave', $chave)->exists();
            if ($existe) {
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

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array<string, string> */
    public static function lerPainel(): array
    {
        $defaults = [
            'ayla_ativa' => config('ayla.enabled', true) ? '1' : '0',
            'ayla_api_url' => rtrim((string) config('app.url'), '/').'/api/ayla/v1',
            'ayla_gateway_url' => '',
            'ayla_read_only' => config('ayla.read_only', true) ? '1' : '0',
            'ayla_rate_limit' => (string) config('ayla.rate_limit', 60),
            'ayla_unidades_globais' => json_encode(self::unidadesPermitidas()),
            'ayla_msg_nao_autorizado' => 'Desculpe, você ainda não tem acesso à Ayla. Fale com o administrador.',
            'ayla_msg_boas_vindas' => 'Olá! Eu sou a Ayla, assistente do Grupo Sabor Paraense. Como posso ajudar?',
            'ayla_telegram_ativo' => '0',
            'ayla_telegram_bot_username' => '',
            'ayla_audio_ativo' => '1',
            'ayla_audio_provider' => 'openai',
            'ayla_audio_voice' => 'alloy',
            'ayla_audio_inbound_only' => '1',
            'ayla_ultimo_teste_em' => '',
            'ayla_ultimo_status' => '',
        ];

        $out = [];
        foreach (self::CHAVES_PAINEL as $chave) {
            $out[$chave] = self::lerChave($chave) ?? ($defaults[$chave] ?? '');
        }

        return $out;
    }

    /**
     * Representação segura para o frontend (token sempre mascarado).
     *
     * @return array<string, mixed>
     */
    public static function paraPainel(): array
    {
        $cfg = self::lerPainel();
        $token = self::tokenEfetivo();

        return [
            'ativa' => self::ativo(),
            'read_only' => self::somenteLeitura(),
            'versao' => self::versao(),
            'rate_limit' => self::rateLimit(),
            'api_url' => $cfg['ayla_api_url'],
            'gateway_url' => $cfg['ayla_gateway_url'],
            'unidades_globais' => self::unidadesPermitidas(),
            'msg_nao_autorizado' => $cfg['ayla_msg_nao_autorizado'],
            'msg_boas_vindas' => $cfg['ayla_msg_boas_vindas'],
            'telegram_ativo' => in_array($cfg['ayla_telegram_ativo'], ['1', 'true'], true),
            'telegram_bot_username' => $cfg['ayla_telegram_bot_username'],
            'audio_ativo' => in_array($cfg['ayla_audio_ativo'], ['1', 'true'], true),
            'audio_provider' => $cfg['ayla_audio_provider'],
            'audio_voice' => $cfg['ayla_audio_voice'],
            'audio_inbound_only' => in_array($cfg['ayla_audio_inbound_only'], ['1', 'true'], true),
            'ultimo_teste_em' => $cfg['ayla_ultimo_teste_em'],
            'ultimo_status' => $cfg['ayla_ultimo_status'],
            'token_mascarado' => self::mascararToken($token),
            'token_configurado' => $token !== '',
            'token_origem' => trim((string) config('ayla.token', '')) !== '' ? 'env' : 'painel',
        ];
    }
}
