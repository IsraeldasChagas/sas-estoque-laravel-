<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

abstract class IntegrationBaseController extends Controller
{
    protected function json(array $data, int $code = 200): Response
    {
        return response()->json($data, $code)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }

    protected function corsPreflight(): Response
    {
        return $this->json([]);
    }

    protected function authUsuario(Request $request): ?object
    {
        $uid = $request->header('X-Usuario-Id');
        if (! $uid) {
            return null;
        }

        return DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first();
    }

    protected function podeConfigurar(?object $usuario): bool
    {
        if (! $usuario) {
            return false;
        }
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));

        return in_array($perfil, ['ADMIN', 'ADMINISTRADOR'], true);
    }

    protected function podeVisualizar(?object $usuario): bool
    {
        if (! $usuario) {
            return false;
        }
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));

        return in_array($perfil, ['ADMIN', 'ADMINISTRADOR', 'GERENTE'], true);
    }
}
