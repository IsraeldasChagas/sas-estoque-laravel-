<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Support\Rh\RhAcesso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RhDashboardController extends Controller
{
    public function stats(Request $request)
    {
        if (! RhAcesso::pode($request, 'rh.ver')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $cand = DB::table('rh_candidatos')->selectRaw(
            "COUNT(*) as total,
             SUM(CASE WHEN status = 'em_teste' THEN 1 ELSE 0 END) as em_teste,
             SUM(CASE WHEN status IN ('aprovado', 'em_contratacao') THEN 1 ELSE 0 END) as aprovados"
        )->first();

        return response()->json([
            'vagas_abertas' => DB::table('rh_vagas')->where('status', 'aberta')->count(),
            'candidatos_total' => (int) ($cand->total ?? 0),
            'candidatos_em_teste' => (int) ($cand->em_teste ?? 0),
            'entrevistas_total' => DB::table('rh_entrevistas')->count(),
            'aprovados' => (int) ($cand->aprovados ?? 0),
        ])->header('Access-Control-Allow-Origin', '*');
    }
}

