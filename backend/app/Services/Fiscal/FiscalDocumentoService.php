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
        if (! self::documentoDisponivel($venda)) {
            throw new \RuntimeException('Nota fiscal ainda não autorizada para esta venda.');
        }

        $ref = trim((string) ($venda->emissao_ref ?? ''));
        $client = $ctx['client'];
        $ext = $tipo === 'xml' ? 'xml' : 'pdf';
        $contentType = $tipo === 'xml' ? 'application/xml; charset=utf-8' : 'application/pdf';
        $filename = self::nomeArquivo($venda, $ext);

        if ($ref !== '') {
            $bin = $tipo === 'xml' ? $client->baixarNfceXml($ref) : $client->baixarNfcePdf($ref);
            if ($bin['ok'] && ($bin['body'] ?? '') !== '') {
                return [
                    'body' => $bin['body'],
                    'content_type' => $bin['content_type'] ?: $contentType,
                    'filename' => $filename,
                ];
            }
        }

        $url = $tipo === 'xml' ? ($venda->url_xml ?? null) : ($venda->url_danfe ?? null);
        if ($url) {
            $bin = $client->baixarUrl((string) $url);
            if ($bin['ok'] && ($bin['body'] ?? '') !== '') {
                return [
                    'body' => $bin['body'],
                    'content_type' => $bin['content_type'] ?: $contentType,
                    'filename' => $filename,
                ];
            }
        }

        throw new \RuntimeException('PDF/XML não disponível na Focus para esta venda. Tente reemitir ou consulte o painel Focus.');
    }

    /** @return array{venda: object, client: FocusNfeClient} */
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

        $baseUrl = $config->api_url ?: FiscalEmissaoConfigSupport::focusBaseUrl((string) $config->environment);
        $client = new FocusNfeClient($baseUrl, (string) $config->api_token);

        return ['venda' => $venda, 'client' => $client];
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
}
