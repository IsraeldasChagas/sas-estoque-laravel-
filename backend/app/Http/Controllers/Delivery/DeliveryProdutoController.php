<?php

namespace App\Http\Controllers\Delivery;

use App\Support\Delivery\CardapioFichaEstoqueSupport;
use App\Support\Delivery\CardapioProdutoUnidadeSupport;
use App\Support\ProducaoEstoqueSupport;
use App\Support\ProducaoFiscalSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliveryProdutoController extends DeliveryBaseController
{
    private const UI_MODES = ['stepper', 'checkbox'];

    private const PRODUTO_FOTO_MAX = 3 * 1024 * 1024;

    private const INGREDIENTE_FOTO_MAX = 2 * 1024 * 1024;

    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    private const TIPOS_VENDA = ['revenda', 'prato'];

    public function produtosEstoqueOpcoes(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryProdutos');
        $tipo = in_array($request->query('tipo'), self::TIPOS_VENDA, true) ? (string) $request->query('tipo') : 'revenda';
        $q = trim((string) $request->query('q', ''));
        $unidadeId = (int) ($request->query('unidade_id') ?: $this->access->unidadeId($request, $usuario) ?: 0);

        $query = DB::table('produtos as p')->select('p.id', 'p.nome', 'p.unidade_id');
        if (Schema::hasColumn('produtos', 'ativo')) {
            $query->where(function ($w) {
                $w->where('p.ativo', 1)->orWhere('p.ativo', true);
            });
        }
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where('p.nome', 'like', $like);
        }

        $hasFicha = Schema::hasTable('fichas_tecnicas') && Schema::hasColumn('fichas_tecnicas', 'produto_final_id');
        if ($tipo === 'prato' && $hasFicha && ! $request->boolean('todos')) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('fichas_tecnicas as f')
                    ->whereColumn('f.produto_final_id', 'p.id');
            });
        }

        $rows = $query->orderBy('p.nome')->limit(100)->get();

        $items = $rows->map(function ($row) use ($unidadeId, $hasFicha) {
            $temFicha = $hasFicha && DB::table('fichas_tecnicas')->where('produto_final_id', (int) $row->id)->exists();
            $saldo = $unidadeId > 0 ? ProducaoEstoqueSupport::saldoDisponivel((int) $row->id, $unidadeId) : null;

            return [
                'id' => (int) $row->id,
                'nome' => (string) $row->nome,
                'tem_ficha_tecnica' => $temFicha,
                'saldo_unidade' => $saldo,
            ];
        })->values();

        return response()->json([
            'tipo' => $tipo,
            'unidade_id' => $unidadeId > 0 ? $unidadeId : null,
            'items' => $items,
            'dica' => $tipo === 'prato'
                ? 'Não use aqui insumos (tomate, arroz). No cardápio você escolhe a ficha técnica; o estoque baixa os ingredientes da receita.'
                : 'Produto que você compra pronto para revender: água, refrigerante, cerveja…',
            'mostrando_só_com_ficha' => $tipo === 'prato' && $hasFicha && ! $request->boolean('todos'),
        ]);
    }

    public function fichasCardapioOpcoes(Request $request): JsonResponse
    {
        $this->auth($request, 'deliveryProdutos');
        if (! Schema::hasTable('fichas_tecnicas')) {
            return response()->json(['items' => []]);
        }
        $q = trim((string) $request->query('q', ''));
        $query = DB::table('fichas_tecnicas')->select('id', 'nome_prato', 'produto_final_id', 'ingredientes_json', 'updated_at');
        if ($q !== '') {
            $query->where('nome_prato', 'like', '%'.$q.'%');
        }
        $rows = $query->orderBy('nome_prato')->limit(200)->get();
        $items = $rows->map(function ($row) {
            $ficha = (object) ['id' => $row->id, 'ingredientes_json' => $row->ingredientes_json ?? '[]'];
            $n = count(CardapioFichaEstoqueSupport::ingredientesDaReceita($ficha));

            return [
                'id' => (int) $row->id,
                'nome' => (string) $row->nome_prato,
                'qtd_insumos' => $n,
                'produto_final_id' => Schema::hasColumn('fichas_tecnicas', 'produto_final_id') && $row->produto_final_id
                    ? (int) $row->produto_final_id : null,
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    public function produtosEstoqueFicha(Request $request): JsonResponse
    {
        $this->auth($request, 'deliveryProdutos');
        $fichaId = (int) $request->query('ficha_id', 0);
        $produtoId = (int) $request->query('produto_id', 0);

        if ($fichaId > 0) {
            $resumo = CardapioFichaEstoqueSupport::resumoPorFichaId($fichaId);
            if ($resumo === null) {
                return response()->json([
                    'ficha_id' => $fichaId,
                    'tem_ficha' => false,
                    'mensagem' => 'Ficha não encontrada.',
                ]);
            }

            return response()->json(['ficha_id' => $fichaId, 'tem_ficha' => true] + $resumo);
        }

        if ($produtoId <= 0) {
            return response()->json(['error' => 'Informe ficha_id ou produto_id.'], 422);
        }

        $resumo = CardapioFichaEstoqueSupport::resumoPorProdutoFinal($produtoId);
        if ($resumo === null) {
            return response()->json([
                'produto_id' => $produtoId,
                'tem_ficha' => false,
                'mensagem' => CardapioFichaEstoqueSupport::mensagemSeSemFicha($produtoId) ?? 'Sem ficha para este produto.',
            ]);
        }

        return response()->json(['produto_id' => $produtoId, 'tem_ficha' => true] + $resumo);
    }

    public function index(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryProdutos');
        $query = DB::table('dlv_produtos as p')
            ->leftJoin('dlv_categorias as c', 'c.id', '=', 'p.categoria_id')
            ->select('p.*', 'c.nome as categoria_nome');
        $unidadeListagem = $this->access->unidadeId($request, $usuario);
        if ($unidadeListagem) {
            CardapioProdutoUnidadeSupport::escopoQueryDisponivelNaUnidade($query, $unidadeListagem, 'p.id', 'p.unidade_id');
        } else {
            $this->access->aplicarEscopo($query, $usuario, $request, 'p.unidade_id');
        }

        if ($request->boolean('indisponivel')) {
            $query->where('p.ativo', 0)->where('p.visivel_loja', 1);
        } elseif ($request->has('ativo')) {
            $query->where('p.ativo', filter_var($request->query('ativo'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }
        if ($request->has('visivel_loja')) {
            $query->where('p.visivel_loja', filter_var($request->query('visivel_loja'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }
        if ($categoriaId = (int) $request->query('categoria_id', 0)) {
            $query->where('p.categoria_id', $categoriaId);
        }

        $busca = trim((string) ($request->query('q', $request->query('busca', ''))));
        if ($busca !== '') {
            $like = '%'.$busca.'%';
            $query->where(function ($q) use ($like) {
                $q->where('p.nome', 'like', $like)
                    ->orWhere('p.sku', 'like', $like)
                    ->orWhere('c.nome', 'like', $like);
            });
        }

        $items = $query->orderBy('p.nome')->limit(500)->get()
            ->map(function ($row) {
                $formatted = $this->formatProdutoListagem($row);
                $formatted['unidades_venda_ids'] = CardapioProdutoUnidadeSupport::unidadesDoProduto(
                    (int) $row->id,
                    (int) $row->unidade_id
                );

                return $formatted;
            })
            ->values();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryProdutos');
        $data = $this->validar($request);
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $data);
        $this->validarCategoria($data['categoria_id'] ?? null, $unidadeId);

        $ingredientes = $this->normalizarIngredientes($request->input('ingredientes', []), $unidadeId);
        if ($ingredientes === []) {
            $data['max_ingredientes_retirar'] = null;
        }
        $this->validarRegrasNegocio($data, $ingredientes, null);
        $data = $this->normalizarVinculoCardapio($data, null);

        $sku = trim((string) ($data['sku'] ?? ''));
        if ($sku === '') {
            $sku = $this->gerarSkuUnico($unidadeId);
        } else {
            $this->garantirSkuUnico($unidadeId, $sku);
        }

        $fotoPath = $this->resolverFotoProduto($request, $unidadeId, null);

        $id = DB::transaction(function () use ($data, $unidadeId, $sku, $fotoPath, $ingredientes, $request) {
            $agora = now();
            $payload = $this->payload($data, $unidadeId, $sku, $fotoPath);
            $id = DB::table('dlv_produtos')->insertGetId($payload + [
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);

            $this->sincronizarAdicionais($id, $unidadeId, $data, $request, true);
            $this->sincronizarIngredientes($id, $unidadeId, $ingredientes);
            $this->sincronizarUnidadesVenda($id, $unidadeId, $data, $request);

            return $id;
        });

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

        $unidadeId = (int) $row->unidade_id;
        $ingredientes = $request->exists('ingredientes')
            ? $this->normalizarIngredientes($request->input('ingredientes', []), $unidadeId)
            : null;

        $merged = array_merge((array) $row, $data);
        if ($ingredientes !== null && $ingredientes === []) {
            $merged['max_ingredientes_retirar'] = null;
            $data['max_ingredientes_retirar'] = null;
        }
        $this->validarRegrasNegocio($merged, $ingredientes ?? [], $row, $request->exists('ingredientes'));
        $data = $this->normalizarVinculoCardapio(array_merge((array) $row, $data), $row);

        $fotoPath = $this->resolverFotoProduto($request, $unidadeId, $row);
        $sku = (string) ($row->sku ?? '');

        DB::transaction(function () use ($id, $data, $row, $unidadeId, $sku, $fotoPath, $ingredientes, $request) {
            $update = $this->payload($data, $unidadeId, $sku, $fotoPath);
            unset($update['unidade_id'], $update['sku']);
            $update['updated_at'] = now();
            DB::table('dlv_produtos')->where('id', $id)->update($update);

            $this->sincronizarAdicionais($id, $unidadeId, array_merge((array) $row, $data), $request, false);
            if ($ingredientes !== null) {
                $this->sincronizarIngredientes($id, $unidadeId, $ingredientes);
            }
            $this->sincronizarUnidadesVenda($id, $unidadeId, array_merge((array) $row, $data), $request);
        });

        return response()->json($this->detalhe($id));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryProdutos');
        $row = DB::table('dlv_produtos')->where('id', $id)->first();
        abort_unless($row, 404, 'Produto não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);

        $ingFotoPaths = DB::table('dlv_produto_ingredientes')
            ->where('produto_id', $id)
            ->whereNotNull('foto_path')
            ->pluck('foto_path')
            ->all();

        DB::transaction(function () use ($id) {
            DB::table('dlv_produto_adicional')->where('produto_id', $id)->delete();
            DB::table('dlv_produto_ingredientes')->where('produto_id', $id)->delete();
            if (CardapioProdutoUnidadeSupport::tabelaAtiva()) {
                DB::table('dlv_produto_unidades')->where('produto_id', $id)->delete();
            }
            DB::table('dlv_produtos')->where('id', $id)->delete();
        });

        $this->removerArquivoProprio($row->foto_path ?? null, (int) $row->unidade_id, false);
        foreach ($ingFotoPaths as $path) {
            $this->removerArquivoProprio($path, (int) $row->unidade_id, true);
        }

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
                ->where('ativo', 1)
                ->where('tipo', 'acrescentar')
                ->whereIn('id', $ids->all())
                ->pluck('id')
                ->map(fn ($v) => (int) $v);
            abort_unless($validos->count() === $ids->count(), 422, 'Um ou mais adicionais são inválidos.');
            $ids = $validos;
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
        $produto = DB::table('dlv_produtos as p')
            ->leftJoin('dlv_categorias as c', 'c.id', '=', 'p.categoria_id')
            ->where('p.id', $id)
            ->select('p.*', 'c.nome as categoria_nome')
            ->first();

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
            ->get()
            ->map(fn ($ing) => array_merge((array) $ing, [
                'foto_url' => $this->fotoUrl($ing->foto_path ?? null),
            ]))
            ->values();

        return array_merge((array) $produto, [
            'estoque' => (int) ($produto->estoque ?? 0),
            'foto_url' => $this->fotoUrl($produto->foto_path ?? null),
            'adicionais' => $adicionais,
            'ingredientes' => $ingredientes,
            'unidades_venda_ids' => CardapioProdutoUnidadeSupport::unidadesDoProduto($id, (int) $produto->unidade_id),
            'tipo_venda' => $this->normalizarTipoVenda($produto->tipo_venda ?? null),
            'ficha_tecnica_id' => Schema::hasColumn('dlv_produtos', 'ficha_tecnica_id') && $produto->ficha_tecnica_id
                ? (int) $produto->ficha_tecnica_id : null,
            'estoque_produto_nome' => $this->nomeProdutoEstoque($produto->estoque_produto_id ?? null),
        ]);
    }

    private function formatProdutoListagem(object $row): array
    {
        $data = (array) $row;

        return array_merge($data, [
            'estoque' => (int) ($row->estoque ?? 0),
            'categoria_nome' => $row->categoria_nome ?? null,
            'foto_url' => $this->fotoUrl($row->foto_path ?? null),
        ]);
    }

    private function payload(array $data, int $unidadeId, string $sku, ?string $fotoPath): array
    {
        $permite = array_key_exists('permite_adicionais', $data)
            ? (bool) $data['permite_adicionais']
            : false;

        $min = array_key_exists('acrescimo_escolhas_min', $data)
            ? ($data['acrescimo_escolhas_min'] === null || $data['acrescimo_escolhas_min'] === '' ? 0 : (int) $data['acrescimo_escolhas_min'])
            : 0;
        $max = array_key_exists('acrescimo_escolhas_max', $data)
            ? ($data['acrescimo_escolhas_max'] === null || $data['acrescimo_escolhas_max'] === '' ? null : (int) $data['acrescimo_escolhas_max'])
            : null;

        if (! $permite) {
            $min = 0;
            $max = null;
        }

        return [
            'unidade_id' => $unidadeId,
            'categoria_id' => $data['categoria_id'] ?? null,
            'estoque_produto_id' => $data['estoque_produto_id'] ?? null,
            'sku' => $sku !== '' ? $sku : null,
            'nome' => (string) $data['nome'],
            'preco' => round((float) ($data['preco'] ?? 0), 2),
            'estoque' => max(0, (int) ($data['estoque'] ?? 0)),
            'descricao' => $data['descricao'] ?? null,
            'foto_path' => $fotoPath,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
            'visivel_loja' => array_key_exists('visivel_loja', $data) ? (bool) $data['visivel_loja'] : true,
            'visivel_pdv' => array_key_exists('visivel_pdv', $data) ? (bool) $data['visivel_pdv'] : true,
            'permite_adicionais' => $permite,
            'acrescimo_escolhas_min' => $min,
            'acrescimo_escolhas_max' => $max,
            'max_ingredientes_retirar' => array_key_exists('max_ingredientes_retirar', $data)
                ? ($data['max_ingredientes_retirar'] === null || $data['max_ingredientes_retirar'] === '' ? null : (int) $data['max_ingredientes_retirar'])
                : null,
            'ingredientes_retirar_ui' => $this->uiMode($data['ingredientes_retirar_ui'] ?? null, 'stepper'),
            'acrescimos_loja_ui' => $this->uiMode($data['acrescimos_loja_ui'] ?? null, 'stepper'),
            'apresentacao' => $data['apresentacao'] ?? null,
            'ordem' => (int) ($data['ordem'] ?? 0),
        ] + (Schema::hasColumn('dlv_produtos', 'tipo_venda')
            ? ['tipo_venda' => $this->normalizarTipoVenda($data['tipo_venda'] ?? null)]
            : []) + (Schema::hasColumn('dlv_produtos', 'ficha_tecnica_id')
            ? ['ficha_tecnica_id' => isset($data['ficha_tecnica_id']) && $data['ficha_tecnica_id'] !== '' && $data['ficha_tecnica_id'] !== null
                ? (int) $data['ficha_tecnica_id'] : null]
            : []);
    }

    /** @param  array<string, mixed>  $data */
    private function normalizarVinculoCardapio(array $data, ?object $existente): array
    {
        $tipo = $this->normalizarTipoVenda($data['tipo_venda'] ?? ($existente->tipo_venda ?? null));
        if ($tipo === 'prato' && Schema::hasColumn('dlv_produtos', 'ficha_tecnica_id')) {
            $fichaId = (int) ($data['ficha_tecnica_id'] ?? ($existente->ficha_tecnica_id ?? 0));
            if ($fichaId > 0) {
                $data['estoque_produto_id'] = null;
            }
        } elseif (Schema::hasColumn('dlv_produtos', 'ficha_tecnica_id')) {
            $data['ficha_tecnica_id'] = null;
        }

        return $data;
    }

    private function uiMode(mixed $value, string $default): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($v, self::UI_MODES, true) ? $v : $default;
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
            'estoque' => 'nullable|integer|min:0',
            'categoria_id' => 'nullable|integer',
            'estoque_produto_id' => 'nullable|integer',
            'ficha_tecnica_id' => 'nullable|integer',
            'tipo_venda' => ['nullable', 'string', Rule::in(self::TIPOS_VENDA)],
            'sku' => 'nullable|string|max:80',
            'descricao' => 'nullable|string',
            'foto_path' => 'nullable|string|max:255',
            'foto_base64' => 'nullable|string',
            'ativo' => 'nullable|boolean',
            'visivel_loja' => 'nullable|boolean',
            'visivel_pdv' => 'nullable|boolean',
            'permite_adicionais' => 'nullable|boolean',
            'acrescimo_escolhas_min' => 'nullable|integer|min:0|max:999',
            'acrescimo_escolhas_max' => 'nullable|integer|min:0|max:999',
            'max_ingredientes_retirar' => 'nullable|integer|min:0|max:999',
            'ingredientes_retirar_ui' => ['nullable', 'string', Rule::in(self::UI_MODES)],
            'acrescimos_loja_ui' => ['nullable', 'string', Rule::in(self::UI_MODES)],
            'apresentacao' => 'nullable|string|max:80',
            'ordem' => 'nullable|integer|min:0',
            'unidade_id' => 'nullable|integer',
            'unidades_venda_ids' => 'nullable|array|max:80',
            'unidades_venda_ids.*' => 'integer|min:1',
            'adicional_ids' => 'nullable|array',
            'adicional_ids.*' => 'integer',
            'ingredientes' => 'nullable|array',
            'ingredientes.*.id' => 'nullable|integer',
            'ingredientes.*.nome' => 'nullable|string|max:160',
            'ingredientes.*.foto_path' => 'nullable|string|max:255',
            'ingredientes.*.foto_base64' => 'nullable|string',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * @param  list<array{id:?int,nome:string,foto_path:?string,foto_base64:?string}>  $ingredientes
     */
    private function validarRegrasNegocio(array $data, array $ingredientes, ?object $existente, bool $ingredientesEnviados = true): void
    {
        $min = array_key_exists('acrescimo_escolhas_min', $data)
            ? ($data['acrescimo_escolhas_min'] === null || $data['acrescimo_escolhas_min'] === '' ? null : (int) $data['acrescimo_escolhas_min'])
            : ($existente?->acrescimo_escolhas_min !== null ? (int) $existente->acrescimo_escolhas_min : null);
        $max = array_key_exists('acrescimo_escolhas_max', $data)
            ? ($data['acrescimo_escolhas_max'] === null || $data['acrescimo_escolhas_max'] === '' ? null : (int) $data['acrescimo_escolhas_max'])
            : ($existente?->acrescimo_escolhas_max !== null ? (int) $existente->acrescimo_escolhas_max : null);

        if ($min !== null && ($min < 0 || $min > 999)) {
            throw ValidationException::withMessages(['acrescimo_escolhas_min' => 'Mínimo de acréscimos deve estar entre 0 e 999.']);
        }
        if ($max !== null && ($max < 0 || $max > 999)) {
            throw ValidationException::withMessages(['acrescimo_escolhas_max' => 'Máximo de acréscimos deve estar entre 0 e 999.']);
        }
        if ($min !== null && $max !== null && $max < $min) {
            throw ValidationException::withMessages([
                'acrescimo_escolhas_max' => 'O máximo de escolhas deve ser maior ou igual ao mínimo.',
            ]);
        }

        foreach (['ingredientes_retirar_ui', 'acrescimos_loja_ui'] as $campo) {
            if (array_key_exists($campo, $data) && $data[$campo] !== null && $data[$campo] !== '') {
                $v = strtolower(trim((string) $data[$campo]));
                if (! in_array($v, self::UI_MODES, true)) {
                    throw ValidationException::withMessages([$campo => 'Modo de UI inválido. Use stepper ou checkbox.']);
                }
            }
        }

        if (! $ingredientesEnviados) {
            $this->validarVinculoEstoque($data, $existente);

            return;
        }

        if ($ingredientes !== []) {
            if (! array_key_exists('max_ingredientes_retirar', $data) && $existente === null) {
                throw ValidationException::withMessages([
                    'max_ingredientes_retirar' => 'Informe quantos ingredientes o cliente pode pedir para retirar (0 = nenhum).',
                ]);
            }
            if (array_key_exists('max_ingredientes_retirar', $data) || $existente === null) {
                $raw = $data['max_ingredientes_retirar'] ?? null;
                if ($raw === null || $raw === '') {
                    throw ValidationException::withMessages([
                        'max_ingredientes_retirar' => 'Informe quantos ingredientes o cliente pode pedir para retirar (0 = nenhum).',
                    ]);
                }
                $limite = (int) $raw;
                if ($limite < 0 || $limite > count($ingredientes)) {
                    throw ValidationException::withMessages([
                        'max_ingredientes_retirar' => 'O máximo para retirar deve ser entre 0 e o total de ingredientes.',
                    ]);
                }
            }
        }

        $this->validarVinculoEstoque($data, $existente);
    }

    private function validarVinculoEstoque(array $data, ?object $existente): void
    {
        $tipo = $this->normalizarTipoVenda($data['tipo_venda'] ?? ($existente->tipo_venda ?? null));
        $estoqueId = array_key_exists('estoque_produto_id', $data)
            ? (int) ($data['estoque_produto_id'] ?? 0)
            : (int) ($existente->estoque_produto_id ?? 0);
        $fichaId = array_key_exists('ficha_tecnica_id', $data)
            ? (int) ($data['ficha_tecnica_id'] ?? 0)
            : (int) ($existente->ficha_tecnica_id ?? 0);

        if ($tipo === 'revenda') {
            if ($estoqueId <= 0) {
                throw ValidationException::withMessages([
                    'estoque_produto_id' => 'Escolha qual produto de revenda baixa no estoque (água, Coca…).',
                ]);
            }

            return;
        }

        $msg = CardapioFichaEstoqueSupport::mensagemSePratoSemReceita(
            $fichaId > 0 ? $fichaId : null,
            $estoqueId > 0 ? $estoqueId : null
        );
        if ($msg !== null) {
            throw ValidationException::withMessages(['ficha_tecnica_id' => $msg]);
        }
    }

    /**
     * @return list<array{id:?int,nome:string,foto_path:?string,foto_base64:?string}>
     */
    private function normalizarIngredientes(mixed $raw, int $unidadeId): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $nome = trim(strip_tags((string) ($item['nome'] ?? '')));
            if ($nome === '') {
                continue;
            }
            $fotoPath = null;
            if (! empty($item['foto_path'])) {
                $fotoPath = $this->fotoPathSeguro((string) $item['foto_path'], $unidadeId, true);
            }
            $out[] = [
                'id' => isset($item['id']) ? (int) $item['id'] : null,
                'nome' => Str::limit($nome, 160, ''),
                'foto_path' => $fotoPath,
                'foto_base64' => isset($item['foto_base64']) ? (string) $item['foto_base64'] : null,
            ];
        }

        return $out;
    }

    private function sincronizarAdicionais(int $produtoId, int $unidadeId, array $data, Request $request, bool $criar): void
    {
        $permite = array_key_exists('permite_adicionais', $data)
            ? (bool) $data['permite_adicionais']
            : false;

        if (! $permite) {
            DB::table('dlv_produto_adicional')->where('produto_id', $produtoId)->delete();

            return;
        }

        if (! $request->exists('adicional_ids') && ! $criar) {
            return;
        }

        $ids = collect($request->input('adicional_ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        $validos = collect();
        if ($ids->isNotEmpty()) {
            $validos = DB::table('dlv_adicionais')
                ->where('unidade_id', $unidadeId)
                ->where('ativo', 1)
                ->where('tipo', 'acrescentar')
                ->whereIn('id', $ids->all())
                ->pluck('id')
                ->map(fn ($v) => (int) $v);
            if ($validos->count() !== $ids->count()) {
                throw ValidationException::withMessages([
                    'adicional_ids' => 'Um ou mais adicionais são inválidos (somente ativos do tipo acrescentar da mesma unidade).',
                ]);
            }
        }

        DB::table('dlv_produto_adicional')->where('produto_id', $produtoId)->delete();
        $agora = now();
        foreach ($validos as $adicionalId) {
            DB::table('dlv_produto_adicional')->insert([
                'produto_id' => $produtoId,
                'adicional_id' => $adicionalId,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }
    }

    /**
     * @param  list<array{id:?int,nome:string,foto_path:?string,foto_base64:?string}>  $itens
     */
    private function sincronizarIngredientes(int $produtoId, int $unidadeId, array $itens): void
    {
        $antigos = DB::table('dlv_produto_ingredientes')
            ->where('produto_id', $produtoId)
            ->get()
            ->keyBy('id');

        $oldPaths = $antigos
            ->pluck('foto_path')
            ->filter()
            ->map(fn ($p) => ltrim(str_replace('\\', '/', (string) $p), '/'))
            ->unique()
            ->values()
            ->all();

        DB::table('dlv_produto_ingredientes')->where('produto_id', $produtoId)->delete();

        $newPaths = [];
        $agora = now();
        foreach ($itens as $i => $item) {
            $foto = null;
            if (! empty($item['foto_base64'])) {
                $foto = $this->salvarBase64($item['foto_base64'], $unidadeId, true, self::INGREDIENTE_FOTO_MAX);
            } elseif (! empty($item['foto_path'])) {
                $foto = $item['foto_path'];
            } elseif (! empty($item['id']) && $antigos->has($item['id'])) {
                $prev = $antigos->get($item['id'])->foto_path ?? null;
                $foto = $this->fotoPathSeguro((string) ($prev ?? ''), $unidadeId, true);
            }

            if ($foto) {
                $newPaths[] = ltrim(str_replace('\\', '/', $foto), '/');
            }

            DB::table('dlv_produto_ingredientes')->insert([
                'produto_id' => $produtoId,
                'nome' => $item['nome'],
                'foto_path' => $foto,
                'ordem' => $i,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }

        foreach ($oldPaths as $old) {
            if ($old !== '' && ! in_array($old, $newPaths, true)) {
                $this->removerArquivoProprio($old, $unidadeId, true);
            }
        }
    }

    private function resolverFotoProduto(Request $request, int $unidadeId, ?object $existente): ?string
    {
        if ($request->filled('foto_base64')) {
            $novo = $this->salvarBase64((string) $request->input('foto_base64'), $unidadeId, false, self::PRODUTO_FOTO_MAX);
            if ($existente && ! empty($existente->foto_path)) {
                $this->removerArquivoProprio($existente->foto_path, $unidadeId, false);
            }

            return $novo;
        }

        if ($request->exists('foto_path')) {
            $raw = $request->input('foto_path');
            if ($raw === null || $raw === '') {
                if ($existente && ! empty($existente->foto_path)) {
                    $this->removerArquivoProprio($existente->foto_path, $unidadeId, false);
                }

                return null;
            }
            $seguro = $this->fotoPathSeguro((string) $raw, $unidadeId, false);
            if ($seguro === null) {
                throw ValidationException::withMessages(['foto_path' => 'Caminho de foto inválido.']);
            }
            if ($existente && ! empty($existente->foto_path) && $existente->foto_path !== $seguro) {
                $this->removerArquivoProprio($existente->foto_path, $unidadeId, false);
            }

            return $seguro;
        }

        return $existente->foto_path ?? null;
    }

    private function gerarSkuUnico(int $unidadeId): string
    {
        do {
            $sku = 'CI-'.strtoupper(Str::random(8));
        } while (
            DB::table('dlv_produtos')->where('unidade_id', $unidadeId)->where('sku', $sku)->exists()
        );

        return $sku;
    }

    private function garantirSkuUnico(int $unidadeId, string $sku, ?int $excetoId = null): void
    {
        $q = DB::table('dlv_produtos')->where('unidade_id', $unidadeId)->where('sku', $sku);
        if ($excetoId) {
            $q->where('id', '!=', $excetoId);
        }
        if ($q->exists()) {
            throw ValidationException::withMessages(['sku' => 'SKU já utilizado nesta unidade.']);
        }
    }

    private function salvarBase64(string $dataUrl, int $unidadeId, bool $ingrediente, int $maxBytes): string
    {
        if (! preg_match('#^data:(image/(?:jpeg|png|webp|gif));base64,(.+)$#i', trim($dataUrl), $m)) {
            throw ValidationException::withMessages([
                $ingrediente ? 'ingredientes' : 'foto_base64' => 'Imagem base64 inválida. Use data URL jpeg/png/webp/gif.',
            ]);
        }

        $mime = strtolower($m[1]);
        if (! isset(self::ALLOWED_IMAGE_MIMES[$mime])) {
            throw ValidationException::withMessages([
                $ingrediente ? 'ingredientes' : 'foto_base64' => 'Formato de imagem não suportado.',
            ]);
        }

        $bin = base64_decode($m[2], true);
        if ($bin === false || $bin === '') {
            throw ValidationException::withMessages([
                $ingrediente ? 'ingredientes' : 'foto_base64' => 'Falha ao decodificar a imagem.',
            ]);
        }
        if (strlen($bin) > $maxBytes) {
            throw ValidationException::withMessages([
                $ingrediente ? 'ingredientes' : 'foto_base64' => $ingrediente
                    ? 'Imagem do ingrediente excede 2MB.'
                    : 'Imagem do produto excede 3MB.',
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($bin) ?: '';
        if (! isset(self::ALLOWED_IMAGE_MIMES[$detected])) {
            throw ValidationException::withMessages([
                $ingrediente ? 'ingredientes' : 'foto_base64' => 'Conteúdo da imagem não é um formato permitido.',
            ]);
        }

        $ext = self::ALLOWED_IMAGE_MIMES[$detected];
        $relDir = $ingrediente
            ? 'uploads/delivery/produtos/'.$unidadeId.'/ingredientes'
            : 'uploads/delivery/produtos/'.$unidadeId;
        $absDir = public_path($relDir);
        if (! is_dir($absDir) && ! mkdir($absDir, 0755, true) && ! is_dir($absDir)) {
            throw ValidationException::withMessages([
                $ingrediente ? 'ingredientes' : 'foto_base64' => 'Não foi possível criar o diretório de uploads.',
            ]);
        }

        $nome = Str::lower(Str::random(24)).'.'.$ext;
        $relPath = $relDir.'/'.$nome;
        $absPath = public_path($relPath);
        if (file_put_contents($absPath, $bin) === false) {
            throw ValidationException::withMessages([
                $ingrediente ? 'ingredientes' : 'foto_base64' => 'Falha ao gravar a imagem.',
            ]);
        }

        return $relPath;
    }

    private function fotoPathSeguro(string $path, int $unidadeId, bool $ingrediente): ?string
    {
        $rel = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($rel === '' || str_contains($rel, '..')) {
            return null;
        }

        $prefix = $ingrediente
            ? 'uploads/delivery/produtos/'.$unidadeId.'/ingredientes/'
            : 'uploads/delivery/produtos/'.$unidadeId.'/';

        if (! str_starts_with($rel, $prefix)) {
            return null;
        }

        // Evita path de ingrediente quando esperamos produto e vice-versa.
        if (! $ingrediente && str_contains(substr($rel, strlen($prefix)), '/')) {
            return null;
        }

        return $rel;
    }

    private function removerArquivoProprio(?string $path, int $unidadeId, bool $ingrediente): void
    {
        $seguro = $this->fotoPathSeguro((string) ($path ?? ''), $unidadeId, $ingrediente);
        if ($seguro === null) {
            return;
        }
        $abs = public_path($seguro);
        $uploadsRoot = realpath(public_path('uploads/delivery/produtos/'.$unidadeId));
        $real = realpath($abs);
        if ($uploadsRoot && $real && str_starts_with($real, $uploadsRoot) && is_file($real)) {
            @unlink($real);
        }
    }

    private function fotoUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }
        $rel = ltrim(str_replace('\\', '/', $path), '/');
        if ($rel === '' || str_contains($rel, '..') || ! str_starts_with($rel, 'uploads/delivery/')) {
            return null;
        }
        if (! is_file(public_path($rel))) {
            // Ainda expõe URL pública esperada; arquivo pode existir após deploy.
            return '/'.$rel;
        }

        return '/'.$rel;
    }

    /** @param  array<string, mixed>  $data */
    private function sincronizarUnidadesVenda(int $produtoId, int $unidadeDono, array $data, Request $request): void
    {
        if (! CardapioProdutoUnidadeSupport::tabelaAtiva()) {
            return;
        }

        if (! $request->exists('unidades_venda_ids')) {
            if ($request->isMethod('post')) {
                CardapioProdutoUnidadeSupport::sincronizar($produtoId, [$unidadeDono], $unidadeDono);
            }

            return;
        }

        $ids = collect($data['unidades_venda_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            $ids = [$unidadeDono];
        }

        CardapioProdutoUnidadeSupport::validarUnidadesExistem($ids);
        CardapioProdutoUnidadeSupport::sincronizar($produtoId, $ids, $unidadeDono);
    }

    private function normalizarTipoVenda(mixed $tipo): string
    {
        $t = is_string($tipo) ? strtolower(trim($tipo)) : '';

        return in_array($t, self::TIPOS_VENDA, true) ? $t : 'revenda';
    }

    private function nomeProdutoEstoque(mixed $produtoId): ?string
    {
        $id = (int) $produtoId;
        if ($id <= 0) {
            return null;
        }

        return DB::table('produtos')->where('id', $id)->value('nome');
    }
}
