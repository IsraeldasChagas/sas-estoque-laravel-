<?php

namespace App\Support\Investimento;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cotações oficiais: Tesouro Transparente (CSV diário) e Bacen (Selic/CDI).
 */
class InvestimentoMercado
{
    private const CSV_URL = 'https://www.tesourotransparente.gov.br/ckan/dataset/df56aa42-484a-4a59-8184-7676580c81e3/resource/796d2059-14e9-44e3-80c9-2d9e30b405c1/download/precotaxatesourodireto.csv';

    private const CSV_PATH = 'investimento/precotaxatesourodireto.csv';

    private const CACHE_KEY = 'investimento_cotacoes_parsed';

    private const CACHE_TTL_SECONDS = 43200; // 12 horas

    /** Tabela oficial IR regressivo — renda fixa (Lei 11.033/2004). */
    public static function tabelaIrRegressiva(): array
    {
        return [
            ['faixa' => 'Até 180 dias', 'dias_max' => 180, 'aliquota_percent' => 22.5],
            ['faixa' => 'De 181 a 360 dias', 'dias_max' => 360, 'aliquota_percent' => 20.0],
            ['faixa' => 'De 361 a 720 dias', 'dias_max' => 720, 'aliquota_percent' => 17.5],
            ['faixa' => 'Acima de 720 dias', 'dias_max' => null, 'aliquota_percent' => 15.0],
        ];
    }

