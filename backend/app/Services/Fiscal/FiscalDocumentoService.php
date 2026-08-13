<?php

namespace App\Services\Fiscal;

use App\Models\FiscalEmissaoConfig;
use App\Support\FiscalEmissaoConfigSupport;
use App\Support\VendaFiscalSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FiscalDocumentoService
{
    /** @return array{pdf: string, xml: string, info: string} */
    public static function rotasRelativas(int $vendaId): array
    {
        return [
            'pdf' => "/fiscal/emissao/vendas/{$vendaId}/danfe.pdf",
            'xml' => "/fiscal/emissao/vendas/{$vendaId}/xml",
            'info' => "/fiscal/emissao/vendas/{$vendaId}/documentos",
        ];
    }

    /** @return array<string, mixed> */
    public static function info(int $vendaId): array
    {
        $ctx = self::contextoVenda($vendaId);

        return [
            'venda_id' => $vendaId,
            'status_documento' => $ctx['venda']->status_documento ?? null,
            'chave_acesso' => $ctx['venda']->chave_acesso ?? null,
            'numero_documento' => $ctx['venda']->numero_documento ?? null,
            'serie_documento' => $ctx['venda']->serie_documento ?? null,
            'emissao_ref' => $ctx['venda']->emissao_ref ?? null,
            'url_danfe' => $ctx['venda']->url_danfe ?? null,
            'url_xml' => $ctx['venda']->url_xml ?? null,
            'documentos' => self::rotasRelativas($vendaId),
            'disponivel' => self::documentoDisponivel($ctx['venda']),
        ];
    }

    /** @return array{body: string, content_type: string, filename: string} */
    public static function obterPdf(int $vendaId): array
    {
        return self::obterBinario($vendaId, 'pdf');
    }

    /** @return array{body: string, content_type: string, filename: string} */
    public static function obterXml(int $vendaId): array
    {
        return self::obterBinario($vendaId, 'xml');
    }

    /** @return array{body: string, content_type: string, filename: string} */
    private static function obterBinario(int $vendaId, string $tipo): array
    {
        $ctx = self::contextoVenda($vendaId);
        $venda = $ctx['venda'];
        $client = $ctx['client'];
        $baseUrl = $ctx['base_url'];
        if (! self::documentoDisponivel($venda)) {
            throw new \RuntimeException('Nota fiscal ainda não autorizada para esta venda.');
        }

        $ref = trim((string) ($venda->emissao_ref ?? ''));
        $filename = self::nomeArquivo($venda, $tipo === 'xml' ? 'xml' : 'pdf');

        if ($tipo === 'xml') {
            if ($ref !== '') {
                $bin = $client->baixarNfceXml($ref);
                if (self::pareceXml($bin)) {
                    return [
                        'body' => $bin['body'],
                        'content_type' => 'application/xml; charset=utf-8',
                        'filename' => $filename,
                    ];
                }
            }
            $urlXml = self::urlAbsolutaFocus($baseUrl, $venda->url_xml ?? null);
            if ($urlXml) {
                $bin = $client->baixarUrl($urlXml);
                if (self::pareceXml($bin)) {
                    return [
                        'body' => $bin['body'],
                        'content_type' => 'application/xml; charset=utf-8',
                        'filename' => $filename,
                    ];
                }
            }
            throw new \RuntimeException('XML não disponível na Focus para esta venda.');
        }

        // PDF / DANFCe (NFC-e Focus costuma entregar HTML em caminho_danfe)
        if ($ref !== '') {
            $bin = $client->baixarNfcePdf($ref);
            if (self::parecePdf($bin)) {
                return [
                    'body' => $bin['body'],
                    'content_type' => 'application/pdf',
                    'filename' => $filename,
                ];
            }
        }

        $urlDanfe = self::urlAbsolutaFocus($baseUrl, $venda->url_danfe ?? null);
        if ($urlDanfe) {
            // tenta .pdf no lugar de .html
            if (str_ends_with(strtolower(parse_url($urlDanfe, PHP_URL_PATH) ?: ''), '.html')) {
                $pdfUrl = preg_replace('/\.html$/i', '.pdf', $urlDanfe);
                if (is_string($pdfUrl) && $pdfUrl !== $urlDanfe) {
                    $binPdf = $client->baixarUrl($pdfUrl);
                    if (self::parecePdf($binPdf)) {
                        return [
                            'body' => $binPdf['body'],
                            'content_type' => 'application/pdf',
                            'filename' => $filename,
                        ];
                    }
                }
            }

            $bin = $client->baixarUrl($urlDanfe);
            if (self::parecePdf($bin)) {
                return [
                    'body' => $bin['body'],
                    'content_type' => 'application/pdf',
                    'filename' => $filename,
                ];
            }
            if (self::pareceHtml($bin)) {
                return [
                    'body' => $bin['body'],
                    'content_type' => 'text/html; charset=utf-8',
                    'filename' => self::nomeArquivo($venda, 'html'),
                ];
            }
        }

        // Último recurso: consulta a nota na Focus e usa caminho_danfe da resposta.
        if ($ref !== '') {
            $consulta = $client->consultarNfce($ref);
            $body = is_array($consulta['body'] ?? null) ? $consulta['body'] : [];
            $caminho = $body['caminho_danfe'] ?? null;
            $abs = self::urlAbsolutaFocus($baseUrl, is_string($caminho) ? $caminho : null);
            if ($abs) {
                $bin = $client->baixarUrl($abs);
                if (self::parecePdf($bin)) {
                    return [
                        'body' => $bin['body'],
                        'content_type' => 'application/pdf',
                        'filename' => $filename,
                    ];
                }
                if (self::pareceHtml($bin)) {
                    return [
                        'body' => $bin['body'],
                        'content_type' => 'text/html; charset=utf-8',
                        'filename' => self::nomeArquivo($venda, 'html'),
                    ];
                }
            }
        }

        throw new \RuntimeException('DANFE/PDF não disponível na Focus para esta venda. Tente novamente ou abra no painel Focus.');
    }

    /** @return array{venda: object, client: FocusNfeClient, base_url: string} */
    private static function contextoVenda(int $vendaId): array
    {
        if (! Schema::hasTable('vendas')) {
            throw new \RuntimeException('Módulo de vendas não instalado.');
        }
        $venda = DB::table('vendas')->where('id', $vendaId)->first();
        if (! $venda) {
            throw new \RuntimeException('Venda não encontrada.');
        }

        $empresaId = (int) ($venda->empresa_id ?? 0);
        if ($empresaId <= 0) {
            $empresaId = (int) (VendaFiscalSupport::resolverEmpresaUnidade((int) ($venda->unidade_id ?? 0)) ?? 0);
        }
        if ($empresaId <= 0) {
            throw new \RuntimeException('Venda sem empresa/CNPJ vinculado.');
        }

        $config = FiscalEmissaoConfig::query()->where('empresa_id', $empresaId)->first();
        if (! $config || empty($config->api_token)) {
            throw new \RuntimeException('Configure o token Focus em Emissão NF-e / NFC-e.');
        }

        $baseUrl = rtrim((string) ($config->api_url ?: FiscalEmissaoConfigSupport::focusBaseUrl((string) $config->environment)), '/');
        $client = new FocusNfeClient($baseUrl, (string) $config->api_token);

        return ['venda' => $venda, 'client' => $client, 'base_url' => $baseUrl];
    }

    private static function documentoDisponivel(object $venda): bool
    {
        $st = strtolower(trim((string) ($venda->status_documento ?? '')));

        return in_array($st, ['autorizado', 'autorizada'], true)
            || ! empty($venda->chave_acesso)
            || ! empty($venda->emissao_ref);
    }

    private static function nomeArquivo(object $venda, string $ext): string
    {
        $num = $venda->numero_documento ?? $venda->id ?? 'nota';

        return 'nfce-'.preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $num).'.'.$ext;
    }

    private static function urlAbsolutaFocus(string $baseUrl, mixed $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if ($url[0] !== '/') {
            $url = '/'.$url;
        }

        return rtrim($baseUrl, '/').$url;
    }

    /** @param array{ok?: bool, body?: string, content_type?: string|null, http_status?: int} $bin */
    private static function parecePdf(array $bin): bool
    {
        if (! ($bin['ok'] ?? false)) {
            return false;
        }
        $body = (string) ($bin['body'] ?? '');
        if ($body === '' || str_starts_with(ltrim($body), '{') || str_starts_with(ltrim($body), '[')) {
            return false;
        }
        $ct = strtolower((string) ($bin['content_type'] ?? ''));

        return str_starts_with($body, '%PDF') || str_contains($ct, 'pdf');
    }

    /** @param array{ok?: bool, body?: string, content_type?: string|null} $bin */
    private static function pareceXml(array $bin): bool
    {
        if (! ($bin['ok'] ?? false)) {
            return false;
        }
        $body = ltrim((string) ($bin['body'] ?? ''));
        if ($body === '' || str_starts_with($body, '{')) {
            return false;
        }

        return str_starts_with($body, '<?xml') || str_starts_with($body, '<');
    }

    /** @param array{ok?: bool, body?: string, content_type?: string|null} $bin */
    private static function pareceHtml(array $bin): bool
    {
        if (! ($bin['ok'] ?? false)) {
            return false;
        }
        $body = ltrim((string) ($bin['body'] ?? ''));
        if ($body === '' || str_starts_with($body, '{')) {
            return false;
        }
        $ct = strtolower((string) ($bin['content_type'] ?? ''));

        return str_contains($ct, 'html')
            || str_starts_with($body, '<!DOCTYPE')
            || str_starts_with($body, '<html')
            || str_contains(strtolower(substr($body, 0, 200)), '<html');
    }
}
