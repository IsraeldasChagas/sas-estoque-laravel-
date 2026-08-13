<?php

namespace App\Services\Fiscal;

use App\Models\FiscalEmissaoConfig;
use App\Support\FiscalEmissaoConfigSupport;
use App\Support\VendaFiscalSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FiscalDocumentoService
{
    /** @return array{pdf: string, xml: string, info: string, danfe_html?: string} */
    public static function rotasRelativas(int $vendaId): array
    {
        return [
            'pdf' => "/fiscal/emissao/vendas/{$vendaId}/danfe.pdf",
            'xml' => "/fiscal/emissao/vendas/{$vendaId}/xml",
            'danfe_html' => "/fiscal/emissao/vendas/{$vendaId}/danfe.html",
            'info' => "/fiscal/emissao/vendas/{$vendaId}/documentos",
        ];
    }

    /** @return array<string, mixed> */
    public static function info(int $vendaId): array
    {
        $ctx = self::contextoVenda($vendaId);
        $venda = $ctx['venda'];
        $client = $ctx['client'];
        $baseUrl = $ctx['base_url'];

        $qrcodeUrl = null;
        $urlConsulta = null;
        $danfeFocus = self::urlAbsolutaFocus($baseUrl, $venda->url_danfe ?? null);
        $ref = trim((string) ($venda->emissao_ref ?? ''));
        if ($ref !== '') {
            $consulta = $client->consultarNfce($ref);
            $body = is_array($consulta['body'] ?? null) ? $consulta['body'] : [];
            if (! empty($body['qrcode_url']) && is_string($body['qrcode_url'])) {
                $qrcodeUrl = $body['qrcode_url'];
            }
            if (! empty($body['url_consulta_nf']) && is_string($body['url_consulta_nf'])) {
                $urlConsulta = $body['url_consulta_nf'];
            }
            if (! $danfeFocus && ! empty($body['caminho_danfe'])) {
                $danfeFocus = self::urlAbsolutaFocus($baseUrl, (string) $body['caminho_danfe']);
            }
        }

        $chave = preg_replace('/\D+/', '', (string) ($venda->chave_acesso ?? ''));

        return [
            'venda_id' => $vendaId,
            'status_documento' => $venda->status_documento ?? null,
            'chave_acesso' => $chave !== '' ? $chave : null,
            'chave_completa' => is_string($chave) && strlen($chave) === 44,
            'numero_documento' => $venda->numero_documento ?? null,
            'serie_documento' => $venda->serie_documento ?? null,
            'emissao_ref' => $venda->emissao_ref ?? null,
            'url_danfe' => $venda->url_danfe ?? null,
            'url_xml' => $venda->url_xml ?? null,
            'qrcode_url' => $qrcodeUrl,
            'url_consulta_nf' => $urlConsulta,
            'danfe_focus_url' => $danfeFocus,
            'documentos' => self::rotasRelativas($vendaId),
            'disponivel' => self::documentoDisponivel($venda),
        ];
    }

    /** HTML oficial do cupom (DANFCe) na Focus — inclui QR Code válido. */
    public static function obterDanfeHtml(int $vendaId): array
    {
        $ctx = self::contextoVenda($vendaId);
        $venda = $ctx['venda'];
        $client = $ctx['client'];
        $baseUrl = $ctx['base_url'];
        if (! self::documentoDisponivel($venda)) {
            throw new \RuntimeException('Nota fiscal ainda não autorizada para esta venda.');
        }

        $urlDanfe = self::urlAbsolutaFocus($baseUrl, $venda->url_danfe ?? null);
        $ref = trim((string) ($venda->emissao_ref ?? ''));
        if (! $urlDanfe && $ref !== '') {
            $consulta = $client->consultarNfce($ref);
            $body = is_array($consulta['body'] ?? null) ? $consulta['body'] : [];
            if (! empty($body['caminho_danfe'])) {
                $urlDanfe = self::urlAbsolutaFocus($baseUrl, (string) $body['caminho_danfe']);
            }
        }
        if (! $urlDanfe) {
            throw new \RuntimeException('DANFE HTML não disponível na Focus.');
        }

        $bin = $client->baixarUrl($urlDanfe);
        if (! self::pareceHtml($bin) && ! self::parecePdf($bin)) {
            throw new \RuntimeException('Não foi possível obter o cupom HTML na Focus.');
        }

        return [
            'body' => $bin['body'],
            'content_type' => self::parecePdf($bin) ? 'application/pdf' : 'text/html; charset=utf-8',
            'filename' => self::nomeArquivo($venda, self::parecePdf($bin) ? 'pdf' : 'html'),
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

        // Preferimos HTML → PDF A4 estilizado (logo menor) em vez do PDF cru da Focus.
        $urlDanfe = self::urlAbsolutaFocus($baseUrl, $venda->url_danfe ?? null)
            ?? ($urlDanfeFromFocus ?? null);
        if ($urlDanfe) {
            $bin = $client->baixarUrl($urlDanfe);
            if (self::pareceHtml($bin)) {
                return [
                    'body' => self::htmlParaPdf($bin['body'], $qrcodeUrl, $chave ?: null),
                    'content_type' => 'application/pdf',
                    'filename' => $filename,
                ];
            }
            if (self::parecePdf($bin)) {
                return [
                    'body' => $bin['body'],
                    'content_type' => 'application/pdf',
                    'filename' => $filename,
                ];
            }
        }

        if ($ref !== '' && is_array($focusBody)) {
            $caminho = $focusBody['caminho_danfe'] ?? null;
            $abs = self::urlAbsolutaFocus($baseUrl, is_string($caminho) ? $caminho : null);
            if ($abs && $abs !== $urlDanfe) {
                $bin = $client->baixarUrl($abs);
                if (self::pareceHtml($bin)) {
                    return [
                        'body' => self::htmlParaPdf($bin['body'], $qrcodeUrl, $chave ?: null),
                        'content_type' => 'application/pdf',
                        'filename' => $filename,
                    ];
                }
                if (self::parecePdf($bin)) {
                    return [
                        'body' => $bin['body'],
                        'content_type' => 'application/pdf',
                        'filename' => $filename,
                    ];
                }
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

        throw new \RuntimeException('DANFE/PDF não disponível na Focus para esta venda. Tente novamente ou abra no painel Focus.');
    }

    /**
     * Converte HTML do DANFCe Focus em PDF A4 e garante QR Code da consulta SEFAZ.
     */
    private static function htmlParaPdf(string $html, ?string $qrcodeUrl = null, ?string $chave = null): string
    {
        if (! class_exists(\Dompdf\Dompdf::class)) {
            throw new \RuntimeException('Gerador de PDF indisponível no servidor (dompdf).');
        }
        // Dompdf lida melhor com HTML relativamente simples; remove scripts.
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = self::prepararHtmlDanfeA4($html);
        $html = self::injetarQrCodeNoHtml($html, $qrcodeUrl, $chave);
        if (! str_contains(strtolower($html), '<html')) {
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>';
        }

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('dpi', 110);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $out = $dompdf->output();
        if (! is_string($out) || $out === '' || ! str_starts_with($out, '%PDF')) {
            throw new \RuntimeException('Falha ao gerar PDF do cupom NFC-e.');
        }

        return $out;
    }

    /**
     * Ajusta o HTML Focus para impressão A4: simetria no topo, espaço e texto dentro das bordas.
     */
    private static function prepararHtmlDanfeA4(string $html): string
    {
        // Logo menor (Focus manda a imagem sem size).
        $html = preg_replace_callback(
            '#(<div[^>]*class=["\'][^"\']*logomarca[^"\']*["\'][^>]*>.*?<img)([^>]*)(>)#is',
            static function (array $m): string {
                $attrs = $m[2];
                $attrs = preg_replace('/\s(width|height|style)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $attrs) ?? $attrs;

                return $m[1].$attrs.' width="40" height="40" style="max-width:40px;max-height:28px;width:auto;height:auto;display:block;margin:0 auto;"'.$m[3];
            },
            $html,
            1
        ) ?? $html;

        // Simetria no cabeçalho: título NFC-e alinhado ao centro como a logo.
        $html = preg_replace(
            '#(<div[^>]*class=["\'][^"\']*logomarca[^"\']*["\'][^>]*>.*?)align=["\']left["\']#is',
            '$1align="center"',
            $html,
            1
        ) ?? $html;

        // Dompdf centraliza o bloco na página com tabela wrapper.
        if (! str_contains($html, 'sas-danfe-center-wrap')) {
            $html = preg_replace(
                '#<div\s+class=["\']content["\']>#i',
                '<table class="sas-danfe-center-wrap" width="100%" border="0" cellpadding="0" cellspacing="0"><tr><td align="center" valign="top">'
                .'<div class="content">',
                $html,
                1
            ) ?? $html;
            if (stripos($html, '</body>') !== false) {
                $html = preg_replace(
                    '#</div>\s*</body>#i',
                    '</div></td></tr></table></body>',
                    $html,
                    1
                ) ?? $html;
            }
        }

        $css = <<<'CSS'
<style type="text/css" id="sas-danfe-a4">
@page { size: A4 portrait; margin: 12mm 12mm; }
html, body {
  margin: 0 !important;
  padding: 0 !important;
  background: #ffffff;
  color: #111111;
  font-family: DejaVu Sans, Arial, Helvetica, sans-serif !important;
  font-size: 10px !important;
  line-height: 1.45;
  text-align: left;
}
.sas-danfe-center-wrap {
  width: 100%;
  margin: 0;
  padding: 0;
}
.content {
  width: 170mm !important;
  max-width: 170mm !important;
  margin: 0 auto !important;
  padding: 14px 18px 20px 18px !important;
  border: 1px solid #bfbfbf !important;
  box-sizing: border-box;
  text-align: left !important;
  overflow: hidden;
  word-wrap: break-word;
  overflow-wrap: anywhere;
}
.logomarca {
  text-align: center !important;
  margin: 0 0 12px 0;
  padding: 2px 0 10px 0;
  border-bottom: 1px solid #dddddd;
}
.logomarca table {
  width: 100% !important;
  margin: 0 auto;
}
.logomarca td {
  text-align: center !important;
  vertical-align: middle !important;
}
.logomarca img {
  max-width: 40px !important;
  max-height: 28px !important;
  width: auto !important;
  height: auto !important;
  display: block !important;
  margin: 0 auto 4px auto !important;
}
.logomarca span,
.logomarca strong,
.logomarca em {
  font-size: 11px !important;
  font-style: normal !important;
  letter-spacing: 0.03em;
  color: #222222;
}
.dados-da-empresa {
  margin: 0 0 10px 0;
}
.dados-da-empresa table {
  width: 100% !important;
  table-layout: fixed;
}
.dados-da-empresa td {
  text-align: left !important;
  font-size: 10px !important;
  line-height: 1.5;
  padding: 2px 4px !important;
  word-wrap: break-word;
  overflow-wrap: anywhere;
}
.documento-auxiliar {
  margin: 8px 0 10px 0;
}
.documento-auxiliar td {
  text-align: center !important;
  font-size: 9.5px !important;
  line-height: 1.45;
  padding: 4px 6px !important;
}
.linha {
  border-bottom: 1px solid #222 !important;
  margin: 10px 0 !important;
  width: 100%;
  height: 0;
}
.tabela-nfce,
.lista-produtos,
.tabela-nfce table,
.lista-produtos table {
  width: 100% !important;
  max-width: 100% !important;
  table-layout: fixed;
  border-collapse: collapse;
}
.tabela-nfce td,
.tabela-nfce th,
.lista-produtos td,
.lista-produtos th {
  font-size: 9px !important;
  vertical-align: top;
  padding: 3px 4px !important;
  word-wrap: break-word;
  overflow-wrap: anywhere;
  white-space: normal !important;
}
.lista-produtos th:nth-child(1),
.lista-produtos td:nth-child(1) { width: 14%; }
.lista-produtos th:nth-child(2),
.lista-produtos td:nth-child(2) { width: 38%; }
.lista-produtos th:nth-child(3),
.lista-produtos td:nth-child(3) { width: 10%; }
.lista-produtos th:nth-child(4),
.lista-produtos td:nth-child(4) { width: 8%; }
.lista-produtos th:nth-child(5),
.lista-produtos td:nth-child(5),
.lista-produtos th:nth-child(6),
.lista-produtos td:nth-child(6) { width: 15%; }
#qr-code0, #qr-code1 {
  display: none !important;
  width: 0 !important;
  height: 0 !important;
  margin: 0 !important;
  padding: 0 !important;
  overflow: hidden !important;
}
.sas-qr-block {
  text-align: center !important;
  margin: 16px 0 2px 0;
  padding: 14px 10px 6px 10px;
  border-top: 1px solid #d0d0d0;
  page-break-inside: avoid;
  width: 100%;
  box-sizing: border-box;
}
.sas-qr-block .sas-qr-title {
  font-size: 11px;
  font-weight: bold;
  margin-bottom: 8px;
  letter-spacing: 0.02em;
}
.sas-qr-block img {
  width: 110px;
  height: 110px;
  display: block;
  margin: 0 auto;
}
.sas-qr-block .sas-qr-chave,
.sas-qr-block .sas-qr-url {
  font-size: 8px;
  margin-top: 8px;
  padding: 0 6px;
  word-wrap: break-word;
  overflow-wrap: anywhere;
  word-break: break-all;
  color: #222;
  max-width: 100%;
  box-sizing: border-box;
}
.sas-qr-block .sas-qr-url {
  font-size: 7px;
  color: #666;
  margin-top: 4px;
}
</style>
CSS;

        if (stripos($html, '</head>') !== false) {
            return preg_replace('#</head>#i', $css.'</head>', $html, 1) ?? ($html.$css);
        }

        return $css.$html;
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
        $bloco = '<div class="sas-qr-block">'
            .'<div class="sas-qr-title">Consulta via QR Code</div>'
            .'<img src="'.$dataUri.'" width="128" height="128" alt="QR Code NFC-e" />'
            .($chaveTxt !== '' ? '<div class="sas-qr-chave">Chave: '.$chaveTxt.'</div>' : '')
            .'<div class="sas-qr-url">'.$urlTxt.'</div>'
            .'</div>';

        // Remove imagens de QR quebradas/relativas que o Dompdf não carrega.
        $html = preg_replace('#<img[^>]*(qrcode|qr-code|qr_code)[^>]*>#i', '', $html) ?? $html;

        // Prefere ficar dentro do .content centralizado.
        if (preg_match('#</div>\s*</td>\s*</tr>\s*</table>\s*</body>#i', $html)) {
            return preg_replace(
                '#</div>(\s*</td>\s*</tr>\s*</table>\s*</body>)#i',
                $bloco.'</div>$1',
                $html,
                1
            ) ?? ($html.$bloco);
        }
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
