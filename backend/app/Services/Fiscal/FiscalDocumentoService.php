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
        $qrcodeUrl = null;
        $focusBody = [];
        $urlDanfeFromFocus = null;
        $chave = preg_replace('/\D+/', '', (string) ($venda->chave_acesso ?? ''));
        if ($ref !== '') {
            $consulta = $client->consultarNfce($ref);
            $focusBody = is_array($consulta['body'] ?? null) ? $consulta['body'] : [];
            if (! empty($focusBody['qrcode_url']) && is_string($focusBody['qrcode_url'])) {
                $qrcodeUrl = $focusBody['qrcode_url'];
            }
            if ($chave === '' && ! empty($focusBody['chave_nfe'])) {
                $chave = preg_replace('/\D+/', '', (string) $focusBody['chave_nfe']) ?? '';
            }
            if (! empty($focusBody['caminho_danfe'])) {
                $urlDanfeFromFocus = self::urlAbsolutaFocus($baseUrl, (string) $focusBody['caminho_danfe']);
            }
        }

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

        $urlDanfe = self::urlAbsolutaFocus($baseUrl, $venda->url_danfe ?? null)
            ?? ($urlDanfeFromFocus ?? null);
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
                    'body' => self::htmlParaPdf($bin['body'], $qrcodeUrl, $chave ?: null),
                    'content_type' => 'application/pdf',
                    'filename' => $filename,
                ];
            }
        }

        // Último recurso: caminho_danfe da consulta Focus.
        if ($ref !== '' && isset($focusBody) && is_array($focusBody)) {
            $caminho = $focusBody['caminho_danfe'] ?? null;
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
                        'body' => self::htmlParaPdf($bin['body'], $qrcodeUrl, $chave ?: null),
                        'content_type' => 'application/pdf',
                        'filename' => $filename,
                    ];
                }
            }
        }

        throw new \RuntimeException('DANFE/PDF não disponível na Focus para esta venda. Tente novamente ou abra no painel Focus.');
    }

    /**
     * Converte HTML do DANFCe Focus em PDF e garante QR Code da consulta SEFAZ.
     */
    private static function htmlParaPdf(string $html, ?string $qrcodeUrl = null, ?string $chave = null): string
    {
        if (! class_exists(\Dompdf\Dompdf::class)) {
            throw new \RuntimeException('Gerador de PDF indisponível no servidor (dompdf).');
        }
        // Dompdf lida melhor com HTML relativamente simples; remove scripts.
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = self::injetarQrCodeNoHtml($html, $qrcodeUrl, $chave);
        if (! str_contains(strtolower($html), '<html')) {
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>';
        }

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper([0, 0, 226.77, 841.89], 'portrait'); // ~80mm largura cupom
        $dompdf->render();
        $out = $dompdf->output();
        if (! is_string($out) || $out === '' || ! str_starts_with($out, '%PDF')) {
            throw new \RuntimeException('Falha ao gerar PDF do cupom NFC-e.');
        }

        return $out;
    }

    private static function injetarQrCodeNoHtml(string $html, ?string $qrcodeUrl, ?string $chave): string
    {
        $qrcodeUrl = trim((string) $qrcodeUrl);
        if ($qrcodeUrl === '') {
            return $html;
        }

        $dataUri = self::gerarQrDataUri($qrcodeUrl);
        if ($dataUri === null) {
            return $html;
        }

        $chaveTxt = $chave ? htmlspecialchars($chave, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
        $urlTxt = htmlspecialchars($qrcodeUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $bloco = '<div style="text-align:center;margin:16px 0;page-break-inside:avoid">'
            .'<div style="font-size:11px;font-weight:bold;margin-bottom:6px">Consulta via QR Code</div>'
            .'<img src="'.$dataUri.'" width="150" height="150" alt="QR Code NFC-e" />'
            .($chaveTxt !== '' ? '<div style="font-size:9px;margin-top:8px;word-break:break-all">Chave: '.$chaveTxt.'</div>' : '')
            .'<div style="font-size:7px;margin-top:4px;word-break:break-all;color:#444">'.$urlTxt.'</div>'
            .'</div>';

        // Remove imagens de QR quebradas/relativas que o Dompdf não carrega.
        $html = preg_replace('#<img[^>]*(qrcode|qr-code|qr_code)[^>]*>#i', '', $html) ?? $html;

        if (stripos($html, '</body>') !== false) {
            return preg_replace('#</body>#i', $bloco.'</body>', $html, 1) ?? ($html.$bloco);
        }

        return $html.$bloco;
    }

    private static function gerarQrDataUri(string $conteudo): ?string
    {
        try {
            if (class_exists(\Endroid\QrCode\Builder\Builder::class)) {
                $builder = new \Endroid\QrCode\Builder\Builder(
                    writer: new \Endroid\QrCode\Writer\PngWriter(),
                    data: $conteudo,
                    size: 240,
                    margin: 8,
                );
                $result = $builder->build();

                return $result->getDataUri();
            }

            if (class_exists(\Endroid\QrCode\QrCode::class)) {
                $qrCode = new \Endroid\QrCode\QrCode($conteudo);
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $result = $writer->write($qrCode);

                return $result->getDataUri();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
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
