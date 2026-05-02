<?php

namespace App\Support\Rh;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RhAcesso
{
    /**
     * Regras simples e compatíveis com o SAS atual:
     * - Se o usuário tiver permissoes_menu (json array), ele precisa conter pelo menos 1 módulo RH permitido.
     * - Se permissoes_menu for null, usa fallback por perfil (ADMIN, GERENTE, ASSISTENTE_ADMINISTRATIVO).
     *
     * As permissões "rh.*" pedidas no requisito são mapeadas para módulos do menu do frontend.
     */
    public static function pode(Request $request, string $perm): bool
    {
        $uid = $request->header('X-Usuario-Id');
        if (! $uid) return false;

        $u = DB::table('usuarios')->where('id', $uid)->first();
        if (! $u || (int) ($u->ativo ?? 0) !== 1) return false;

        $perfil = strtoupper(trim((string) ($u->perfil ?? '')));

        $pm = $u->permissoes_menu ?? null;
        if (is_string($pm)) {
            $decoded = json_decode($pm, true);
            $pm = is_array($decoded) ? $decoded : null;
        }

        $map = [
            'rh.ver' => ['rhDashboard', 'rhVagas', 'rhCandidatos', 'rhEntrevistas', 'rhBancoTalentos', 'rhRelatorios', 'rhFolhaPonto', 'rhConfig'],
            'rh.vagas' => ['rhVagas'],
            'rh.candidatos' => ['rhCandidatos', 'rhBancoTalentos'],
            'rh.documentos' => ['rhCandidatos'],
            'rh.config' => ['rhConfig'],
            'rh.folha_ponto' => ['rhFolhaPonto', 'funcionarios', 'rhRelatorios'],
        ];
        $needAny = $map[$perm] ?? $map['rh.ver'];

        if (is_array($pm) && count($pm)) {
            foreach ($needAny as $key) {
                if (in_array($key, $pm, true)) return true;
            }
            return false;
        }

        // fallback perfil (quando usuario não tem menu customizado)
        if (in_array($perfil, ['ADMIN', 'GERENTE', 'ASSISTENTE_ADMINISTRATIVO'], true)) {
            return true;
        }

        return false;
    }
}

