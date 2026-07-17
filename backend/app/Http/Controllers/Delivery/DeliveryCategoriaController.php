<?php

namespace App\Http\Controllers\Delivery;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeliveryCategoriaController extends DeliveryBaseController
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryCategorias');
        $query = DB::table('dlv_categorias')
            ->select('dlv_categorias.*')
            ->selectSub(function ($query) {
                $query->from('dlv_produtos')
                    ->selectRaw('count(*)')
                    ->whereColumn('dlv_produtos.categoria_id', 'dlv_categorias.id');
            }, 'product_count');
        $this->access->aplicarEscopo($query, $usuario, $request);

        if ($request->has('ativo')) {
            $query->where('ativo', filter_var($request->query('ativo'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }

        if ($busca = trim((string) $request->query('busca', ''))) {
            $query->where('nome', 'like', '%'.$busca.'%');
        }

        $items = $query->orderBy('ordem')->orderBy('nome')->get();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryCategorias');
        $data = $this->validar($request);
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $data);
        $data['nome'] = trim($data['nome']);
        if ($data['nome'] === '') {
            throw ValidationException::withMessages(['nome' => 'O nome da categoria é obrigatório.']);
        }
        $this->garantirNomeUnico($unidadeId, $data['nome']);
        $agora = now();

        $id = DB::table('dlv_categorias')->insertGetId([
            'unidade_id' => $unidadeId,
            'nome' => $data['nome'],
            'ordem' => (int) ($data['ordem'] ?? 0),
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        return response()->json(DB::table('dlv_categorias')->where('id', $id)->first(), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryCategorias');
        $row = DB::table('dlv_categorias')->where('id', $id)->first();
        abort_unless($row, 404, 'Categoria não encontrada.');
        $this->access->autorizarRegistro($usuario, $row);

        return response()->json($row);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryCategorias');
        $row = DB::table('dlv_categorias')->where('id', $id)->first();
        abort_unless($row, 404, 'Categoria não encontrada.');
        $this->access->autorizarRegistro($usuario, $row);
        $data = $this->validar($request, false);
        if (array_key_exists('nome', $data)) {
            $data['nome'] = trim($data['nome']);
            if ($data['nome'] === '') {
                throw ValidationException::withMessages(['nome' => 'O nome da categoria é obrigatório.']);
            }
            $this->garantirNomeUnico((int) $row->unidade_id, $data['nome'], $id);
        }

        DB::table('dlv_categorias')->where('id', $id)->update([
            'nome' => $data['nome'] ?? $row->nome,
            'ordem' => array_key_exists('ordem', $data) ? (int) $data['ordem'] : $row->ordem,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : (bool) $row->ativo,
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('dlv_categorias')->where('id', $id)->first());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryCategorias');
        $row = DB::table('dlv_categorias')->where('id', $id)->first();
        abort_unless($row, 404, 'Categoria não encontrada.');
        $this->access->autorizarRegistro($usuario, $row);
        $referencias = DB::table('dlv_produtos')->where('categoria_id', $id)->count();
        if ($referencias > 0) {
            throw ValidationException::withMessages([
                'categoria' => "Esta categoria está vinculada a {$referencias} produto(s). Altere a categoria dos produtos antes de excluir.",
            ]);
        }
        DB::table('dlv_categorias')->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    private function validar(Request $request, bool $criar = true): array
    {
        $rules = [
            'nome' => ($criar ? 'required' : 'sometimes').'|string|max:255',
            'ordem' => 'nullable|integer|min:0|max:65535',
            'ativo' => 'nullable|boolean',
            'unidade_id' => 'nullable|integer',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function garantirNomeUnico(int $unidadeId, string $nome, ?int $excetoId = null): void
    {
        $query = DB::table('dlv_categorias')
            ->where('unidade_id', $unidadeId)
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($nome)]);

        if ($excetoId !== null) {
            $query->where('id', '!=', $excetoId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'nome' => 'Já existe uma categoria com este nome nesta unidade.',
            ]);
        }
    }
}
