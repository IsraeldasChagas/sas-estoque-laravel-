<?php

namespace App\Services\Delivery;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeliveryAccessService
{
    public const BYPASS_PERFIS = ['ADMIN', 'GERENTE', 'ASSISTENTE', 'ASSISTENTE_ADMINISTRATIVO'];

    public function usuario(Request $request): object
    {
        $uid = $request->header('X-Usuario-Id');
        if (! $uid && $request->isMethod('GET')) {
            $uid = $request->query('x_usuario_id');
        }

        $usuario = DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first();
        abort_unless($usuario, 401, 'Usuário não identificado.');

        return $usuario;
    }

    public function autorizar(Request $request, string $modulo): object
    {
        $usuario = $this->usuario($request);
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));

        if (in_array($perfil, self::BYPASS_PERFIS, true)) {
            return $usuario;
        }

        $permissoes = $usuario->permissoes_menu ?? null;
        if (is_string($permissoes)) {
            $permissoes = json_decode($permissoes, true);
        }

        abort_unless(is_array($permissoes) && $this->temModulo($permissoes, $modulo), 403, 'Sem permissão para este módulo.');

        return $usuario;
    }

    /** @param  array<int, string>  $permissoes */
    private function temModulo(array $permissoes, string $modulo): bool
    {
        foreach ($this->modulosAceitos($modulo) as $aceito) {
            if (in_array($aceito, $permissoes, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function modulosAceitos(string $modulo): array
    {
        $aliases = [
            'deliveryProdutos' => ['deliveryProdutos', 'cardapioItens'],
            'deliveryCategorias' => ['deliveryCategorias', 'cardapioCategorias'],
            'deliveryCatalogo' => ['deliveryCatalogo', 'cardapioConsulta'],
            'deliveryAdicionais' => ['deliveryAdicionais', 'cardapioAdicionais'],
        ];

        return $aliases[$modulo] ?? [$modulo];
    }

    public function unidadeId(Request $request, object $usuario, ?array $payload = null): ?int
    {
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));

        if ($perfil === 'ADMIN') {
            if (is_array($payload) && ! empty($payload['unidade_id'])) {
                return (int) $payload['unidade_id'];
            }
            if ($request->filled('unidade_id')) {
                return (int) $request->input('unidade_id', $request->query('unidade_id'));
            }
        }

        return ! empty($usuario->unidade_id) ? (int) $usuario->unidade_id : null;
    }

    public function exigirUnidade(Request $request, object $usuario, ?array $payload = null): int
    {
        $unidadeId = $this->unidadeId($request, $usuario, $payload);
        abort_unless($unidadeId, 422, 'unidade_id é obrigatório.');

        return $unidadeId;
    }

    public function aplicarEscopo($query, object $usuario, Request $request, string $coluna = 'unidade_id'): void
    {
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));

        if ($perfil === 'ADMIN' && $request->filled('unidade_id')) {
            $query->where($coluna, (int) $request->query('unidade_id'));
        } elseif ($perfil !== 'ADMIN' && ! empty($usuario->unidade_id)) {
            $query->where($coluna, (int) $usuario->unidade_id);
        }
    }

    public function autorizarRegistro(object $usuario, object $registro, string $mensagem = 'Sem permissão para este registro.'): void
    {
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));
        if ($perfil !== 'ADMIN' && ! empty($usuario->unidade_id) && (int) ($registro->unidade_id ?? 0) !== (int) $usuario->unidade_id) {
            abort(403, $mensagem);
        }
    }

    public function verificarTabelas(): void
    {
        abort_unless(
            Schema::hasTable('dlv_loja_config')
            && Schema::hasTable('dlv_categorias')
            && Schema::hasTable('dlv_produtos')
            && Schema::hasTable('dlv_adicionais')
            && Schema::hasTable('dlv_pedidos'),
            503,
            'Módulo Delivery indisponível. Execute as migrations.'
        );
    }
}
