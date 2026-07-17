<?php

namespace App\Http\Controllers\Delivery;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeliveryProdutoController extends DeliveryBaseController
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryProdutos');
        $query = DB::table('dlv_produtos');
        $this->access->aplicarEscopo($query, $usuario, $request);

        if ($request->has('ativo')) {
            $query->where('ativo', filter_var($request->query('ativo'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }
        if ($request->has('visivel_loja')) {
            $query->where('visivel_loja', filter_var($request->query('visivel_loja'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }
        if ($categoriaId = (int) $request->query('categoria_id', 0)) {
            $query->where('categoria_id', $categoriaId);
        }
        if ($busca = trim((string) $request->query('busca', ''))) {
            $like = '%'.$busca.'%';
            $query->where(function ($q) use ($like) {
                $q->where('nome', 'like', $like)->orWhere('sku', 'like', $like);
            });
        }

        $items = $query->orderBy('ordem')->orderBy('nome')->limit(500)->get();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryProdutos');
        $data = $this->validar($request);
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $data);
        $this->validarCategoria($data['categoria_id'] ?? null, $unidadeId);
        $agora = now();

        $id = DB::table('dlv_produtos')->insertGetId($this->payload($data, $unidadeId) + [
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        return response()->json($this->detalhe($id), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryProdutos');
        $row = DB::table('dlv_produtos')->where('id', $id)->first();
        abort_unless($row, 404, 'Produto não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);

        return response()->json($this->detalhe($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryProdutos');
        $row = DB::table('dlv_produtos')->where('id', $id)->first();
        abort_unless($row, 404, 'Produto não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);
        $data = $this->validar($request, false);
        if (array_key_exists('categoria_id', $data)) {
            $this->validarCategoria($data['categoria_id'], (int) $row->unidade_id);
        }

        $update = $this->payload(array_merge((array) $row, $data), (int) $row->unidade_id);
        unset($update['unidade_id']);
        $update['updated_at'] = now();
        DB::table('dlv_produtos')->where('id', $id)->update($update);

        return response()->json($this->detalhe($id));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryProdutos');
        $row = DB::table('dlv_produtos')->where('id', $id)->first();
        abort_unless($row, 404, 'Produto não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);

        DB::transaction(function () use ($id) {
            DB::table('dlv_produto_adicional')->where('produto_id', $id)->delete();
            DB::table('dlv_produto_ingredientes')->where('produto_id', $id)->delete();
            DB::table('dlv_produtos')->where('id', $id)->delete();
        });

        return response()->json(['ok' => true]);
    }

    public function syncAdicionais(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryProdutos');
        $row = DB::table('dlv_produtos')->where('id', $id)->first();
        abort_unless($row, 404, 'Produto não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);

        $ids = collect($request->input('adicional_ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        if ($ids->isNotEmpty()) {
            $validos = DB::table('dlv_adicionais')
                ->where('unidade_id', $row->unidade_id)
                ->whereIn('id', $ids->all())
                ->pluck('id')
                ->map(fn ($v) => (int) $v);
            abort_unless($validos->count() === $ids->count(), 422, 'Um ou mais adicionais são inválidos.');
        }

        DB::transaction(function () use ($id, $ids) {
            DB::table('dlv_produto_adicional')->where('produto_id', $id)->delete();
            $agora = now();
            foreach ($ids as $adicionalId) {
                DB::table('dlv_produto_adicional')->insert([
                    'produto_id' => $id,
                    'adicional_id' => $adicionalId,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }
        });

        return response()->json($this->detalhe($id));
    }

    private function detalhe(int $id): array
    {
        $produto = DB::table('dlv_produtos')->where('id', $id)->first();
        $adicionais = DB::table('dlv_produto_adicional as pa')
            ->join('dlv_adicionais as a', 'a.id', '=', 'pa.adicional_id')
            ->where('pa.produto_id', $id)
            ->orderBy('a.ordem')
            ->orderBy('a.nome')
            ->get(['a.*']);
        $ingredientes = DB::table('dlv_produto_ingredientes')
            ->where('produto_id', $id)
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();

        return array_merge((array) $produto, [
            'adicionais' => $adicionais,
            'ingredientes' => $ingredientes,
        ]);
    }

    private function payload(array $data, int $unidadeId): array
    {
        return [
            'unidade_id' => $unidadeId,
            'categoria_id' => $data['categoria_id'] ?? null,
            'estoque_produto_id' => $data['estoque_produto_id'] ?? null,
            'sku' => $data['sku'] ?? null,
            'nome' => (string) $data['nome'],
            'preco' => round((float) ($data['preco'] ?? 0), 2),
            'descricao' => $data['descricao'] ?? null,
            'foto_path' => $data['foto_path'] ?? null,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
            'visivel_loja' => array_key_exists('visivel_loja', $data) ? (bool) $data['visivel_loja'] : true,
            'permite_adicionais' => array_key_exists('permite_adicionais', $data) ? (bool) $data['permite_adicionais'] : false,
            'acrescimo_escolhas_min' => (int) ($data['acrescimo_escolhas_min'] ?? 0),
            'acrescimo_escolhas_max' => $data['acrescimo_escolhas_max'] ?? null,
            'max_ingredientes_retirar' => $data['max_ingredientes_retirar'] ?? null,
            'ingredientes_retirar_ui' => $data['ingredientes_retirar_ui'] ?? 'checkbox',
            'acrescimos_loja_ui' => $data['acrescimos_loja_ui'] ?? 'stepper',
            'apresentacao' => $data['apresentacao'] ?? null,
            'ordem' => (int) ($data['ordem'] ?? 0),
        ];
    }

    private function validarCategoria(mixed $categoriaId, int $unidadeId): void
    {
        if ($categoriaId === null || $categoriaId === '') {
            return;
        }
        $ok = DB::table('dlv_categorias')->where('id', (int) $categoriaId)->where('unidade_id', $unidadeId)->exists();
        abort_unless($ok, 422, 'Categoria inválida para a unidade.');
    }

    private function validar(Request $request, bool $criar = true): array
    {
        $rules = [
            'nome' => ($criar ? 'required' : 'sometimes').'|string|max:180',
            'preco' => ($criar ? 'required' : 'sometimes').'|numeric|min:0',
            'categoria_id' => 'nullable|integer',
            'estoque_produto_id' => 'nullable|integer',
            'sku' => 'nullable|string|max:80',
            'descricao' => 'nullable|string',
            'foto_path' => 'nullable|string|max:255',
            'ativo' => 'nullable|boolean',
            'visivel_loja' => 'nullable|boolean',
            'permite_adicionais' => 'nullable|boolean',
            'acrescimo_escolhas_min' => 'nullable|integer|min:0',
            'acrescimo_escolhas_max' => 'nullable|integer|min:0',
            'max_ingredientes_retirar' => 'nullable|integer|min:0',
            'ingredientes_retirar_ui' => 'nullable|string|max:40',
            'acrescimos_loja_ui' => 'nullable|string|max:40',
            'apresentacao' => 'nullable|string|max:80',
            'ordem' => 'nullable|integer|min:0',
            'unidade_id' => 'nullable|integer',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