    /**
     * Retorna referências de mercado para o simulador.
     *
     * @return array<string, mixed>
     */
    public static function referencias(bool $forceRefresh = false): array
    {
        $cacheKey = self::CACHE_KEY;
        if (! $forceRefresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $selic = self::buscarSerieBacen(432, 'Meta Selic (% a.a.)');
        $cdi = self::buscarSerieBacen(4389, 'CDI acumulado no mês (% a.a. ref.)');

        $tesouro = self::obterTitulosTesouro($forceRefresh);

        $sugestoes = self::montarSugestoesPorTipo($selic, $cdi, $tesouro['titulos'] ?? []);

        $payload = [
            'atualizado_em' => now()->toIso8601String(),
            'fontes' => [
                'tesouro_csv' => self::CSV_URL,
                'tesouro_portal' => 'https://www.tesourodireto.com.br/',
                'tesouro_transparente' => 'https://www.tesourotransparente.gov.br/temas/divida-publica-federal/tesouro-direto',
                'bacen_selic' => 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.432/dados/ultimos/1?formato=json',
                'bacen_cdi' => 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.4389/dados/ultimos/1?formato=json',
            ],
            'tabela_ir_regressiva' => self::tabelaIrRegressiva(),
            'selic' => $selic,
            'cdi' => $cdi,
            'tesouro' => $tesouro,
            'sugestoes_por_tipo' => $sugestoes,
            'avisos' => array_values(array_filter([
                ($tesouro['ok'] ?? false) ? null : ($tesouro['erro'] ?? 'Cotações do Tesouro indisponíveis no momento; use Selic/CDI ou informe a taxa manualmente.'),
                'CDB e Fundo DI não têm API pública única; a taxa sugerida usa CDI do Bacen como referência.',
                'Tesouro Selic acompanha a Selic; a taxa sugerida vem do Bacen, não do campo "Taxa Compra" do CSV.',
            ])),
        ];

        Cache::put($cacheKey, $payload, self::CACHE_TTL_SECONDS);

        return $payload;
    }

    /** @return array{valor: ?float, data: ?string, label: string, ok: bool} */
    private static function buscarSerieBacen(int $codigo, string $label): array
    {
        try {
            $r = Http::timeout(15)->withHeaders(['User-Agent' => 'SAS-Estoque/1.0'])
                ->get("https://api.bcb.gov.br/dados/serie/bcdata.sgs.{$codigo}/dados/ultimos/1?formato=json");
            if (! $r->successful()) {
                return ['valor' => null, 'data' => null, 'label' => $label, 'ok' => false];
            }
            $items = $r->json();
            $row = is_array($items) && isset($items[0]) ? $items[0] : null;

            return [
                'valor' => isset($row['valor']) ? (float) str_replace(',', '.', (string) $row['valor']) : null,
                'data' => $row['data'] ?? null,
                'label' => $label,
                'ok' => true,
            ];
        } catch (\Throwable $e) {
            Log::warning("InvestimentoMercado Bacen {$codigo}: " . $e->getMessage());

            return ['valor' => null, 'data' => null, 'label' => $label, 'ok' => false];
        }
    }

    /**
     * @return array{ok: bool, data_base: ?string, titulos: array, erro: ?string}
     */
    private static function obterTitulosTesouro(bool $forceRefresh): array
    {
        $path = storage_path('app/' . self::CSV_PATH);
        $needsDownload = $forceRefresh || ! is_file($path) || (time() - filemtime($path)) > 86400;

        if ($needsDownload) {
            try {
                @mkdir(dirname($path), 0775, true);
                $r = Http::timeout(180)->withHeaders(['User-Agent' => 'SAS-Estoque/1.0'])
                    ->sink($path)
                    ->get(self::CSV_URL);
                if (! $r->successful() || ! is_file($path) || filesize($path) < 1000) {
                    if (is_file($path)) {
                        @unlink($path);
                    }

                    return [
                        'ok' => false,
                        'data_base' => null,
                        'titulos' => [],
                        'erro' => 'Falha ao baixar CSV do Tesouro Transparente.',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('InvestimentoMercado CSV download: ' . $e->getMessage());
                if (! is_file($path)) {
                    return [
                        'ok' => false,
                        'data_base' => null,
                        'titulos' => [],
                        'erro' => 'Download do Tesouro Transparente expirou ou falhou. Tente novamente em alguns minutos.',
                    ];
                }
            }
        }

        try {
            $parsed = self::parseCsvTitulos($path);

            return [
                'ok' => true,
                'data_base' => $parsed['data_base'],
                'titulos' => $parsed['titulos'],
                'erro' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('InvestimentoMercado CSV parse: ' . $e->getMessage());

            return [
                'ok' => false,
                'data_base' => null,
                'titulos' => [],
                'erro' => 'Erro ao ler cotações do Tesouro.',
            ];
        }
    }

    /**
     * @return array{data_base: ?string, titulos: array<int, array<string, mixed>>}
     */
    private static function parseCsvTitulos(string $path): array
    {
        $fh = fopen($path, 'r');
        if (! $fh) {
            throw new \RuntimeException('Não foi possível abrir CSV.');
        }

        fgetcsv($fh, 0, ';'); // cabeçalho
        $maxBase = '';
        $buffer = [];

        while (($row = fgetcsv($fh, 0, ';')) !== false) {
            if (count($row) < 6) {
                continue;
            }
            $tipo = trim((string) ($row[0] ?? ''));
            $dataBase = self::parseDataBr($row[2] ?? '');
            if (! $tipo || ! $dataBase) {
                continue;
            }
            if ($dataBase >= $maxBase) {
                if ($dataBase > $maxBase) {
                    $maxBase = $dataBase;
                    $buffer = [];
                }
                $taxaCompra = (float) str_replace(',', '.', trim((string) ($row[3] ?? '0')));
                $puCompra = (float) str_replace(',', '.', trim((string) ($row[5] ?? '0')));
                $buffer[] = [
                    'id' => md5($tipo . '|' . ($row[1] ?? '') . '|' . ($row[2] ?? '')),
                    'nome' => $tipo,
                    'tipo_sistema' => self::mapearTipoSistema($tipo),
                    'data_vencimento' => trim((string) ($row[1] ?? '')),
                    'data_base' => trim((string) ($row[2] ?? '')),
                    'taxa_compra_aa' => round($taxaCompra, 4),
                    'pu_compra' => round($puCompra, 2),
                    'liquidez' => self::liquidezPorTipo($tipo),
                ];
            }
        }
        fclose($fh);

        // Um registro por título + vencimento (evita duplicatas do CSV histórico)
        $uniq = [];
        foreach ($buffer as $t) {
            $k = $t['nome'] . '|' . $t['data_vencimento'];
            if (! isset($uniq[$k])) {
                $uniq[$k] = $t;
            }
        }
        $titulos = array_values($uniq);
        usort($titulos, fn ($a, $b) => strcmp($a['nome'], $b['nome']) ?: strcmp($a['data_vencimento'], $b['data_vencimento']));

        return [
            'data_base' => $maxBase ? self::formatDataBr($maxBase) : null,
            'titulos' => $titulos,
        ];
    }

    private static function parseDataBr(?string $s): ?string
    {
        $s = trim((string) $s);
        if (! preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }

    private static function formatDataBr(string $iso): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }

        return $iso;
    }

    private static function mapearTipoSistema(string $nomeTitulo): string
    {
        $n = mb_strtolower($nomeTitulo);
        if (str_contains($n, 'selic')) {
            return 'tesouro_selic';
        }
        if (str_contains($n, 'ipca') || str_contains($n, 'igpm')) {
            return 'tesouro_ipca';
        }
        if (str_contains($n, 'prefixado')) {
            return 'tesouro_prefixado';
        }

        return 'outros';
    }

    private static function liquidezPorTipo(string $nomeTitulo): string
    {
        return self::mapearTipoSistema($nomeTitulo) === 'tesouro_selic' ? 'alta' : 'media';
    }

    /**
     * Taxas sugeridas por tipo do sistema (para preenchimento automático).
     *
     * @param  array<int, array<string, mixed>>  $titulos
     * @return array<string, array<string, mixed>>
     */
    private static function montarSugestoesPorTipo(array $selic, array $cdi, array $titulos): array
    {
        $sug = [];
        if ($selic['valor'] ?? null) {
            $sug['tesouro_selic'] = [
                'taxa_anual' => $selic['valor'],
                'fonte' => 'Bacen — Meta Selic',
                'data' => $selic['data'] ?? null,
            ];
            $sug['cdb_liquidez'] = [
                'taxa_anual' => $cdi['valor'] ?? $selic['valor'],
                'fonte' => 'Bacen — CDI (referência CDB DI)',
                'data' => $cdi['data'] ?? $selic['data'] ?? null,
            ];
            $sug['fundo_di'] = [
                'taxa_anual' => $cdi['valor'] ?? $selic['valor'],
                'fonte' => 'Bacen — CDI (referência Fundo DI)',
                'data' => $cdi['data'] ?? $selic['data'] ?? null,
            ];
        }

        foreach (['tesouro_ipca', 'tesouro_prefixado'] as $tipo) {
            $candidatos = array_values(array_filter($titulos, fn ($t) => ($t['tipo_sistema'] ?? '') === $tipo));
            if (! $candidatos) {
                continue;
            }
            usort($candidatos, fn ($a, $b) => ($b['taxa_compra_aa'] ?? 0) <=> ($a['taxa_compra_aa'] ?? 0));
            $melhor = $candidatos[0];
            $sug[$tipo] = [
                'taxa_anual' => $melhor['taxa_compra_aa'],
                'fonte' => 'Tesouro Transparente — ' . $melhor['nome'] . ' (venc. ' . $melhor['data_vencimento'] . ')',
                'data' => $melhor['data_base'] ?? null,
                'titulo_id' => $melhor['id'],
            ];
        }

        return $sug;
    }
}
