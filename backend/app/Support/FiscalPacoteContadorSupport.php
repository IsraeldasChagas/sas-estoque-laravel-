<?php

namespace App\Support;

use App\Models\FiscalEmissaoConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Monta dados do pacote mensal para o contador (exportação; não substitui SPED). */
final class FiscalPacoteContadorSupport
{
    /** @return array{data_ini: string, data_fim: string} */
    public static function periodoFromMes(?string $mes): array
    {
        $mes = trim((string) $mes);
        if (! preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = now()->format('Y-m');
        }
        $ini = $mes . '-01';
        $fim = date('Y-m-t', strtotime($ini));

        return ['data_ini' => $ini, 'data_fim' => $fim];
    }

    /** @return array<string, mixed> */
    public static function preview(int $empresaId, string $dataIni, string $dataFim): array
    {
        $empresa = DB::table('empresas')->where('id', $empresaId)->first();
        if (! $empresa) {
            throw new \InvalidArgumentException('Empresa não encontrada.');
        }

        $f = ['empresa_id' => $empresaId, 'data_ini' => $dataIni, 'data_fim' => $dataFim . ' 23:59:59'];
        $visao = FiscalConsolidacaoSupport::visaoGeral($f);

        $vendasCount = Schema::hasTable('vendas')
            ? (int) DB::table('vendas')->where('empresa_id', $empresaId)
                ->where('data_venda', '>=', $dataIni)
                ->where('data_venda', '<=', $dataFim . ' 23:59:59')
                ->count()
            : 0;

        $nfCount = Schema::hasTable('notas_fiscais_entrada')
            ? (int) DB::table('notas_fiscais_entrada')->where('empresa_id', $empresaId)
                ->where('data_entrada', '>=', $dataIni)
                ->where('data_entrada', '<=', $dataFim)
                ->count()
            : 0;

        $nfceAut = Schema::hasTable('vendas')
            ? (int) DB::table('vendas')->where('empresa_id', $empresaId)
                ->where('data_venda', '>=', $dataIni)
                ->where('data_venda', '<=', $dataFim . ' 23:59:59')
                ->where('status_documento', 'autorizado')
                ->count()
            : 0;

        return [
            'empresa' => self::mapEmpresaPublic($empresa),
            'periodo' => ['inicio' => $dataIni, 'fim' => $dataFim],
            'contagens' => [
                'vendas' => $vendasCount,
                'notas_entrada' => $nfCount,
                'nfce_autorizadas' => $nfceAut,
            ],
            'visao_gerencial' => $visao,
            'disclaimer' => 'Pacote informativo para escrituração externa. Não é SPED, PGDAS nem substitui validação do contador.',
        ];
    }

