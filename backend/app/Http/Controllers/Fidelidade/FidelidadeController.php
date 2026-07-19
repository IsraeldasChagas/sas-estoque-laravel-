<?php

namespace App\Http\Controllers\Fidelidade;

use App\Http\Controllers\Controller;
use App\Services\Fidelidade\FidelidadeCatalogoConsultaService;
use App\Services\Fidelidade\FidelidadePublicOtpCache;
use App\Services\Fidelidade\FidelidadeCodigoService;
use App\Services\Fidelidade\FidelidadeLedgerService;
use App\Services\Fidelidade\FidelidadeNormalizer;
use App\Services\Fidelidade\FidelidadeResgateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FidelidadeController extends Controller
{
    private const MODOS = ['selos', 'pontos'];

    private const RECOMPENSA_TIPOS = ['catalogo_consulta', 'desconto_valor', 'desconto_percentual', 'brinde', 'catalogo', 'produto'];

    private const STATUS_CONTA = ['ativo', 'inativo', 'bloqueado'];

    private const STATUS_RESGATE = ['pendente', 'entregue', 'cancelado', 'estornado'];

    public function __construct(
        private FidelidadeLedgerService $ledger,
        private FidelidadeResgateService $resgate,
        private FidelidadeCatalogoConsultaService $catalogoConsulta,
    ) {}

    public function getPrograma(Request $request): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadePrograma');
        $this->verificarTabelas();
        $unidadeId = $this->resolverUnidade($usuario, $request);
        abort_unless($unidadeId, 422, 'unidade_id obrigatório.');
        $unidadeId = $this->catalogoConsulta->unidadeFidelidadeCanonica($unidadeId);

        $programa = DB::table('fid_programas')->where('unidade_id', $unidadeId)->first();

        return response()->json([
            'programa' => $this->serializarPrograma($programa),
            'catalogo_consulta_suportado' => $this->catalogoConsulta->colunasDisponiveis(),
        ]);
    }

    public function catalogoConsultaProdutos(Request $request): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadePrograma');
        $this->verificarTabelas();
        $unidadeId = $this->resolverUnidade($usuario, $request);
        abort_unless($unidadeId, 422, 'unidade_id obrigatório.');
        $unidadeId = $this->catalogoConsulta->unidadeFidelidadeCanonica($unidadeId);

        $loja = $this->catalogoConsulta->lojaVinculada($unidadeId);
        $deliveryUnidadeId = $this->catalogoConsulta->unidadeDeliveryParaFidelidade($unidadeId);
        $items = $this->catalogoConsulta->produtosAtivos($unidadeId);

        return response()->json([
            'items' => $items,
            'delivery_disponivel' => Schema::hasTable('dlv_produtos'),
            'unidade_fidelidade_id' => $unidadeId,
            'unidade_delivery_id' => $deliveryUnidadeId,
            'loja_nome' => $loja->nome_loja ?? null,
            'loja_slug' => $loja->slug ?? null,
            'catalogo_consulta_suportado' => $this->catalogoConsulta->colunasDisponiveis(),
        ]);
    }

    public function putPrograma(Request $request): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadePrograma');
        $this->verificarTabelas();
        $unidadeId = $this->resolverUnidade($usuario, $request, true);
        abort_unless($unidadeId, 422, 'unidade_id obrigatório.');
        $unidadeId = $this->catalogoConsulta->unidadeFidelidadeCanonica($unidadeId);

        $data = Validator::make($request->all(), [
            'ativo' => 'sometimes|boolean',
            'nome_exibicao' => 'sometimes|string|max:120',
            'modo' => 'sometimes|in:'.implode(',', self::MODOS),
            'pedidos_meta' => 'sometimes|integer|min:1|max:1000',
            'pontos_por_selo' => 'sometimes|integer|min:0|max:100000',
            'tipo_recompensa_padrao' => 'sometimes|in:'.implode(',', self::RECOMPENSA_TIPOS),
            'produto_id' => 'nullable|integer',
            'valor_desconto' => 'nullable|numeric|min:0',
            'desconto_percentual' => 'nullable|numeric|min:0|max:100',
            'base_desconto_percentual' => 'nullable|string|max:40',
            'texto_recompensa' => 'nullable|string|max:500',
            'catalogo_qtd_escolhas' => 'nullable|integer|min:1|max:20',
            'catalogo_produtos_ids' => 'nullable|array|max:50',
            'catalogo_produtos_ids.*' => 'integer|min:1',
            'dias_expiracao_credito' => 'nullable|integer|min:1|max:3650',
            'permite_ajuste_manual' => 'sometimes|boolean',
        ])->validate();

        $agora = now();
        $existente = DB::table('fid_programas')->where('unidade_id', $unidadeId)->first();
        $tipo = $data['tipo_recompensa_padrao'] ?? ($existente->tipo_recompensa_padrao ?? 'catalogo_consulta');
        if ($tipo === 'produto') {
            $tipo = 'catalogo_consulta';
        }
        $payload = [
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : ($existente->ativo ?? false),
            'nome_exibicao' => $data['nome_exibicao'] ?? ($existente->nome_exibicao ?? 'Cartão fidelidade'),
            'modo' => $data['modo'] ?? ($existente->modo ?? 'selos'),
            'pedidos_meta' => $data['pedidos_meta'] ?? ($existente->pedidos_meta ?? 10),
            'pontos_por_selo' => $data['pontos_por_selo'] ?? ($existente->pontos_por_selo ?? 1),
            'tipo_recompensa_padrao' => $tipo,
            'produto_id' => array_key_exists('produto_id', $data) ? $data['produto_id'] : ($existente->produto_id ?? null),
            'valor_desconto' => array_key_exists('valor_desconto', $data) ? $data['valor_desconto'] : ($existente->valor_desconto ?? null),
            'texto_recompensa' => $tipo === 'brinde' && array_key_exists('texto_recompensa', $data)
                ? $data['texto_recompensa']
                : ($tipo === 'brinde' ? ($existente->texto_recompensa ?? null) : null),
            'dias_expiracao_credito' => array_key_exists('dias_expiracao_credito', $data) ? $data['dias_expiracao_credito'] : ($existente->dias_expiracao_credito ?? null),
            'permite_ajuste_manual' => array_key_exists('permite_ajuste_manual', $data)
                ? (bool) $data['permite_ajuste_manual']
                : ($existente->permite_ajuste_manual ?? true),
            'updated_at' => $agora,
        ];

        if ($this->catalogoConsulta->colunasDisponiveis()) {
            if ($tipo === 'catalogo_consulta') {
                $payload['catalogo_qtd_escolhas'] = array_key_exists('catalogo_qtd_escolhas', $data)
                    ? max(1, min(20, (int) $data['catalogo_qtd_escolhas']))
                    : max(1, (int) ($existente->catalogo_qtd_escolhas ?? 1));
                $ids = array_key_exists('catalogo_produtos_ids', $data)
                    ? (array) $data['catalogo_produtos_ids']
                    : array_map(
                        fn ($item) => (int) ($item['id'] ?? 0),
                        $this->catalogoConsulta->decodificarProdutosJson($existente->catalogo_produtos_json ?? null)
                    );
                $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
                if ($ids === [] && array_key_exists('catalogo_produtos_ids', $data)) {
                    throw ValidationException::withMessages([
                        'catalogo_produtos_ids' => ['Marque pelo menos 1 produto do cardápio Delivery.'],
                    ]);
                }
                $json = $this->catalogoConsulta->normalizarProdutosJson($unidadeId, $ids);
                if ($ids !== [] && $json === null) {
                    throw ValidationException::withMessages([
                        'catalogo_produtos_ids' => ['Não foi possível vincular os produtos marcados. Confira se o Delivery está na mesma unidade (ou unidade vinculada) e se os produtos estão ativos.'],
                    ]);
                }
                $payload['catalogo_produtos_json'] = $json;
                $payload['texto_recompensa'] = null;
            } else {
                $payload['catalogo_qtd_escolhas'] = null;
                $payload['catalogo_produtos_json'] = null;
            }
        } elseif ($tipo === 'catalogo_consulta') {
            abort(503, 'Execute php artisan migrate para salvar produtos do catálogo (consulta).');
        }

        if (Schema::hasColumn('fid_programas', 'desconto_percentual')) {
            $payload['desconto_percentual'] = array_key_exists('desconto_percentual', $data)
                ? $data['desconto_percentual']
                : ($existente->desconto_percentual ?? null);
            $payload['base_desconto_percentual'] = array_key_exists('base_desconto_percentual', $data)
                ? $data['base_desconto_percentual']
                : ($existente->base_desconto_percentual ?? null);
        }

        if ($existente) {
            DB::table('fid_programas')->where('id', $existente->id)->update($payload);
            $id = (int) $existente->id;
        } else {
            $id = DB::table('fid_programas')->insertGetId(array_merge($payload, [
                'unidade_id' => $unidadeId,
                'created_at' => $agora,
            ]));
        }

        return response()->json([
            'programa' => $this->serializarPrograma(DB::table('fid_programas')->where('id', $id)->first()),
        ]);
    }

    public function listCartoes(Request $request): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeCartoes');
        $this->verificarTabelas();

        $query = DB::table('fid_contas');
        $this->aplicarEscopo($query, $usuario, $request);

        if ($status = trim((string) $request->query('status', ''))) {
            $query->where('status', $status);
        }
        if ($busca = trim((string) $request->query('q', $request->query('busca', '')))) {
            $tel = FidelidadeNormalizer::telefone($busca);
            $like = '%'.$busca.'%';
            $query->where(function ($q) use ($like, $tel, $busca) {
                $q->where('nome', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('codigo_fidelidade', 'like', $like)
                    ->orWhere('telefone_normalizado', 'like', '%'.$tel.'%')
                    ->orWhere('cpf_normalizado', 'like', '%'.preg_replace('/\D+/', '', $busca).'%');
            });
        }

        $limit = max(1, min(200, (int) $request->query('limit', 100)));
        $items = $query->orderByDesc('id')->limit($limit)->get();

        return response()->json(['items' => $items]);
    }

    public function storeCartao(Request $request): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeCartoes');
        $this->verificarTabelas();
        $unidadeId = $this->resolverUnidade($usuario, $request, true);
        abort_unless($unidadeId, 422, 'unidade_id obrigatório.');

        $data = Validator::make($request->all(), [
            'telefone' => 'required|string|max:30',
            'cpf' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:160',
            'nome' => 'nullable|string|max:160',
            'origem_tipo' => 'nullable|string|max:40',
            'origem_id' => 'nullable|integer',
        ])->validate();

        $telefone = FidelidadeNormalizer::telefone($data['telefone']);
        if ($telefone === '' || strlen($telefone) < 10) {
            throw ValidationException::withMessages(['telefone' => 'Telefone inválido.']);
        }

        $cpf = null;
        if (! empty($data['cpf'])) {
            $cpf = FidelidadeNormalizer::cpf($data['cpf']);
            if (! $cpf || ! FidelidadeNormalizer::cpfValido($cpf)) {
                throw ValidationException::withMessages(['cpf' => 'CPF inválido.']);
            }
        }

        $email = FidelidadeNormalizer::email($data['email'] ?? null);
        $nome = FidelidadeNormalizer::nome($data['nome'] ?? null);

        if ($cpf) {
            $cpfOutro = DB::table('fid_contas')
                ->where('unidade_id', $unidadeId)
                ->where('cpf_normalizado', $cpf)
                ->where('telefone_normalizado', '!=', $telefone)
                ->exists();
            if ($cpfOutro) {
                throw ValidationException::withMessages(['cpf' => 'Este CPF já está cadastrado em outro telefone.']);
            }
        }

        if ($email) {
            $emailOutro = DB::table('fid_contas')
                ->where('unidade_id', $unidadeId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('telefone_normalizado', '!=', $telefone)
                ->exists();
            if ($emailOutro) {
                throw ValidationException::withMessages(['email' => 'Este e-mail já está cadastrado em outro telefone.']);
            }
        }

        $existente = DB::table('fid_contas')
            ->where('unidade_id', $unidadeId)
            ->where('telefone_normalizado', $telefone)
            ->first();

        if ($existente) {
            if ((string) $existente->status === 'ativo') {
                throw ValidationException::withMessages(['telefone' => 'Já existe cartão ativo para este telefone.']);
            }
            DB::table('fid_contas')->where('id', $existente->id)->update([
                'cpf_normalizado' => $cpf ?? $existente->cpf_normalizado,
                'email' => $email ?? $existente->email,
                'nome' => $nome ?? $existente->nome,
                'status' => 'ativo',
                'updated_at' => now(),
            ]);
            $conta = DB::table('fid_contas')->where('id', $existente->id)->first();

            return response()->json(['conta' => $conta, 'reativado' => true]);
        }

        $agora = now();
        $id = DB::table('fid_contas')->insertGetId([
            'unidade_id' => $unidadeId,
            'telefone_normalizado' => $telefone,
            'cpf_normalizado' => $cpf,
            'email' => $email,
            'nome' => $nome,
            'codigo_fidelidade' => FidelidadeCodigoService::gerar(),
            'status' => 'ativo',
            'saldo_selos' => 0,
            'saldo_pontos' => 0,
            'total_resgates' => 0,
            'origem_tipo' => $data['origem_tipo'] ?? null,
            'origem_id' => $data['origem_id'] ?? null,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $this->ledger->aplicar([
            'conta_id' => $id,
            'tipo' => 'geracao',
            'delta_selos' => 0,
            'delta_pontos' => 0,
            'descricao' => 'Cadastro do cartão',
            'usuario_id' => (int) $usuario->id,
            'idempotency_key' => 'geracao-conta-'.$id,
        ]);

        return response()->json([
            'conta' => DB::table('fid_contas')->where('id', $id)->first(),
            'reativado' => false,
        ], 201);
    }

    public function showCartao(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeCartoes');
        $this->verificarTabelas();
        $conta = $this->obterContaEscopo($id, $usuario);

        return response()->json(['conta' => $conta]);
    }

    public function destroyCartao(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeCartoes');
        $this->verificarTabelas();
        $conta = $this->obterContaEscopo($id, $usuario);

        DB::transaction(function () use ($conta) {
            if (Schema::hasTable('fid_resgates')) {
                DB::table('fid_resgates')->where('conta_id', $conta->id)->delete();
            }
            if (Schema::hasTable('fid_ledger')) {
                DB::table('fid_ledger')->where('conta_id', $conta->id)->delete();
            }
            DB::table('fid_contas')->where('id', $conta->id)->delete();
        });

        FidelidadePublicOtpCache::invalidarTelefone(
            (int) $conta->unidade_id,
            (string) $conta->telefone_normalizado
        );

        return response()->json([
            'message' => 'Cartão excluído. Se o cliente for cadastrado de novo, começa do zero.',
            'deleted_id' => (int) $conta->id,
        ]);
    }

    public function postSelo(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeCartoes');
        $this->verificarTabelas();
        $conta = $this->obterContaEscopo($id, $usuario);

        $programa = DB::table('fid_programas')->where('unidade_id', $conta->unidade_id)->first();
        $pontosPorSelo = (int) ($programa->pontos_por_selo ?? 1);
        $key = $this->idempotencyKey($request);

        $expiraEm = null;
        if ($programa && ! empty($programa->dias_expiracao_credito)) {
            $expiraEm = now()->addDays((int) $programa->dias_expiracao_credito);
        }

        $result = $this->ledger->aplicar([
            'conta_id' => (int) $conta->id,
            'tipo' => 'selo',
            'delta_selos' => 1,
            'delta_pontos' => $pontosPorSelo,
            'descricao' => $request->input('descricao', 'Selo fidelidade'),
            'referencia_tipo' => $request->input('referencia_tipo'),
            'referencia_id' => $request->input('referencia_id'),
            'idempotency_key' => $key,
            'expira_em' => $expiraEm,
            'usuario_id' => (int) $usuario->id,
        ]);

        return response()->json([
            'conta' => $result['conta'],
            'ledger' => $result['ledger'],
            'replayed' => $result['replayed'],
        ], $result['replayed'] ? 200 : 201);
    }

    public function postAjuste(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeCartoes');
        $this->verificarTabelas();
        $conta = $this->obterContaEscopo($id, $usuario);

        $programa = DB::table('fid_programas')->where('unidade_id', $conta->unidade_id)->first();
        if ($programa && ! $programa->permite_ajuste_manual) {
            abort(403, 'Ajuste manual desabilitado no programa.');
        }

        $data = Validator::make($request->all(), [
            'delta_selos' => 'nullable|integer',
            'delta_pontos' => 'nullable|integer',
            'descricao' => 'nullable|string|max:500',
        ])->validate();

        $deltaSelos = (int) ($data['delta_selos'] ?? 0);
        $deltaPontos = (int) ($data['delta_pontos'] ?? 0);
        if ($deltaSelos === 0 && $deltaPontos === 0) {
            throw ValidationException::withMessages(['delta' => 'Informe delta_selos ou delta_pontos.']);
        }

        $result = $this->ledger->aplicar([
            'conta_id' => (int) $conta->id,
            'tipo' => 'ajuste',
            'delta_selos' => $deltaSelos,
            'delta_pontos' => $deltaPontos,
            'descricao' => $data['descricao'] ?? 'Ajuste manual',
            'idempotency_key' => $this->idempotencyKey($request),
            'usuario_id' => (int) $usuario->id,
        ]);

        return response()->json([
            'conta' => $result['conta'],
            'ledger' => $result['ledger'],
            'replayed' => $result['replayed'],
        ]);
    }

    public function patchStatus(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeCartoes');
        $this->verificarTabelas();
        $conta = $this->obterContaEscopo($id, $usuario);

        $data = Validator::make($request->all(), [
            'status' => 'required|in:'.implode(',', self::STATUS_CONTA),
        ])->validate();

        $novo = (string) $data['status'];
        if ($novo === (string) $conta->status) {
            return response()->json(['conta' => $conta]);
        }

        DB::table('fid_contas')->where('id', $conta->id)->update([
            'status' => $novo,
            'updated_at' => now(),
        ]);

        $this->ledger->aplicar([
            'conta_id' => (int) $conta->id,
            'tipo' => 'status',
            'delta_selos' => 0,
            'delta_pontos' => 0,
            'descricao' => 'Status: '.$conta->status.' → '.$novo,
            'usuario_id' => (int) $usuario->id,
            'permitir_bloqueado' => true,
            'idempotency_key' => $this->idempotencyKey($request) ?: ('status-'.$conta->id.'-'.$novo.'-'.now()->timestamp),
        ]);

        return response()->json([
            'conta' => DB::table('fid_contas')->where('id', $conta->id)->first(),
        ]);
    }

    public function extrato(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeHistorico');
        $this->verificarTabelas();
        $conta = $this->obterContaEscopo($id, $usuario);

        $limit = max(1, min(500, (int) $request->query('limit', 100)));
        $items = DB::table('fid_ledger')
            ->where('conta_id', $conta->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'items' => $items,
            'saldo_selos' => (int) $conta->saldo_selos,
            'saldo_pontos' => (int) $conta->saldo_pontos,
        ]);
    }

    public function estornar(Request $request, int $id, int $ledgerId): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeHistorico');
        $this->verificarTabelas();
        $conta = $this->obterContaEscopo($id, $usuario);

        $movimento = DB::table('fid_ledger')->where('id', $ledgerId)->where('conta_id', $conta->id)->first();
        abort_unless($movimento, 404, 'Movimento não encontrado.');

        $result = $this->ledger->estornar(
            $ledgerId,
            (int) $usuario->id,
            $request->input('descricao'),
            $this->idempotencyKey($request)
        );

        return response()->json([
            'conta' => $result['conta'],
            'ledger' => $result['ledger'],
            'replayed' => $result['replayed'],
        ]);
    }

    public function listRecompensas(Request $request): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeRecompensas');
        $this->verificarTabelas();

        $query = DB::table('fid_recompensas');
        $this->aplicarEscopo($query, $usuario, $request);
        if ($request->query('ativo') !== null && $request->query('ativo') !== '') {
            $query->where('ativo', (int) filter_var($request->query('ativo'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'items' => $query->orderBy('titulo')->limit(200)->get(),
        ]);
    }

    public function storeRecompensa(Request $request): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeRecompensas');
        $this->verificarTabelas();
        $unidadeId = $this->resolverUnidade($usuario, $request, true);
        abort_unless($unidadeId, 422, 'unidade_id obrigatório.');

        $data = $this->validarRecompensa($request);
        $agora = now();
        $id = DB::table('fid_recompensas')->insertGetId(array_merge($data, [
            'unidade_id' => $unidadeId,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]));

        return response()->json([
            'recompensa' => DB::table('fid_recompensas')->where('id', $id)->first(),
        ], 201);
    }

    public function updateRecompensa(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeRecompensas');
        $this->verificarTabelas();
        $row = DB::table('fid_recompensas')->where('id', $id)->first();
        abort_unless($row, 404, 'Recompensa não encontrada.');
        $this->autorizarRegistro($usuario, $row);

        $validated = Validator::make($request->all(), [
            'titulo' => 'sometimes|string|max:160',
            'tipo' => 'sometimes|in:'.implode(',', self::RECOMPENSA_TIPOS),
            'produto_id' => 'nullable|integer',
            'valor_desconto' => 'nullable|numeric|min:0',
            'custo_selos' => 'sometimes|integer|min:0',
            'custo_pontos' => 'sometimes|integer|min:0',
            'ativo' => 'sometimes|boolean',
            'texto' => 'nullable|string|max:500',
        ])->validate();

        $data = [];
        foreach ($validated as $campo => $valor) {
            $data[$campo] = $valor;
        }
        if (array_key_exists('ativo', $data)) {
            $data['ativo'] = (bool) $data['ativo'];
        }
        $data['updated_at'] = now();
        DB::table('fid_recompensas')->where('id', $id)->update($data);

        return response()->json([
            'recompensa' => DB::table('fid_recompensas')->where('id', $id)->first(),
        ]);
    }

    public function destroyRecompensa(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeRecompensas');
        $this->verificarTabelas();
        $row = DB::table('fid_recompensas')->where('id', $id)->first();
        abort_unless($row, 404, 'Recompensa não encontrada.');
        $this->autorizarRegistro($usuario, $row);

        DB::table('fid_recompensas')->where('id', $id)->update([
            'ativo' => 0,
            'updated_at' => now(),
        ]);

        return response()->json([
            'recompensa' => DB::table('fid_recompensas')->where('id', $id)->first(),
        ]);
    }

    public function redeem(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeCartoes');
        $this->verificarTabelas();
        $conta = $this->obterContaEscopo($id, $usuario);

        $data = Validator::make($request->all(), [
            'recompensa_id' => 'nullable|integer',
            'observacao' => 'nullable|string|max:500',
        ])->validate();

        $result = $this->resgate->resgatar(
            (int) $conta->id,
            isset($data['recompensa_id']) ? (int) $data['recompensa_id'] : null,
            (int) $usuario->id,
            $this->idempotencyKey($request),
            $data['observacao'] ?? null
        );

        return response()->json([
            'resgate' => $result['resgate'],
            'ledger' => $result['ledger'],
            'conta' => $result['conta'],
            'replayed' => $result['replayed'],
        ], $result['replayed'] ? 200 : 201);
    }

    public function listResgates(Request $request): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeRecompensas');
        $this->verificarTabelas();

        $query = DB::table('fid_resgates');
        $this->aplicarEscopo($query, $usuario, $request);
        if ($status = trim((string) $request->query('status', ''))) {
            $query->where('status', $status);
        }

        return response()->json([
            'items' => $query->orderByDesc('id')->limit(200)->get(),
        ]);
    }

    public function patchResgate(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeRecompensas');
        $this->verificarTabelas();
        $resgate = DB::table('fid_resgates')->where('id', $id)->first();
        abort_unless($resgate, 404, 'Resgate não encontrado.');
        $this->autorizarRegistro($usuario, $resgate);

        $data = Validator::make($request->all(), [
            'status' => 'required|in:'.implode(',', self::STATUS_RESGATE),
            'observacao' => 'nullable|string|max:1000',
        ])->validate();

        $novo = (string) $data['status'];
        $update = [
            'status' => $novo,
            'updated_at' => now(),
        ];
        if (array_key_exists('observacao', $data)) {
            $update['observacao'] = $data['observacao'];
        }

        if ($novo === 'entregue') {
            $update['entregue_em'] = now();
            $update['usuario_entrega_id'] = (int) $usuario->id;
        }

        if ($novo === 'cancelado' && in_array((string) $resgate->status, ['pendente', 'entregue'], true) && $resgate->ledger_id) {
            $this->ledger->estornar(
                (int) $resgate->ledger_id,
                (int) $usuario->id,
                'Cancelamento de resgate #'.$id,
                $this->idempotencyKey($request) ?: ('cancel-resgate-'.$id)
            );
            $update['status'] = 'estornado';
        }

        DB::table('fid_resgates')->where('id', $id)->update($update);

        return response()->json([
            'resgate' => DB::table('fid_resgates')->where('id', $id)->first(),
        ]);
    }

    public function relatorioResumo(Request $request): JsonResponse
    {
        $usuario = $this->autorizar($request, 'fidelidadeRelatorios');
        $this->verificarTabelas();

        $contas = DB::table('fid_contas');
        $this->aplicarEscopo($contas, $usuario, $request);
        $contasRows = $contas->get();

        $ledger = DB::table('fid_ledger');
        $this->aplicarEscopo($ledger, $usuario, $request);
        $mesInicio = now()->startOfMonth()->toDateTimeString();
        $selosMes = (clone $ledger)->where('tipo', 'selo')->where('created_at', '>=', $mesInicio)->sum('delta_selos');
        $resgatesMes = (clone $ledger)->where('tipo', 'debito_resgate')->where('created_at', '>=', $mesInicio)->count();

        $ativos = $contasRows->where('status', 'ativo')->count();
        $totalResgates = (int) $contasRows->sum('total_resgates');
        $saldoSelos = (int) $contasRows->sum('saldo_selos');
        $saldoPontos = (int) $contasRows->sum('saldo_pontos');

        $resgatesQuery = DB::table('fid_resgates');
        $this->aplicarEscopo($resgatesQuery, $usuario, $request);
        $pendentes = (clone $resgatesQuery)->where('status', 'pendente')->count();

        return response()->json([
            'resumo' => [
                'cartoes_total' => $contasRows->count(),
                'cartoes_ativos' => $ativos,
                'saldo_selos' => $saldoSelos,
                'saldo_pontos' => $saldoPontos,
                'selos_mes' => (int) $selosMes,
                'resgates_total' => $totalResgates,
                'resgates_mes' => $resgatesMes,
                'resgates_pendentes' => $pendentes,
                'taxa_conversao_percentual' => $ativos > 0
                    ? round(($totalResgates / max($ativos, 1)) * 100, 2)
                    : 0,
            ],
        ]);
    }

    private function validarRecompensa(Request $request, bool $creating = true): array
    {
        $rules = [
            'titulo' => ($creating ? 'required' : 'sometimes').'|string|max:160',
            'tipo' => ($creating ? 'required' : 'sometimes').'|in:'.implode(',', self::RECOMPENSA_TIPOS),
            'produto_id' => 'nullable|integer',
            'valor_desconto' => 'nullable|numeric|min:0',
            'custo_selos' => 'sometimes|integer|min:0',
            'custo_pontos' => 'sometimes|integer|min:0',
            'ativo' => 'sometimes|boolean',
            'texto' => 'nullable|string|max:500',
        ];
        $data = Validator::make($request->all(), $rules)->validate();

        return [
            'titulo' => $data['titulo'] ?? null,
            'tipo' => $data['tipo'] ?? null,
            'produto_id' => $data['produto_id'] ?? null,
            'valor_desconto' => $data['valor_desconto'] ?? null,
            'custo_selos' => (int) ($data['custo_selos'] ?? 0),
            'custo_pontos' => (int) ($data['custo_pontos'] ?? 0),
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
            'texto' => $data['texto'] ?? null,
        ];
    }

    private function idempotencyKey(Request $request): ?string
    {
        return $this->ledger->normalizeKey(
            $request->header('Idempotency-Key', $request->input('idempotency_key'))
        );
    }

    private function obterContaEscopo(int $id, object $usuario): object
    {
        $conta = DB::table('fid_contas')->where('id', $id)->first();
        abort_unless($conta, 404, 'Cartão não encontrado.');
        $this->autorizarRegistro($usuario, $conta);

        return $conta;
    }

    private function autorizar(Request $request, string $modulo): object
    {
        $usuario = DB::table('usuarios')->where('id', $request->header('X-Usuario-Id'))->where('ativo', 1)->first();
        abort_unless($usuario, 401, 'Uuário não identificado.');
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));
        if (in_array($perfil, ['ADMIN', 'GERENTE', 'ASSISTENTE_ADMINISTRATIVO'], true)) {
            return $usuario;
        }
        $permissoes = $usuario->permissoes_menu ?? null;
        if (is_string($permissoes)) {
            $permissoes = json_decode($permissoes, true);
        }
        abort_unless(is_array($permissoes) && in_array($modulo, $permissoes, true), 403, 'Sem permissão para este módulo.');

        return $usuario;
    }

    private function aplicarEscopo($query, object $usuario, Request $request): void
    {
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));
        $podeEscolher = in_array($perfil, ['ADMIN', 'GERENTE', 'ASSISTENTE_ADMINISTRATIVO'], true);
        if ($podeEscolher && $request->filled('unidade_id')) {
            $query->where('unidade_id', (int) $request->query('unidade_id', $request->input('unidade_id')));
        } elseif (! $podeEscolher && ! empty($usuario->unidade_id)) {
            $query->where('unidade_id', (int) $usuario->unidade_id);
        }
    }

    private function autorizarRegistro(object $usuario, object $row): void
    {
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));
        $podeTodas = in_array($perfil, ['ADMIN', 'GERENTE', 'ASSISTENTE_ADMINISTRATIVO'], true);
        if (! $podeTodas && ! empty($usuario->unidade_id) && (int) $row->unidade_id !== (int) $usuario->unidade_id) {
            abort(403, 'Sem permissão para este registro.');
        }
    }

    private function resolverUnidade(object $usuario, Request $request, bool $fromBody = false): ?int
    {
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));
        $podeEscolher = in_array($perfil, ['ADMIN', 'GERENTE', 'ASSISTENTE_ADMINISTRATIVO'], true);
        if ($podeEscolher) {
            $raw = $fromBody
                ? $request->input('unidade_id', $request->query('unidade_id'))
                : $request->query('unidade_id', $request->input('unidade_id'));
            if ($raw !== null && $raw !== '') {
                return (int) $raw;
            }
        }

        return ! empty($usuario->unidade_id) ? (int) $usuario->unidade_id : null;
    }

    private function verificarTabelas(): void
    {
        abort_unless(
            Schema::hasTable('fid_programas')
            && Schema::hasTable('fid_contas')
            && Schema::hasTable('fid_ledger')
            && Schema::hasTable('fid_recompensas')
            && Schema::hasTable('fid_resgates'),
            503,
            'Módulo Fidelidade indisponível. Execute as migrations.'
        );
    }

    private function serializarPrograma(?object $programa): ?object
    {
        if (! $programa) {
            return null;
        }

        $tipo = (string) ($programa->tipo_recompensa_padrao ?? '');
        if ($tipo === 'produto') {
            $programa->tipo_recompensa_padrao = 'catalogo_consulta';
        }

        $produtos = $this->catalogoConsulta->decodificarProdutosJson($programa->catalogo_produtos_json ?? null);
        $programa->catalogo_produtos = $produtos;
        $programa->catalogo_produtos_ids = array_map(fn ($item) => (int) ($item['id'] ?? 0), $produtos);

        return $programa;
    }
}
