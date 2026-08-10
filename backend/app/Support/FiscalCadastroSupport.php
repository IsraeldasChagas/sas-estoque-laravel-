<?php

namespace App\Support;

final class FiscalCadastroSupport
{
    public const TIPOS_FISCAIS = [
        'producao_propria',
        'revenda',
        'insumo',
        'uso_consumo',
    ];

    public const REGIMES_TRIBUTARIOS = [
        'simples_nacional',
        'lucro_presumido',
        'lucro_real',
        'outro',
    ];

    /** Origem da mercadoria (códigos oficiais 0–8). */
    public const ORIGENS_MERCADORIA = ['0', '1', '2', '3', '4', '5', '6', '7', '8'];

    public static function normalizarCnpj(?string $cnpj): ?string
    {
        if ($cnpj === null || trim($cnpj) === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $cnpj);

        return strlen($digits) === 14 ? $digits : null;
    }

    public static function normalizarNcm(?string $ncm): ?string
    {
        if ($ncm === null || trim($ncm) === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $ncm);
        if (strlen($digits) !== 8) {
            return null;
        }

        return $digits;
    }

    public static function normalizarCest(?string $cest): ?string
    {
        if ($cest === null || trim($cest) === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $cest);
        if ($digits === '' || strlen($digits) > 7) {
            return null;
        }

        return str_pad($digits, 7, '0', STR_PAD_LEFT);
    }

    public static function normalizarCfop(?string $cfop): ?string
    {
        if ($cfop === null || trim($cfop) === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $cfop);

        return strlen($digits) === 4 ? $digits : null;
    }

    public static function normalizarCst(?string $cst): ?string
    {
        if ($cst === null || trim($cst) === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $cst);
        if ($digits === '' || strlen($digits) > 3) {
            return null;
        }

        return str_pad($digits, 3, '0', STR_PAD_LEFT);
    }