    /** @return array<string, string> filename => content */
    public static function arquivosPacote(int $empresaId, string $dataIni, string $dataFim): array
    {
        $empresa = DB::table('empresas')->where('id', $empresaId)->first();
        if (! $empresa) {
            throw new \InvalidArgumentException('Empresa não encontrada.');
        }

        $f = ['empresa_id' => $empresaId, 'data_ini' => $dataIni, 'data_fim' => $dataFim . ' 23:59:59'];
        $periodoLabel = $dataIni . ' a ' . $dataFim;
        $cnpj = FiscalCadastroSupport::normalizarCnpj($empresa->cnpj ?? '') ?: 'sem-cnpj';

        $readme = self::gerarLeiaMe($empresa, $periodoLabel);
        $files = [
            'LEIA-ME.txt' => $readme,
            'empresa.json' => json_encode(self::mapEmpresaPublic($empresa), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'resumo_gerencial.json' => json_encode(FiscalConsolidacaoSupport::visaoGeral($f), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];

        if (ApuracaoFiscalSupport::moduloAtivo()) {
            try {
                $apuracao = ApuracaoFiscalSupport::calcular($empresaId, $dataIni, $dataFim);
                $files['apuracao_estimada.json'] = json_encode($apuracao, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } catch (\Throwable $e) {
                $files['apuracao_estimada.json'] = json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
        }

        $cfg = FiscalEmissaoConfig::query()->where('empresa_id', $empresaId)->first();
        $files['config_emissao.json'] = json_encode([
            'provider' => $cfg?->provider,
            'environment' => $cfg?->environment,
            'emitir_nfce_pdv' => (bool) ($cfg?->emitir_nfce_pdv ?? false),
            'is_active' => (bool) ($cfg?->is_active ?? false),
            'serie_nfce' => $cfg?->serie_nfce,
            'observacao' => 'Sem tokens ou certificados — apenas metadados.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $files['vendas.csv'] = self::csvFromRows(self::queryVendas($empresaId, $dataIni, $dataFim));
        $files['vendas_itens.csv'] = self::csvFromRows(self::queryVendaItens($empresaId, $dataIni, $dataFim));
        $files['notas_entrada.csv'] = self::csvFromRows(self::queryNotasEntrada($empresaId, $dataIni, $dataFim));
        $files['eventos_fiscais.csv'] = self::csvFromRows(self::queryEventos($empresaId, $dataIni, $dataFim));
        $files['logs_emissao_nfce.csv'] = self::csvFromRows(self::queryLogsEmissao($empresaId, $dataIni, $dataFim));

        $manifest = [
            'gerado_em' => now()->toIso8601String(),
            'sistema' => 'SAS-Estoque',
            'empresa_id' => $empresaId,
            'cnpj' => $cnpj,
            'periodo' => ['inicio' => $dataIni, 'fim' => $dataFim],
            'arquivos' => array_keys($files),
        ];
        $files['manifest.json'] = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return $files;
    }

    public static function criarZip(array $files): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Extensão ZIP do PHP não disponível no servidor.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'sas-pacote-');
        if ($tmp === false) {
            throw new \RuntimeException('Não foi possível criar arquivo temporário.');
        }
        @unlink($tmp);
        $zipPath = $tmp . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar o ZIP.');
        }
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $binary = file_get_contents($zipPath);
        @unlink($zipPath);
        if ($binary === false) {
            throw new \RuntimeException('Falha ao ler ZIP gerado.');
        }

        return $binary;
    }

    /** @return array<string, mixed> */
    private static function mapEmpresaPublic(object $empresa): array
    {
        return [
            'id' => (int) $empresa->id,
            'razao_social' => $empresa->razao_social ?? null,
            'nome_fantasia' => $empresa->nome_fantasia ?? null,
            'cnpj' => $empresa->cnpj ?? null,
            'inscricao_estadual' => $empresa->inscricao_estadual ?? null,
            'regime_tributario' => $empresa->regime_tributario ?? null,
            'uf' => $empresa->uf ?? null,
            'municipio' => $empresa->municipio ?? null,
        ];
    }

    private static function gerarLeiaMe(object $empresa, string $periodo): string
    {
        $nome = $empresa->razao_social ?? $empresa->nome_fantasia ?? 'Empresa';

        return <<<TXT
PACOTE CONTADOR — SAS-Estoque
=============================

Empresa: {$nome}
CNPJ: {$empresa->cnpj}
Período: {$periodo}

Conteúdo:
- vendas.csv / vendas_itens.csv — saídas registradas no PDV/fiscal interno
- notas_entrada.csv — entradas fiscais (compras)
- eventos_fiscais.csv — movimentações com evento
- logs_emissao_nfce.csv — tentativas/emissões Focus (metadados)
- resumo_gerencial.json / apuracao_estimada.json — visão M7 (ESTIMATIVA)

IMPORTANTE:
Este pacote NÃO é SPED, EFD, PGDAS, DCTF nem escrituração oficial.
Use os XMLs oficiais da Focus/SEFAZ quando disponíveis para lançamento definitivo.
Valide sempre com o contador responsável.

TXT;
    }

    /** @param list<object|array<string, mixed>> $rows */
    private static function csvFromRows(array $rows): string
    {
        if ($rows === []) {
            return "vazio\n";
        }
        $first = $rows[0];
        $headers = array_keys(is_array($first) ? $first : get_object_vars($first));
        $out = [implode(';', $headers)];
        foreach ($rows as $row) {
            $arr = is_array($row) ? $row : get_object_vars($row);
            $cells = [];
            foreach ($headers as $h) {
                $v = $arr[$h] ?? '';
                if (is_array($v) || is_object($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                }
                $cells[] = self::csvCell((string) $v);
            }
            $out[] = implode(';', $cells);
        }

        return implode("\n", $out) . "\n";
    }

    private static function csvCell(string $v): string
    {
        $v = str_replace(["\r", "\n"], ' ', $v);
        if (str_contains($v, ';') || str_contains($v, '"')) {
            return '"' . str_replace('"', '""', $v) . '"';
        }

        return $v;
    }

    /** @return list<object> */
    private static function queryVendas(int $empresaId, string $dataIni, string $dataFim): array
    {
        if (! Schema::hasTable('vendas')) {
            return [];
        }

        return DB::table('vendas')
            ->where('empresa_id', $empresaId)
            ->where('data_venda', '>=', $dataIni)
            ->where('data_venda', '<=', $dataFim . ' 23:59:59')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** @return list<object> */
    private static function queryVendaItens(int $empresaId, string $dataIni, string $dataFim): array
    {
        if (! Schema::hasTable('venda_itens') || ! Schema::hasTable('vendas')) {
            return [];
        }

        return DB::table('venda_itens')
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->where('vendas.empresa_id', $empresaId)
            ->where('vendas.data_venda', '>=', $dataIni)
            ->where('vendas.data_venda', '<=', $dataFim . ' 23:59:59')
            ->select('venda_itens.*', 'vendas.data_venda')
            ->orderBy('venda_itens.id')
            ->get()
            ->all();
    }

    /** @return list<object> */
    private static function queryNotasEntrada(int $empresaId, string $dataIni, string $dataFim): array
    {
        if (! Schema::hasTable('notas_fiscais_entrada')) {
            return [];
        }

        return DB::table('notas_fiscais_entrada')
            ->where('empresa_id', $empresaId)
            ->where('data_entrada', '>=', $dataIni)
            ->where('data_entrada', '<=', $dataFim)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** @return list<object> */
    private static function queryEventos(int $empresaId, string $dataIni, string $dataFim): array
    {
        if (! Schema::hasTable('eventos_fiscais')) {
            return [];
        }

        return DB::table('eventos_fiscais')
            ->where('empresa_id', $empresaId)
            ->where('data_evento', '>=', $dataIni)
            ->where('data_evento', '<=', $dataFim . ' 23:59:59')
            ->orderBy('id')
            ->limit(5000)
            ->get()
            ->all();
    }

    /** @return list<object> */
    private static function queryLogsEmissao(int $empresaId, string $dataIni, string $dataFim): array
    {
        if (! Schema::hasTable('fiscal_emissao_logs')) {
            return [];
        }

        return DB::table('fiscal_emissao_logs')
            ->where('empresa_id', $empresaId)
            ->where('created_at', '>=', $dataIni)
            ->where('created_at', '<=', $dataFim . ' 23:59:59')
            ->orderBy('id')
            ->get()
            ->all();
    }
}
