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

        $abertas = DB::table('rh_vagas')->where('status', 'aberta')->count();
        $totalCandidatos = DB::table('rh_candidatos')->count();
        $novos = DB::table('rh_candidatos')->where('status', 'novo')->count();
        $entrevistas = DB::table('rh_entrevistas')->count();
        $aprovados = DB::table('rh_candidatos')->whereIn('status', ['aprovado', 'em_contratacao'])->count();

        return response()->json([
            'vagas_abertas' => $abertas,
            'candidatos_total' => $totalCandidatos,
            'candidatos_novos' => $novos,
            'entrevistas_total' => $entrevistas,
            'aprovados' => $aprovados,
        ])->header('Access-Control-Allow-Origin', '*');
    }
}

