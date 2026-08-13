<?php

namespace App\Support;

use App\Models\FiscalEmissaoConfig;
use App\Services\Fiscal\FocusNfeClient;

/**
 * A Focus NFe exige CSC / Id Token no cadastro da empresa (API /v2/empresas),
 * não no JSON da NFC-e. Este sync grava o CSC salvo no SAS na empresa Focus.
 */
final class FocusEmpresaCscSync
{
    /**
     * @return array{ok: bool, skipped?: bool, motivo?: string, focus_empresa_id?: int|string|null, mensagem?: string}
     */
    public static function sincronizar(FiscalEmissaoConfig $config, object $empresaSas): array
    {
        if (($config->provider ?? '') !== 'focus_nfe') {
            return ['ok' => true, 'skipped' => true, 'motivo' => 'Provedor não é Focus.'];
        }

        $cscId = trim((string) ($config->csc_id ?? ''));
        $cscToken = trim((string) ($config->csc_token ?? ''));
        if ($cscId === '' || $cscToken === '') {
            return [
                'ok' => false,
                'motivo' => 'CSC/ID ausentes no SAS. Salve ID CSC e Token CSC em Emissão NF-e / NFC-e.',
            ];
        }

        $apiToken = trim((string) ($config->api_token ?? ''));
        if ($apiToken === '') {
            return ['ok' => false, 'motivo' => 'Token Focus ausente no SAS.'];
        }

        $cnpj = FiscalCadastroSupport::normalizarCnpj($empresaSas->cnpj ?? null);
        if (! $cnpj) {
            return ['ok' => false, 'motivo' => 'CNPJ da empresa SAS inválido.'];
        }

        // API de empresas Focus opera em produção.
        $baseUrl = 'https://api.focusnfe.com.br';
        $client = new FocusNfeClient($baseUrl, $apiToken);

        $list = $client->listarEmpresas($cnpj);
        if (! ($list['ok'] ?? false)) {
            $status = (int) ($list['http_status'] ?? 0);
            $msg = self::extrairMensagem($list['body'] ?? null) ?: ('HTTP '.$status);
            if ($status === 401 || $status === 403) {
                return [
                    'ok' => false,
                    'auth_error' => true,
                    'motivo' => 'Token Focus sem permissão na API de empresas (HTTP '.$status.'). '
                        .'No painel Focus → Tokens, use o **Token principal produção** (olho no topo), '
                        .'não só o token da linha da empresa. Ou cadastre o CSC manualmente em Empresas → DETALHES.',
                ];
            }

            return ['ok' => false, 'motivo' => 'Falha ao consultar empresa na Focus: '.$msg];
        }

        $empresaFocus = self::primeiraEmpresa($list['body'] ?? null, $cnpj);
        if (! $empresaFocus) {
            return [
                'ok' => false,
                'motivo' => 'CNPJ '.$cnpj.' não encontrado na Focus. Cadastre a empresa no painel Focus.',
            ];
        }

        $focusId = $empresaFocus['id'] ?? null;
        if ($focusId === null || $focusId === '') {
            return ['ok' => false, 'motivo' => 'Focus não retornou id da empresa.'];
        }

        $idToken = (int) preg_replace('/\D+/', '', $cscId);
        if ($idToken <= 0) {
            return ['ok' => false, 'motivo' => 'ID CSC inválido (use o número da SEFAZ, ex.: 1 ou 000001).'];
        }

        $env = (string) ($config->environment ?? 'homologation');
        $payload = $env === 'production'
            ? [
                'csc_nfce_producao' => $cscToken,
                'id_token_nfce_producao' => $idToken,
            ]
            : [
                'csc_nfce_homologacao' => $cscToken,
                'id_token_nfce_homologacao' => $idToken,
            ];

        // Mantém NFC-e habilitada ao sincronizar.
        if (array_key_exists('habilita_nfce', $empresaFocus) && ! ($empresaFocus['habilita_nfce'] ?? false)) {
            $payload['habilita_nfce'] = true;
        }

        $put = $client->atualizarEmpresa($focusId, $payload);
        if (! ($put['ok'] ?? false)) {
            $msg = self::extrairMensagem($put['body'] ?? null) ?: ('HTTP '.($put['http_status'] ?? '?'));

            return [
                'ok' => false,
                'focus_empresa_id' => $focusId,
                'motivo' => 'Focus recusou atualização do CSC: '.$msg,
            ];
        }

        return [
            'ok' => true,
            'focus_empresa_id' => $focusId,
            'mensagem' => $env === 'production'
                ? 'CSC de produção sincronizado na Focus (empresa #'.$focusId.').'
                : 'CSC de homologação sincronizado na Focus (empresa #'.$focusId.').',
        ];
    }

    /**
     * @param  array<string, mixed>|list<mixed>|null  $body
     * @return array<string, mixed>|null
     */
    private static function primeiraEmpresa(array|null $body, string $cnpj): ?array
    {
        if ($body === null) {
            return null;
        }
        // Lista: [ {...}, ... ] ou { data: [...] }
        $lista = $body;
        if (isset($body['data']) && is_array($body['data'])) {
            $lista = $body['data'];
        }
        if ($lista !== [] && array_is_list($lista)) {
            foreach ($lista as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rowCnpj = preg_replace('/\D+/', '', (string) ($row['cnpj'] ?? ''));
                if ($rowCnpj === $cnpj || $cnpj === '') {
                    return $row;
                }
            }

            return is_array($lista[0] ?? null) ? $lista[0] : null;
        }
        if (isset($body['id']) || isset($body['cnpj'])) {
            return $body;
        }

        return null;
    }

    /** @param  mixed  $body */
    private static function extrairMensagem(mixed $body): ?string
    {
        if (! is_array($body)) {
            return is_string($body) ? $body : null;
        }
        foreach (['mensagem', 'message', 'erro', 'error'] as $k) {
            if (! empty($body[$k]) && is_string($body[$k])) {
                return $body[$k];
            }
        }
        if (! empty($body['erros'][0]['mensagem']) && is_string($body['erros'][0]['mensagem'])) {
            return $body['erros'][0]['mensagem'];
        }

        return null;
    }
}
