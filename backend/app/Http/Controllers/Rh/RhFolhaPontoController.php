<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Support\Rh\RhAcesso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RhFolhaPontoController extends Controller
{
    private const MESES = [
        1 => 'JANEIRO', 2 => 'FEVEREIRO', 3 => 'MARÇO', 4 => 'ABRIL',
        5 => 'MAIO', 6 => 'JUNHO', 7 => 'JULHO', 8 => 'AGOSTO',
        9 => 'SETEMBRO', 10 => 'OUTUBRO', 11 => 'NOVEMBRO', 12 => 'DEZEMBRO',
    ];

    private const DIAS_SEMANA = [
        0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta',
        4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado',
    ];

    /** Abreviações para o PDF retrato caber em uma folha. */
    private const DIAS_SEMANA_PDF = [
        0 => 'Dom', 1 => 'Seg', 2 => 'Ter', 3 => 'Qua',
        4 => 'Qui', 5 => 'Sex', 6 => 'Sáb',
    ];

    public function index(Request $request)
    {
        if (! RhAcesso::pode($request, 'rh.folha_ponto')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        if (! Schema::hasTable('rh_folhas_ponto')) {
            return response()->json([])->header('Access-Control-Allow-Origin', '*');
        }

        $q = DB::table('rh_folhas_ponto')
            ->leftJoin('unidades', 'rh_folhas_ponto.unidade_id', '=', 'unidades.id')
            ->select('rh_folhas_ponto.*', 'unidades.nome as unidade_nome')
            ->orderByDesc('rh_folhas_ponto.ano')
            ->orderByDesc('rh_folhas_ponto.mes')
            ->orderByDesc('rh_folhas_ponto.id');

        if ($request->filled('ano')) {
            $q->where('rh_folhas_ponto.ano', (int) $request->query('ano'));
        }
        if ($request->filled('mes')) {
            $q->where('rh_folhas_ponto.mes', (int) $request->query('mes'));
        }

        $rows = $q->get();

        return response()->json($rows->map(function ($r) {
            $copy = (array) $r;
            unset($copy['dias_json']);

            return (object) $copy;
        }))->header('Access-Control-Allow-Origin', '*');
    }

    public function show(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.folha_ponto')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        if (! Schema::hasTable('rh_folhas_ponto')) {
            return response()->json(['error' => 'Módulo indisponível'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        $row = DB::table('rh_folhas_ponto')
            ->leftJoin('unidades', 'rh_folhas_ponto.unidade_id', '=', 'unidades.id')
            ->where('rh_folhas_ponto.id', $id)
            ->select('rh_folhas_ponto.*', 'unidades.nome as unidade_nome')
            ->first();
        if (! $row) {
            return response()->json(['error' => 'Não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        $out = (array) $row;
        $out['dias'] = $this->decodeDiasJson($row->dias_json);

        return response()->json($out)->header('Access-Control-Allow-Origin', '*');
    }

    public function store(Request $request)
    {
        if (! RhAcesso::pode($request, 'rh.folha_ponto')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        if (! Schema::hasTable('rh_folhas_ponto')) {
            return response()->json(['error' => 'Execute a migration: rh_folhas_ponto'], 503)->header('Access-Control-Allow-Origin', '*');
        }

        $data = $this->validatedPayload($request);
        $uid = $request->header('X-Usuario-Id');

        $id = DB::table('rh_folhas_ponto')->insertGetId([
            'ano' => $data['ano'],
            'mes' => $data['mes'],
            'unidade_id' => $data['unidade_id'],
            'empresa_nome' => $data['empresa_nome'],
            'empresa_endereco' => $data['empresa_endereco'],
            'empresa_cep' => $data['empresa_cep'],
            'empresa_cnpj' => $data['empresa_cnpj'],
            'empresa_cidade_ano' => $data['empresa_cidade_ano'],
            'empresa_email' => $data['empresa_email'],
            'funcionario_nome' => $data['funcionario_nome'],
            'funcionario_cpf' => $data['funcionario_cpf'],
            'funcionario_cargo' => $data['funcionario_cargo'],
            'funcionario_ctps' => $data['funcionario_ctps'],
            'dias_json' => json_encode($data['dias'], JSON_UNESCAPED_UNICODE),
            'usuario_id' => $uid ? (int) $uid : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->show($request, $id);
    }

    public function update(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.folha_ponto')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        if (! Schema::hasTable('rh_folhas_ponto')) {
            return response()->json(['error' => 'Módulo indisponível'], 503)->header('Access-Control-Allow-Origin', '*');
        }

        if (! DB::table('rh_folhas_ponto')->where('id', $id)->exists()) {
            return response()->json(['error' => 'Não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        $data = $this->validatedPayload($request);

        DB::table('rh_folhas_ponto')->where('id', $id)->update([
            'ano' => $data['ano'],
            'mes' => $data['mes'],
            'unidade_id' => $data['unidade_id'],
            'empresa_nome' => $data['empresa_nome'],
            'empresa_endereco' => $data['empresa_endereco'],
            'empresa_cep' => $data['empresa_cep'],
            'empresa_cnpj' => $data['empresa_cnpj'],
            'empresa_cidade_ano' => $data['empresa_cidade_ano'],
            'empresa_email' => $data['empresa_email'],
            'funcionario_nome' => $data['funcionario_nome'],
            'funcionario_cpf' => $data['funcionario_cpf'],
            'funcionario_cargo' => $data['funcionario_cargo'],
            'funcionario_ctps' => $data['funcionario_ctps'],
            'dias_json' => json_encode($data['dias'], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        return $this->show($request, $id);
    }

    public function destroy(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.folha_ponto')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        if (! Schema::hasTable('rh_folhas_ponto')) {
            return response()->json(['error' => 'Módulo indisponível'], 503)->header('Access-Control-Allow-Origin', '*');
        }

        DB::table('rh_folhas_ponto')->where('id', $id)->delete();

        return response()->json(['ok' => true])->header('Access-Control-Allow-Origin', '*');
    }

    public function pdf(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.folha_ponto')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        if (! Schema::hasTable('rh_folhas_ponto')) {
            return response()->json(['error' => 'Módulo indisponível'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        $row = DB::table('rh_folhas_ponto')
            ->leftJoin('unidades', 'rh_folhas_ponto.unidade_id', '=', 'unidades.id')
            ->where('rh_folhas_ponto.id', $id)
            ->select('rh_folhas_ponto.*', 'unidades.nome as unidade_nome')
            ->first();
        if (! $row) {
            return response()->json(['error' => 'Não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        $dias = $this->decodeDiasJson($row->dias_json);
        $h = static fn (?string $s): string => htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $ano = (int) $row->ano;
        $mes = (int) $row->mes;
        $mesNome = self::MESES[$mes] ?? 'MÊS';
        $nDias = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);

        $rowsHtml = '';
        for ($d = 1; $d <= $nDias; $d++) {
            $ts = strtotime(sprintf('%04d-%02d-%02d', $ano, $mes, $d));
            $w = (int) date('w', $ts);
            $diaSemana = self::DIAS_SEMANA_PDF[$w] ?? '';
            $cel = $dias[$d - 1] ?? [];
            $rowsHtml .= '<tr>'
                . '<td class="c-dia">' . $h($diaSemana . ' ' . $d) . '</td>'
                . '<td>' . $h($cel['entrada'] ?? '') . '</td>'
                . '<td>' . $h($cel['intervalo_inicio'] ?? '') . '</td>'
                . '<td>' . $h($cel['intervalo_fim'] ?? '') . '</td>'
                . '<td>' . $h($cel['saida'] ?? '') . '</td>'
                . '<td>' . $h($cel['hora_extra'] ?? '') . '</td>'
                . '<td>' . $h($cel['assinatura'] ?? '') . '</td>'
                . '</tr>';
        }

        $periodo = $h($mesNome . '/' . $ano);
        $end = $h($row->empresa_endereco ?? '');
        $cep = $h($row->empresa_cep ?? '');
        $cnpj = $h($row->empresa_cnpj ?? '');
        $cidade = $h($row->empresa_cidade_ano ?? '');

        $nome = $h($row->funcionario_nome ?? '');
        $cpf = $h($row->funcionario_cpf ?? '');
        $cargo = $h($row->funcionario_cargo ?? '');
        $ctps = $h($row->funcionario_ctps ?? '');
        $unidadeLinha = trim((string) ($row->unidade_nome ?? '')) !== ''
            ? '<div style="margin-top:2px;font-weight:bold;">Unidade: ' . $h($row->unidade_nome) . '</div>'
            : '';

        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8" />
<style>
/* 8pt, margens e espaçamento simples — 30mm no topo */
@page { margin: 30mm 8mm 12mm 8mm; }
html, body { width: 100%; margin: 0; padding: 0; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; line-height: 1.3; color: #111; }
.sheet { width: 100%; box-sizing: border-box; padding: 0; margin: 0; }
.top { text-align: center; font-size: 8pt; line-height: 1.35; margin: 0 0 6px 0; padding: 0; }
.top > div { margin: 0 0 2px 0; padding: 0; }
.titulo { text-align: center; font-weight: bold; font-size: 8pt; margin: 6px 0 6px; padding: 0; }
.func { font-size: 8pt; margin: 0 0 8px 0; line-height: 1.35; padding: 0; text-align: center; }
.func-line { margin: 2px 0 0 0; padding: 0; }
.tbl-wrap { width: 100%; margin: 0; padding: 0; box-sizing: border-box; }
table.fp { width: 100%; border-collapse: collapse; font-size: 8pt; table-layout: fixed; }
th, td { border: 1px solid #111; padding: 4px 5px; vertical-align: middle; word-wrap: break-word; }
th { background: #e8e8e8; font-weight: bold; text-align: center; line-height: 1.25; font-size: 8pt; }
.fp td { line-height: 1.25; }
.c-dia { text-align: left; white-space: nowrap; width: 12%; }
th:nth-child(2), td:nth-child(2) { width: 11%; text-align: center; }
th:nth-child(3), td:nth-child(3) { width: 12%; text-align: center; }
th:nth-child(4), td:nth-child(4) { width: 12%; text-align: center; }
th:nth-child(5), td:nth-child(5) { width: 11%; text-align: center; }
th:nth-child(6), td:nth-child(6) { width: 10%; text-align: center; }
th:nth-child(7), td:nth-child(7) { width: 32%; text-align: left; }
</style></head><body>
<div class="sheet">
<div class="top">'
            . ($end !== '' ? '<div>' . $end . '</div>' : '')
            . ($cep !== '' ? '<div>CEP: ' . $cep . '</div>' : '')
            . ($cnpj !== '' ? '<div>CNPJ: ' . $cnpj . '</div>' : '')
            . ($cidade !== '' ? '<div>' . $cidade . '</div>' : '')
            . $unidadeLinha
            . '</div>
<div class="titulo">Folha de Ponto - Período ' . $periodo . '</div>
<div class="func"><strong>FUNCIONÁRIO (a)</strong>
<div class="func-line">Nome: ' . $nome . ' &nbsp;&nbsp; CPF: ' . $cpf . '</div>
<div class="func-line">Cargo: ' . $cargo . ' &nbsp;&nbsp; CTPS: ' . $ctps . '</div>
</div>
<div class="tbl-wrap">
<table class="fp">
<thead><tr>
<th>Dia</th>
<th>Entrada</th>
<th>Início do<br/>intervalo</th>
<th>Fim do<br/>intervalo</th>
<th>Saída</th>
<th>Hora extra</th>
<th>Assinatura do<br/>empregado (a)</th>
</tr></thead>
<tbody>' . $rowsHtml . '</tbody>
</table>
</div>
</div>
</body></html>';

        try {
            $dompdf = new \Dompdf\Dompdf();
            $options = $dompdf->getOptions();
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            $dompdf->setOptions($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfOutput = $dompdf->output();
            $fn = 'folha-ponto-' . $id . '.pdf';

            return response($pdfOutput, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $fn . '"')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id')
                ->header('Content-Length', (string) strlen($pdfOutput));
        } catch (\Throwable $e) {
            \Log::error('RhFolhaPonto pdf: ' . $e->getMessage());

            return response()->json(['error' => 'Erro ao gerar PDF'], 500)
                ->header('Access-Control-Allow-Origin', '*');
        }
    }

    /**
     * @return array<int, array{entrada?:string,intervalo_inicio?:string,intervalo_fim?:string,saida?:string,hora_extra?:string,assinatura?:string}>
     */
    private function decodeDiasJson(?string $json): array
    {
        if (! $json) {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{ano:int,mes:int,unidade_id:?int,empresa_nome:?string,empresa_endereco:?string,empresa_cep:?string,empresa_cnpj:?string,empresa_cidade_ano:?string,empresa_email:?string,funcionario_nome:string,funcionario_cpf:?string,funcionario_cargo:?string,funcionario_ctps:?string,dias:array}
     */
    private function validatedPayload(Request $request): array
    {
        $v = $request->validate([
            'ano' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'unidade_id' => 'nullable|integer|exists:unidades,id',
            'empresa_nome' => 'nullable|string|max:180',
            'empresa_endereco' => 'nullable|string|max:400',
            'empresa_cep' => 'nullable|string|max:40',
            'empresa_cnpj' => 'nullable|string|max:40',
            'empresa_cidade_ano' => 'nullable|string|max:140',
            'empresa_email' => 'nullable|string|max:180',
            'funcionario_nome' => 'required|string|max:200',
            'funcionario_cpf' => 'nullable|string|max:32',
            'funcionario_cargo' => 'nullable|string|max:140',
            'funcionario_ctps' => 'nullable|string|max:80',
            'dias' => 'required|array|min:28|max:31',
            'dias.*.entrada' => 'nullable|string|max:20',
            'dias.*.intervalo_inicio' => 'nullable|string|max:20',
            'dias.*.intervalo_fim' => 'nullable|string|max:20',
            'dias.*.saida' => 'nullable|string|max:20',
            'dias.*.hora_extra' => 'nullable|string|max:20',
            'dias.*.assinatura' => 'nullable|string|max:80',
        ]);

        $ano = (int) $v['ano'];
        $mes = (int) $v['mes'];
        $n = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
        $diasIn = $v['dias'];
        if (count($diasIn) !== $n) {
            throw ValidationException::withMessages([
                'dias' => ['O array dias deve ter exatamente ' . $n . ' posições para o mês ' . $mes . '/' . $ano . '.'],
            ]);
        }

        $dias = [];
        foreach ($diasIn as $item) {
            $dias[] = [
                'entrada' => isset($item['entrada']) ? (string) $item['entrada'] : '',
                'intervalo_inicio' => isset($item['intervalo_inicio']) ? (string) $item['intervalo_inicio'] : '',
                'intervalo_fim' => isset($item['intervalo_fim']) ? (string) $item['intervalo_fim'] : '',
                'saida' => isset($item['saida']) ? (string) $item['saida'] : '',
                'hora_extra' => isset($item['hora_extra']) ? (string) $item['hora_extra'] : '',
                'assinatura' => isset($item['assinatura']) ? (string) $item['assinatura'] : '',
            ];
        }

        $unidadeId = isset($v['unidade_id']) && $v['unidade_id'] !== null && $v['unidade_id'] !== ''
            ? (int) $v['unidade_id']
            : null;

        return [
            'ano' => $ano,
            'mes' => $mes,
            'unidade_id' => $unidadeId,
            'empresa_nome' => $v['empresa_nome'] ?? null,
            'empresa_endereco' => $v['empresa_endereco'] ?? null,
            'empresa_cep' => $v['empresa_cep'] ?? null,
            'empresa_cnpj' => $v['empresa_cnpj'] ?? null,
            'empresa_cidade_ano' => $v['empresa_cidade_ano'] ?? null,
            'empresa_email' => $v['empresa_email'] ?? null,
            'funcionario_nome' => $v['funcionario_nome'],
            'funcionario_cpf' => $v['funcionario_cpf'] ?? null,
            'funcionario_cargo' => $v['funcionario_cargo'] ?? null,
            'funcionario_ctps' => $v['funcionario_ctps'] ?? null,
            'dias' => $dias,
        ];
    }
}
