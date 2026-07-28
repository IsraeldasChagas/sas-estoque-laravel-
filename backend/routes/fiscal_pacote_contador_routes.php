<?php

/**
 * Pacote mensal para contador — exportação ZIP (CSV + JSON).
 */

use App\Support\FiscalPacoteContadorSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$fiscalPcCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

$fiscalPcAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');

    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

$fiscalPcPodeVer = function ($u) {
    if (! $u) {
        return false;
    }
    $p = strtoupper(trim((string) ($u->perfil ?? '')));

    return in_array($p, ['ADMIN', 'ADMINISTRADOR', 'GERENTE'], true);
};

$fiscalPcJson = fn ($data, int $code = 200) => response()->json($data, $code)
    ->header('Access-Control-Allow-Origin', '*');

foreach ([
    '/fiscal/pacote-contador/meta',
    '/fiscal/pacote-contador/preview',
    '/fiscal/pacote-contador/download',
] as $p) {
    Route::options($p, $fiscalPcCors);
}

Route::get('/fiscal/pacote-contador/meta', function () use ($fiscalPcJson) {
    return $fiscalPcJson([
        'titulo' => 'Pacote contador',
        'descricao' => 'Exportação mensal por CNPJ para escrituração externa (não substitui SPED/PGDAS).',
        'formato' => 'zip',
        'parametros' => ['empresa_id' => 'obrigatório', 'mes' => 'YYYY-MM (opcional, padrão mês atual)'],
    ]);
});

Route::get('/fiscal/pacote-contador/preview', function (Request $request) use ($fiscalPcAuth, $fiscalPcPodeVer, $fiscalPcJson) {
    $u = $fiscalPcAuth($request);
    if (! $fiscalPcPodeVer($u)) {
        return $fiscalPcJson(['error' => 'Não autorizado'], 401);
    }
    $empresaId = (int) $request->query('empresa_id', 0);
    if ($empresaId <= 0) {
        return $fiscalPcJson(['error' => 'Informe empresa_id'], 422);
    }
    $periodo = FiscalPacoteContadorSupport::periodoFromMes($request->query('mes'));
    try {
        return $fiscalPcJson(FiscalPacoteContadorSupport::preview($empresaId, $periodo['data_ini'], $periodo['data_fim']));
    } catch (\Throwable $e) {
        return $fiscalPcJson(['error' => $e->getMessage()], 422);
    }
});

Route::get('/fiscal/pacote-contador/download', function (Request $request) use ($fiscalPcAuth, $fiscalPcPodeVer) {
    $u = $fiscalPcAuth($request);
    if (! $fiscalPcPodeVer($u)) {
        return response()->json(['error' => 'Não autorizado'], 401)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $empresaId = (int) $request->query('empresa_id', 0);
    if ($empresaId <= 0) {
        return response()->json(['error' => 'Informe empresa_id'], 422)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $periodo = FiscalPacoteContadorSupport::periodoFromMes($request->query('mes'));
    try {
        $files = FiscalPacoteContadorSupport::arquivosPacote($empresaId, $periodo['data_ini'], $periodo['data_fim']);
        $zip = FiscalPacoteContadorSupport::criarZip($files);
        $empresa = DB::table('empresas')->where('id', $empresaId)->first();
        $cnpj = preg_replace('/\D/', '', (string) ($empresa->cnpj ?? 'empresa'));
        $mes = substr($periodo['data_ini'], 0, 7);
        $filename = 'pacote-contador-' . $cnpj . '-' . $mes . '.zip';

        return response($zip, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 500)
            ->header('Access-Control-Allow-Origin', '*');
    }
});
