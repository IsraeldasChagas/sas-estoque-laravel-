<?php

namespace App\Support;

use App\Models\FiscalEmissaoConfig;

final class FiscalEmissaoConfigSupport
{
    public const PROVIDERS = [
        'focus_nfe' => 'Focus NFe (API)',
        'nfe_io' => 'NFe.io (API)',
        'tecnospeed' => 'TecnoSpeed (API)',
        'certificado_a1' => 'Certificado A1 (emissor direto — próxima fase)',
        'vendafacil' => 'VendaFácil (via integração)',
    ];

    public const ENVIRONMENTS = [
        'homologation' => 'Homologação (SEFAZ testes)',
        'production' => 'Produção',
    ];

    /**
     * @param  array<string, mixed>|null  $empresa  linha empresas (cnpj, regime, ie, uf…)
     * @return array{pronto: bool, status: string, itens: list<array{ok: bool, label: string, hint?: string}>}
     */
    public static function avaliarProntidao(?FiscalEmissaoConfig $config, ?array $empresa): array
    {
        $itens = [];

        $cnpj = preg_replace('/\D/', '', (string) ($empresa['cnpj'] ?? ''));
        $itens[] = [
            'ok' => strlen($cnpj) === 14,
            'label' => 'CNPJ da empresa cadastrado',
            'hint' => 'Configurações → Empresas (CNPJ)',
        ];
        $itens[] = [
            'ok' => ! empty($empresa['regime_tributario'] ?? null),
            'label' => 'Regime tributário da empresa',
        ];
        $itens[] = [
            'ok' => ! empty($empresa['inscricao_estadual'] ?? null),
            'label' => 'Inscrição estadual (IE)',
        ];
        $itens[] = [
            'ok' => ! empty($empresa['uf'] ?? null),
            'label' => 'UF da empresa',
        ];

        if (! $config) {
            $itens[] = ['ok' => false, 'label' => 'Configuração de emissão salva'];
            $itens[] = ['ok' => false, 'label' => 'Provedor e credenciais'];

            return self::montarResultado($itens, 'not_configured');
        }

        $itens[] = ['ok' => true, 'label' => 'Configuração de emissão salva'];

        $provider = (string) ($config->provider ?? '');
        $itens[] = [
            'ok' => $provider !== '',
            'label' => 'Provedor de emissão selecionado',
        ];

        $usaApi = in_array($provider, ['focus_nfe', 'nfe_io', 'tecnospeed', 'vendafacil'], true);
        $usaCert = $provider === 'certificado_a1';

        if ($usaApi) {
            $tokenOk = $config->api_token !== null && $config->api_token !== '';
            $itens[] = [
                'ok' => $tokenOk,
                'label' => 'Token / API key do emissor',
            ];
            if ($provider !== 'vendafacil') {
                $itens[] = [
                    'ok' => ! empty($config->api_url),
                    'label' => 'URL da API do emissor',
                ];
            }
        }

        if ($usaCert) {
            $itens[] = [
                'ok' => $config->certificado_pfx !== null && $config->certificado_pfx !== '',
                'label' => 'Certificado digital A1 (.pfx)',
            ];
            $itens[] = [
                'ok' => $config->certificado_senha !== null && $config->certificado_senha !== '',
                'label' => 'Senha do certificado',
            ];
        }

        if ($config->emitir_nfce_pdv) {
            $itens[] = [
                'ok' => $config->serie_nfce !== null && $config->numero_proximo_nfce !== null,
                'label' => 'Série e próximo número NFC-e',
            ];
            $cscOk = ! empty($config->csc_id) && $config->csc_token !== null && $config->csc_token !== '';
            $itens[] = [
                'ok' => $cscOk,
                'label' => 'CSC (ID + token) para NFC-e',
                'hint' => 'Obrigatório na SEFAZ para cupom eletrônico',
            ];
        }

        if ($config->emitir_nfe_pedido) {
            $itens[] = [
                'ok' => $config->serie_nfe !== null && $config->numero_proximo_nfe !== null,
                'label' => 'Série e próximo número NF-e',
            ];
        }

        $itens[] = [
            'ok' => (bool) $config->is_active,
            'label' => 'Emissão ativada para este CNPJ',
        ];

        $status = 'ready';
        foreach ($itens as $item) {
            if (! ($item['ok'] ?? false)) {
                $status = 'pending';

                break;
            }
        }

        if ($provider === 'certificado_a1') {
            $status = 'pending';
            $itens[] = [
                'ok' => false,
                'label' => 'Motor de emissão direta SEFAZ',
                'hint' => 'Próxima fase — use Focus/NFe.io enquanto isso',
            ];
        }

        if ($provider === 'vendafacil') {
            $itens[] = [
                'ok' => false,
                'label' => 'Canal VendaFácil para NF',
                'hint' => 'Ative recurso fiscal em Integrações → VendaFácil (fase posterior)',
            ];
            if ($status === 'ready') {
                $status = 'pending';
            }
        }

        return self::montarResultado($itens, $status);
    }

    /**
     * @param  list<array{ok: bool, label: string, hint?: string}>  $itens
     * @return array{pronto: bool, status: string, itens: list<array{ok: bool, label: string, hint?: string}>}
     */
    private static function montarResultado(array $itens, string $status): array
    {
        $pronto = $status === 'ready';
        foreach ($itens as $item) {
            if (! ($item['ok'] ?? false)) {
                $pronto = false;

                break;
            }
        }

        return [
            'pronto' => $pronto,
            'status' => $pronto ? 'ready' : $status,
            'itens' => $itens,
        ];
    }

    public static function labelStatus(string $status): string
    {
        return match ($status) {
            'ready' => 'Pronto para emitir',
            'pending' => 'Pendências',
            'error' => 'Erro na validação',
            default => 'Não configurado',
        };
    }
}