    public static function normalizarCsosn(?string $csosn): ?string
    {
        if ($csosn === null || trim($csosn) === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $csosn);
        if ($digits === '') {
            return null;
        }
        // CSOSN oficial tem 3 dígitos (ex.: 102, 500). "0500" / "0102" → remove zero à esquerda.
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) > 3) {
            return null;
        }
        $code = str_pad($digits, 3, '0', STR_PAD_LEFT);
        $validos = ['101', '102', '103', '201', '202', '203', '300', '400', '500', '900'];
        if (! in_array($code, $validos, true)) {
            return null;
        }

        return $code;
    }

    public static function normalizarBool($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        $s = strtolower(trim((string) $value));

        return in_array($s, ['1', 'true', 'sim', 'yes', 's'], true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizarCamposProdutoFiscal(array $data): array
    {
        $out = [];
        if (array_key_exists('tipo_fiscal', $data)) {
            $tf = strtolower(trim((string) ($data['tipo_fiscal'] ?? '')));
            $out['tipo_fiscal'] = $tf !== '' && in_array($tf, self::TIPOS_FISCAIS, true) ? $tf : null;
        }
        if (array_key_exists('perfil_tributario_id', $data)) {
            $pid = $data['perfil_tributario_id'];
            $out['perfil_tributario_id'] = ($pid !== null && $pid !== '') ? (int) $pid : null;
        }
        if (array_key_exists('ncm', $data)) {
            $out['ncm'] = self::normalizarNcm($data['ncm'] ?? null);
        }
        if (array_key_exists('cest', $data)) {
            $out['cest'] = self::normalizarCest($data['cest'] ?? null);
        }
        if (array_key_exists('origem_mercadoria', $data)) {
            $o = trim((string) ($data['origem_mercadoria'] ?? ''));
            $out['origem_mercadoria'] = in_array($o, self::ORIGENS_MERCADORIA, true) ? $o : null;
        }
        foreach (['cst_icms' => 'normalizarCst', 'csosn' => 'normalizarCsosn'] as $k => $method) {
            if (array_key_exists($k, $data)) {
                $out[$k] = self::$method($data[$k] ?? null);
            }
        }
        foreach (['cfop_entrada_padrao', 'cfop_saida_padrao'] as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = self::normalizarCfop($data[$k] ?? null);
            }
        }
        foreach ([
            'tratamento_icms', 'tratamento_pis', 'tratamento_cofins', 'tratamento_ipi',
            'tratamento_cbs', 'tratamento_ibs',
        ] as $k) {
            if (array_key_exists($k, $data)) {
                $v = trim((string) ($data[$k] ?? ''));
                $out[$k] = $v !== '' ? mb_substr($v, 0, 80) : null;
            }
        }
        foreach ([
            'monofasico', 'substituicao_tributaria', 'gera_credito_icms',
            'gera_credito_pis', 'gera_credito_cofins',
        ] as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = self::normalizarBool($data[$k]);
            }
        }
        if (array_key_exists('observacao_fiscal', $data)) {
            $obs = trim((string) ($data['observacao_fiscal'] ?? ''));
            $out['observacao_fiscal'] = $obs !== '' ? $obs : null;
        }

        return $out;
    }

    /**
     * @param  object|array<string, mixed>  $produto
     */
    public static function statusFiscalProduto($produto): string
    {
        $p = is_array($produto) ? $produto : (array) $produto;
        $tipo = trim((string) ($p['tipo_fiscal'] ?? ''));
        if ($tipo === '') {
            return 'pendente';
        }
        $ncm = trim((string) ($p['ncm'] ?? ''));
        $origem = trim((string) ($p['origem_mercadoria'] ?? ''));
        $temNcm = $ncm !== '' && strlen(preg_replace('/\D/', '', $ncm)) === 8;
        $temOrigem = $origem !== '';
        if ($temNcm && $temOrigem) {
            return 'completo';
        }

        return 'incompleto';
    }

    public static function labelTipoFiscal(?string $tipo): string
    {
        return match ($tipo) {
            'producao_propria' => 'Produção própria',
            'revenda' => 'Revenda',
            'insumo' => 'Insumo',
            'uso_consumo' => 'Uso e consumo',
            default => '—',
        };
    }

    public static function labelStatusFiscal(string $status): string
    {
        return match ($status) {
            'completo' => 'Completo',
            'incompleto' => 'Incompleto',
            default => 'Pendente',
        };
    }

    /**
     * @param  object  $perfil
     * @return array<string, mixed>
     */
    public static function camposSugeridosDoPerfil(object $perfil): array
    {
        return array_filter([
            'tipo_fiscal' => $perfil->tipo_fiscal_padrao ?? null,
            'ncm' => $perfil->ncm_padrao ?? null,
            'cest' => $perfil->cest_padrao ?? null,
            'cst_icms' => $perfil->cst_icms ?? null,
            'csosn' => $perfil->csosn ?? null,
            'cfop_entrada_padrao' => $perfil->cfop_entrada_padrao ?? null,
            'cfop_saida_padrao' => $perfil->cfop_saida_padrao ?? null,
            'tratamento_icms' => $perfil->tratamento_icms ?? null,
            'tratamento_pis' => $perfil->tratamento_pis ?? null,
            'tratamento_cofins' => $perfil->tratamento_cofins ?? null,
            'tratamento_ipi' => $perfil->tratamento_ipi ?? null,
            'tratamento_cbs' => $perfil->tratamento_cbs ?? null,
            'tratamento_ibs' => $perfil->tratamento_ibs ?? null,
            'monofasico' => isset($perfil->monofasico) ? (bool) $perfil->monofasico : null,
            'substituicao_tributaria' => isset($perfil->substituicao_tributaria) ? (bool) $perfil->substituicao_tributaria : null,
            'gera_credito_icms' => isset($perfil->gera_credito_icms) ? (bool) $perfil->gera_credito_icms : null,
            'gera_credito_pis' => isset($perfil->gera_credito_pis) ? (bool) $perfil->gera_credito_pis : null,
            'gera_credito_cofins' => isset($perfil->gera_credito_cofins) ? (bool) $perfil->gera_credito_cofins : null,
        ], static fn ($v) => $v !== null && $v !== '');
    }
}
