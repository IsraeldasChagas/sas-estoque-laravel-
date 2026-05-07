<?php

use App\Http\Controllers\Admin\RhRecruitmentMergeController;
use App\Http\Controllers\Api\EntradaEstoqueController;
use App\Http\Controllers\KanbanTaskController;
use App\Http\Controllers\Rh\RhCandidatoController;
use App\Http\Controllers\Rh\RhDashboardController;
use App\Http\Controllers\Rh\RhDocumentoController;
use App\Http\Controllers\Rh\RhEntrevistaController;
use App\Http\Controllers\Rh\RhFolhaPontoController;
use App\Http\Controllers\Rh\RhVagaController;
use App\Services\EntradaEstoqueService;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;

// ============================================
// AUTENTICAÇÃO
// ============================================

/**
 * ⚠️ ROTA DE LOGIN - Autenticação de usuários ⚠️
 * 
 * ⚠️ CÓDIGO TESTADO E FUNCIONANDO - MODIFICAR COM CUIDADO ⚠️
 * Esta rota foi reescrita com comentários detalhados e está funcionando corretamente.
 * Se precisar modificar, teste cuidadosamente após as alterações.
 * 
 * Esta rota recebe email e senha, valida as credenciais e retorna
 * os dados do usuário autenticado junto com um token de sessão.
 * 
 * @param Request $request - Contém 'email' e 'senha'
 * @return JSON com dados do usuário ou erro
 */
Route::post('/login', function (Request $request) {
    try {
        // PASSO 1: Validação dos dados recebidos
        // Verifica se email e senha foram enviados e se o email é válido
        $request->validate([
            'email' => 'required|email',  // Email obrigatório e formato válido
            'senha' => 'required|string', // Senha obrigatória (string)
        ]);

        // PASSO 2: Busca o usuário no banco de dados
        // Procura por email e verifica se o usuário está ativo
        $usuario = DB::table('usuarios')
            ->where('email', $request->email)  // Busca pelo email informado
            ->where('ativo', 1)                 // Apenas usuários ativos podem fazer login
            ->first();                          // Retorna o primeiro resultado ou null

        // PASSO 3: Verifica se o usuário foi encontrado
        // Se não encontrou, retorna erro genérico (por segurança, não revela se email existe)
        if (!$usuario) {
            return response()->json(['error' => 'Email ou senha incorretos'], 401)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }

        // PASSO 4: Validação da senha
        // Usa password_verify que funciona com todos os formatos de bcrypt ($2a$, $2b$, $2y$)
        // Compara a senha informada com o hash armazenado no banco
        $senhaValida = password_verify($request->senha, $usuario->senha_hash);
        
        // PASSO 5: Verifica se a senha está correta
        // Se a senha não confere, retorna erro genérico (por segurança)
        if (!$senhaValida) {
            return response()->json(['error' => 'Email ou senha incorretos'], 401)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }

        // PASSO 6: Geração do token de sessão
        // Cria um token aleatório de 64 caracteres (32 bytes em hexadecimal)
        // Este token pode ser usado para autenticação em requisições futuras
        $token = bin2hex(random_bytes(32));

        // Registra login no audit_logs (rastreabilidade para comprovação)
        if (Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')->insert([
                'usuario_id' => $usuario->id,
                'acao' => 'login',
                'recurso' => 'auth',
                'descricao' => 'Login realizado com sucesso',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        }

        // PASSO 7: Retorna dados do usuário autenticado
        // Retorna ID, nome, email, perfil e token de sessão
        // Inclui headers CORS para permitir requisições do frontend
        $permissoesMenu = $usuario->permissoes_menu ?? null;
        if (is_string($permissoesMenu)) {
            $decoded = json_decode($permissoesMenu, true);
            $permissoesMenu = is_array($decoded) ? $decoded : null;
        }
        return response()->json([
            'id' => $usuario->id,                                    // ID do usuário
            'nome' => $usuario->nome,                                // Nome completo
            'email' => $usuario->email,                              // Email
            'perfil' => $usuario->perfil ?? 'VISUALIZADOR',         // Perfil (padrão: VISUALIZADOR)
            'unidade_id' => $usuario->unidade_id ?? null,            // Unidade do usuário
            'permissoes_menu' => $permissoesMenu,                    // Módulos permitidos (null = usa padrão do perfil)
            'token' => $token,                                       // Token de sessão
        ])->header('Access-Control-Allow-Origin', '*')              // Permite requisições de qualquer origem
          ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')   // Métodos permitidos
          ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization'); // Headers permitidos
          
    } catch (\Illuminate\Validation\ValidationException $e) {
        // PASSO 8a: Tratamento de erro de validação
        // Se os dados não passaram na validação (email inválido, campos faltando)
        \Log::error('Erro de validação no login: ' . $e->getMessage());
        return response()->json([
            'error' => 'Dados inválidos',
            'details' => $e->errors()
        ], 422)
            ->header('Access-Control-Allow-Origin', '*')
          ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
          ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            
    } catch (\Exception $e) {
        // PASSO 8b: Tratamento de erro genérico
        // Captura qualquer outro erro (banco de dados, conexão, etc.)
        \Log::error('Erro no login: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json(['error' => 'Erro interno do servidor'], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
});

/**
 * ROTA OPTIONS - Preflight CORS
 * 
 * Esta rota é chamada automaticamente pelo navegador antes de requisições POST
 * para verificar se o servidor permite a requisição (CORS preflight).
 * 
 * @return Resposta vazia com status 200 e headers CORS
 */
Route::options('/login', function () {
    // Retorna resposta vazia com headers CORS permitindo a requisição
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')              // Permite qualquer origem
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS') // Métodos permitidos
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization'); // Headers permitidos
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'message' => 'API funcionando']);
});

Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API Laravel funcionando',
        'timestamp' => now()->toDateTimeString(),
        'database' => 'connected'
    ]);
});

// ============================================
// PRODUTOS
// ============================================

Route::get('/produtos', function (Request $request) {
    try {
        // Faz join com unidades para trazer o nome da unidade responsável
        $query = DB::table('produtos')
            ->leftJoin('unidades', 'produtos.unidade_id', '=', 'unidades.id')
            ->select(
                'produtos.*',
                'unidades.nome as unidade_nome'  // Adiciona o nome da unidade
            );
        
        $temUnidadeId = $request->has('unidade_id') && $request->unidade_id;
        $temComEstoque = $request->has('com_estoque') && $request->com_estoque == '1';
        
        // Se tem unidade_id E com_estoque, busca produtos que têm estoque naquela unidade
        // (mesmo que o produto pertença a outra unidade, se foi transferido)
        if ($temUnidadeId && $temComEstoque) {
            $unidadeId = $request->unidade_id;
            // Busca produtos que têm estoque na unidade especificada (via stock_lotes)
            $query->whereExists(function ($q) use ($unidadeId) {
                $q->select(DB::raw(1))
                  ->from('stock_lotes')
                  ->whereColumn('stock_lotes.produto_id', 'produtos.id')
                  ->where('stock_lotes.unidade_id', $unidadeId)
                  ->where('stock_lotes.quantidade', '>', 0);
            });
        } 
        // Se tem apenas unidade_id (sem com_estoque), busca produtos que pertencem àquela unidade
        else if ($temUnidadeId) {
            $query->where('produtos.unidade_id', $request->unidade_id);
        }
        // Se tem apenas com_estoque (sem unidade_id), busca produtos com estoque em qualquer unidade
        else if ($temComEstoque) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('stock_lotes')
                  ->whereColumn('stock_lotes.produto_id', 'produtos.id')
                  ->where('stock_lotes.quantidade', '>', 0);
            });
        }
        
        // Se não tiver filtro de unidade, retorna todos (quando ?todas=1)
        if (!$request->has('unidade_id') || $request->has('todas')) {
            // Retorna todos os produtos
        }
        
        // Filtro de pesquisa por nome
        if ($request->has('search') && trim((string)$request->search) !== '') {
            $termo = '%' . trim((string)$request->search) . '%';
            $query->where('produtos.nome', 'like', $termo);
        }
        
        $produtos = $query->orderBy('produtos.nome')->get();
        
        // Garante que o campo 'ativo' existe e está no formato correto
        $produtos = $produtos->map(function($produto) {
            // Se ativo não existir, verifica se a coluna existe na tabela
            if (!isset($produto->ativo)) {
                // Tenta verificar se a coluna existe no banco
                try {
                    $schema = DB::select("SHOW COLUMNS FROM produtos LIKE 'ativo'");
                    if (empty($schema)) {
                        // Se a coluna não existir, assume que todos os produtos estão ativos
                        $produto->ativo = 1;
                    } else {
                        // Se a coluna existir mas o valor for null, assume ativo
                        $produto->ativo = 1;
                    }
                } catch (\Exception $e) {
                    // Em caso de erro, assume que está ativo
                    $produto->ativo = 1;
                }
            }
            // Converte para inteiro se for string ou boolean
            if (is_bool($produto->ativo)) {
                $produto->ativo = $produto->ativo ? 1 : 0;
            } else {
                $produto->ativo = is_numeric($produto->ativo) ? (int)$produto->ativo : ($produto->ativo ? 1 : 0);
            }
            return $produto;
        });
        
        return response()->json($produtos);
    } catch (\Exception $e) {
        \Log::error('Erro ao buscar produtos: ' . $e->getMessage());
        return response()->json([], 200); // Retorna array vazio em caso de erro
    }
});

Route::get('/produtos/{id}', function ($id) {
    // Busca produto com join na tabela unidades para trazer o nome da unidade
    $produto = DB::table('produtos')
        ->leftJoin('unidades', 'produtos.unidade_id', '=', 'unidades.id')
        ->select(
            'produtos.*',
            'unidades.nome as unidade_nome'  // Adiciona o nome da unidade
        )
        ->where('produtos.id', $id)
        ->first();
    
    if (!$produto) {
        return response()->json(['error' => 'Produto não encontrado'], 404);
    }
    return response()->json($produto);
});

Route::get('/estoque/resumo', function (Request $request) {
    try {
        $query = DB::table('stock_lotes')
            ->where('stock_lotes.quantidade', '>', 0)
            ->select(
                DB::raw('SUM(stock_lotes.quantidade * stock_lotes.custo_unitario) as valor_total'),
                DB::raw('SUM(stock_lotes.quantidade) as qtd_total')
            );
        if ($request->has('unidade_id') && $request->unidade_id) {
            $query->where('stock_lotes.unidade_id', $request->unidade_id);
        }
        $resumo = $query->first();
        return response()->json([
            'valor_total' => floatval($resumo->valor_total ?? 0),
            'qtd_total' => floatval($resumo->qtd_total ?? 0),
        ]);
    } catch (\Exception $e) {
        \Log::error('Erro ao buscar resumo estoque: ' . $e->getMessage());
        return response()->json(['valor_total' => 0, 'qtd_total' => 0]);
    }
});

Route::get('/produtos/{id}/estoque', function ($id) {
    try {
        $produto = DB::table('produtos')->where('id', $id)->first();
        if (!$produto) {
            return response()->json(['error' => 'Produto não encontrado'], 404);
        }
        
        $driver = DB::connection()->getDriverName();
        $concatLotes = $driver === 'pgsql'
            ? "STRING_AGG(DISTINCT stock_lotes.codigo_lote, ', ' ORDER BY stock_lotes.codigo_lote)"
            : "GROUP_CONCAT(DISTINCT stock_lotes.codigo_lote ORDER BY stock_lotes.codigo_lote SEPARATOR ', ')";
        $concatLocais = $driver === 'pgsql'
            ? "STRING_AGG(DISTINCT locais.nome, ', ' ORDER BY locais.nome)"
            : "GROUP_CONCAT(DISTINCT locais.nome ORDER BY locais.nome SEPARATOR ', ')";

        $estoquePorUnidade = DB::table('stock_lotes')
            ->leftJoin('unidades', 'stock_lotes.unidade_id', '=', 'unidades.id')
            ->leftJoin('lotes', function($join) {
                $join->on('lotes.numero_lote', '=', 'stock_lotes.codigo_lote')
                     ->on('lotes.produto_id', '=', 'stock_lotes.produto_id')
                     ->on('lotes.unidade_id', '=', 'stock_lotes.unidade_id');
            })
            ->leftJoin('locais', 'lotes.local_id', '=', 'locais.id')
            ->select(
                'stock_lotes.unidade_id',
                'unidades.nome as unidade_nome',
                DB::raw('SUM(stock_lotes.quantidade) as qtd_total'),
                DB::raw('AVG(stock_lotes.custo_unitario) as valor_unitario_medio'),
                DB::raw('SUM(stock_lotes.quantidade * stock_lotes.custo_unitario) as valor_total'),
                DB::raw('COUNT(DISTINCT stock_lotes.codigo_lote) as num_lotes'),
                DB::raw($concatLotes . ' as codigos_lote'),
                DB::raw($concatLocais . ' as nomes_locais')
            )
            ->where('stock_lotes.produto_id', $id)
            ->where('stock_lotes.quantidade', '>', 0)
            ->groupBy('stock_lotes.unidade_id', 'unidades.nome')
            ->get();

        // Calcula totais
        $qtdTotal = $estoquePorUnidade->sum('qtd_total');
        $valorTotal = $estoquePorUnidade->sum('valor_total');
        $valorUnitarioMedio = $qtdTotal > 0 ? ($valorTotal / $qtdTotal) : 0;

        // Formata os dados para o frontend
        $estoquePorUnidadeFormatado = $estoquePorUnidade->map(function($item) use ($id) {
            $codigosLote = trim($item->codigos_lote ?? '');
            $locais = trim($item->nomes_locais ?? '');

            // Busca lotes detalhados desta unidade para o modal de detalhes
            $lotesDetalhados = DB::table('stock_lotes')
                ->where('stock_lotes.produto_id', $id)
                ->where('stock_lotes.unidade_id', $item->unidade_id)
                ->where('stock_lotes.quantidade', '>', 0)
                ->select(
                    'stock_lotes.codigo_lote',
                    'stock_lotes.quantidade',
                    'stock_lotes.custo_unitario',
                    'stock_lotes.data_validade',
                    DB::raw('stock_lotes.quantidade * stock_lotes.custo_unitario as valor_total')
                )
                ->orderByDesc('stock_lotes.id')
                ->get()
                ->map(function($lote) {
                    return [
                        'codigo_lote'    => $lote->codigo_lote,
                        'quantidade'     => floatval($lote->quantidade),
                        'custo_unitario' => floatval($lote->custo_unitario),
                        'valor_total'    => floatval($lote->valor_total),
                        'data_validade'  => $lote->data_validade,
                    ];
                });

            return [
                'unidade_id'           => $item->unidade_id,
                'unidade_nome'         => $item->unidade_nome ?? 'N/A',
                'locais'               => $locais ?: null,
                'qtd_total'            => floatval($item->qtd_total ?? 0),
                'valor_unitario_medio' => floatval($item->valor_unitario_medio ?? 0),
                'valor_total'          => floatval($item->valor_total ?? 0),
                'num_lotes'            => intval($item->num_lotes ?? 0),
                'codigos_lote'         => $codigosLote ?: null,
                'lotes_detalhados'     => $lotesDetalhados,
            ];
        });
        
        $estoqueMinimo = floatval($produto->estoque_minimo ?? 0);
        $abaixoDoMinimo = $estoqueMinimo > 0 && $qtdTotal < $estoqueMinimo;

        return response()->json([
            'produto' => [
                'id' => $produto->id,
                'nome' => $produto->nome,
                'unidade_base' => $produto->unidade_base ?? 'UND',
                'estoque_minimo' => $estoqueMinimo,
            ],
            'estoque_por_unidade' => $estoquePorUnidadeFormatado,
            'estoque_total' => [
                'qtd_total' => floatval($qtdTotal),
                'valor_total' => floatval($valorTotal),
                'valor_unitario_medio' => floatval($valorUnitarioMedio),
                'abaixo_do_minimo' => $abaixoDoMinimo,
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('Erro ao buscar estoque do produto: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao buscar estoque: ' . $e->getMessage()], 500);
    }
});

Route::post('/produtos', function (Request $request) {
    \Log::info('📦 POST /produtos - Requisição recebida', [
        'payload' => $request->all()
    ]);
    
    try {
        $data = $request->validate([
            'nome' => 'required|string',
            'categoria' => 'required|string',
            'unidade_base' => 'required|string',
            'unidade_id' => 'nullable|integer',
            'codigo_barras' => 'nullable|string',
            'descricao' => 'nullable|string',
            'custo_medio' => 'nullable|numeric',
            'estoque_minimo' => 'nullable|numeric',
            'ativo' => 'nullable|integer',
        ]);
        
        \Log::info('✅ Dados validados:', $data);
        
        // Verifica duplicata por nome (ignorando maiúsculas/minúsculas e espaços)
        $nomeNorm = strtolower(trim($data['nome']));
        $existe = DB::table('produtos')
            ->whereRaw('LOWER(TRIM(nome)) = ?', [$nomeNorm])
            ->exists();
        if ($existe) {
            return response()->json([
                'error' => 'Já existe um produto com este nome.',
                'message' => 'Já existe um produto com este nome.',
            ], 422);
        }
        
        // Gera código_barras automaticamente se não fornecido
        if (empty(trim((string)($data['codigo_barras'] ?? '')))) {
            $data['codigo_barras'] = 'PROD-' . date('YmdHis') . '-' . rand(1000, 9999);
            \Log::info('🔢 Código de barras gerado:', ['codigo' => $data['codigo_barras']]);
        }
        
        // Garante que campos NOT NULL tenham valor padrão (MySQL não aceita null)
        $data['custo_medio'] = $data['custo_medio'] ?? 0;
        $data['estoque_minimo'] = $data['estoque_minimo'] ?? 0;
        $data['ativo'] = $data['ativo'] ?? 1;
        
        $id = DB::table('produtos')->insertGetId($data);
        \Log::info('💾 Produto salvo no banco', ['id' => $id]);
        
        $produto = DB::table('produtos')->where('id', $id)->first();
        \Log::info('✅ Produto retornado:', ['produto' => $produto]);
        
        return response()->json($produto, 201);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::error('❌ Erro de validação:', [
            'errors' => $e->errors(),
            'message' => $e->getMessage()
        ]);
        return response()->json([
            'error' => 'Erro de validação',
            'details' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        \Log::error('❌ Erro ao salvar produto:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'error' => 'Erro ao salvar produto',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::put('/produtos/{id}', function (Request $request, $id) {
    $data = $request->all();
    if (!empty($data['nome'])) {
        $nomeNorm = strtolower(trim($data['nome']));
        $existe = DB::table('produtos')
            ->whereRaw('LOWER(TRIM(nome)) = ?', [$nomeNorm])
            ->where('id', '!=', $id)
            ->exists();
        if ($existe) {
            return response()->json([
                'error' => 'Já existe um produto com este nome.',
                'message' => 'Já existe um produto com este nome.',
            ], 422);
        }
    }
    DB::table('produtos')->where('id', $id)->update($data);
    return response()->json(DB::table('produtos')->where('id', $id)->first());
});

// Rota para desativar/ativar produto (solução recomendada ao invés de excluir)
Route::put('/produtos/{id}/desativar', function (Request $request, $id) {
    try {
        $produto = DB::table('produtos')->where('id', $id)->first();
        if (!$produto) {
            return response()->json(['error' => 'Produto não encontrado'], 404)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
        
        // Verifica se a coluna 'ativo' existe na tabela
        $schema = DB::select("SHOW COLUMNS FROM produtos LIKE 'ativo'");
        if (empty($schema)) {
            // Se não existir, cria a coluna
            DB::statement('ALTER TABLE produtos ADD COLUMN ativo TINYINT(1) DEFAULT 1');
        }
        
        // Desativa o produto (ativo = 0)
        DB::table('produtos')->where('id', $id)->update(['ativo' => 0]);
        
        \Log::info("Produto desativado: ID {$id} - {$produto->nome}");
        
        return response()->json([
            'success' => true,
            'message' => 'Produto desativado com sucesso',
            'produto' => DB::table('produtos')->where('id', $id)->first()
        ], 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            
    } catch (\Exception $e) {
        \Log::error('Erro ao desativar produto: ' . $e->getMessage());
        return response()->json([
            'error' => 'Erro ao desativar produto',
            'message' => $e->getMessage()
        ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
});

Route::put('/produtos/{id}/ativar', function (Request $request, $id) {
    try {
        $produto = DB::table('produtos')->where('id', $id)->first();
        if (!$produto) {
            return response()->json(['error' => 'Produto não encontrado'], 404)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
        
        // Verifica se a coluna 'ativo' existe na tabela
        $schema = DB::select("SHOW COLUMNS FROM produtos LIKE 'ativo'");
        if (empty($schema)) {
            // Se não existir, cria a coluna
            DB::statement('ALTER TABLE produtos ADD COLUMN ativo TINYINT(1) DEFAULT 1');
        }
        
        // Ativa o produto (ativo = 1)
        DB::table('produtos')->where('id', $id)->update(['ativo' => 1]);
        
        \Log::info("Produto ativado: ID {$id} - {$produto->nome}");
        
        return response()->json([
            'success' => true,
            'message' => 'Produto ativado com sucesso',
            'produto' => DB::table('produtos')->where('id', $id)->first()
        ], 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            
    } catch (\Exception $e) {
        \Log::error('Erro ao ativar produto: ' . $e->getMessage());
        return response()->json([
            'error' => 'Erro ao ativar produto',
            'message' => $e->getMessage()
        ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
});

Route::options('/produtos/{id}/remover', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

Route::options('/produtos/{id}/desativar', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

Route::options('/produtos/{id}/ativar', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

Route::delete('/produtos/{id}/remover', function (Request $request, $id) {
    try {
        // Valida se o produto existe
        $produto = DB::table('produtos')->where('id', $id)->first();
        if (!$produto) {
            return response()->json(['error' => 'Produto não encontrado'], 404)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
        
        // ============================================
        // VERIFICA TODAS AS CONEXÕES POSSÍVEIS
        // ============================================
        
        // Verifica conexões em todas as tabelas relacionadas
        $countMovimentacoes = DB::table('movimentacoes')->where('produto_id', $id)->count();
        $countLotes = DB::table('lotes')->where('produto_id', $id)->count();
        $countStock = DB::table('stock_lotes')->where('produto_id', $id)->count();
        $countListas = DB::table('listas_itens')->where('produto_id', $id)->count();
        
        // Verifica se existe alguma conexão
        $temConexoes = ($countMovimentacoes > 0 || $countLotes > 0 || $countStock > 0 || $countListas > 0);
        
        // Prepara aviso detalhado sobre as conexões
        $avisos = [];
        $detalhesExclusao = [];
        $totalRegistros = 0;
        
        if ($countMovimentacoes > 0) {
            $avisos[] = "⚠️ {$countMovimentacoes} movimentação(ões) de estoque";
            $detalhesExclusao[] = "{$countMovimentacoes} movimentação(ões)";
            $totalRegistros += $countMovimentacoes;
        }
        
        if ($countLotes > 0) {
            $avisos[] = "⚠️ {$countLotes} lote(s) cadastrado(s)";
            $detalhesExclusao[] = "{$countLotes} lote(s)";
            $totalRegistros += $countLotes;
        }
        
        if ($countStock > 0) {
            $avisos[] = "⚠️ {$countStock} registro(s) de estoque";
            $detalhesExclusao[] = "{$countStock} registro(s) de estoque";
            $totalRegistros += $countStock;
        }
        
        if ($countListas > 0) {
            $avisos[] = "⚠️ {$countListas} item(ns) em listas de compras";
            $detalhesExclusao[] = "{$countListas} item(ns) de lista(s)";
            $totalRegistros += $countListas;
        }
        
        // ============================================
        // EXCLUSÃO FORÇADA (SEMPRE PERMITE DELETAR)
        // ============================================
        
        if ($temConexoes) {
            \Log::warning("EXCLUSÃO FORÇADA do produto ID {$id} - {$produto->nome}. Produto possui conexões com outras telas!");
            \Log::warning("Conexões encontradas: " . implode(', ', $detalhesExclusao));
            
            // Apaga registros relacionados em cascata (ordem correta para evitar problemas de foreign key)
            
            // 1. Apaga movimentações primeiro (podem referenciar lotes)
            if ($countMovimentacoes > 0) {
                $deletedMov = DB::table('movimentacoes')->where('produto_id', $id)->delete();
                \Log::info("  ✓ {$deletedMov} movimentação(ões) apagada(s)");
            }
            
            // 2. Apaga registros de estoque (stock_lotes)
            if ($countStock > 0) {
                $deletedStock = DB::table('stock_lotes')->where('produto_id', $id)->delete();
                \Log::info("  ✓ {$deletedStock} registro(s) de estoque apagado(s)");
            }
            
            // 3. Apaga lotes
            if ($countLotes > 0) {
                $deletedLotes = DB::table('lotes')->where('produto_id', $id)->delete();
                \Log::info("  ✓ {$deletedLotes} lote(s) apagado(s)");
            }
            
            // 4. Apaga itens de listas
            if ($countListas > 0) {
                $deletedListas = DB::table('listas_itens')->where('produto_id', $id)->delete();
                \Log::info("  ✓ {$deletedListas} item(ns) de lista(s) apagado(s)");
            }
        }
        
        // Exclui o produto
        DB::table('produtos')->where('id', $id)->delete();
        
        \Log::info("Produto excluído: ID {$id} - {$produto->nome}" . ($temConexoes ? ' (COM CONEXÕES - FORÇADO)' : ' (SEM CONEXÕES)'));
        
        // Retorna resposta com aviso se havia conexões
        $response = [
            'success' => true,
            'message' => $temConexoes 
                ? "Produto excluído com sucesso. ⚠️ ATENÇÃO: Foram apagados {$totalRegistros} registro(s) relacionados em cascata."
                : 'Produto excluído com sucesso',
            'produto_id' => $id,
            'produto_nome' => $produto->nome
        ];
        
        if ($temConexoes) {
            $response['warning'] = true;
            $response['avisos'] = $avisos;
            $response['detalhes_exclusao'] = implode(', ', $detalhesExclusao);
            $response['total_registros_apagados'] = $totalRegistros;
            $response['conexoes_encontradas'] = [
                'movimentacoes' => $countMovimentacoes,
                'lotes' => $countLotes,
                'estoque' => $countStock,
                'listas' => $countListas
            ];
        }
        
        return response()->json($response, 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            
    } catch (\Exception $e) {
        \Log::error('Erro ao excluir produto: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        
        // Tenta identificar o problema específico
        $errorMessage = $e->getMessage();
        $isForeignKeyError = (strpos($errorMessage, 'foreign key constraint') !== false || 
                             strpos($errorMessage, 'foreign key') !== false ||
                             strpos($errorMessage, 'Cannot delete') !== false);
        
        if ($isForeignKeyError) {
            // Se ainda houver erro de foreign key, tenta apagar tudo manualmente
            try {
                \Log::warning("Tentando exclusão manual devido a erro de foreign key...");
                
                // Tenta apagar tudo novamente em ordem mais agressiva
                DB::table('movimentacoes')->where('produto_id', $id)->delete();
                DB::table('stock_lotes')->where('produto_id', $id)->delete();
                DB::table('lotes')->where('produto_id', $id)->delete();
                DB::table('listas_itens')->where('produto_id', $id)->delete();
                DB::table('produtos')->where('id', $id)->delete();
                
                \Log::info("Exclusão manual bem-sucedida após erro de foreign key");
                
                return response()->json([
                    'success' => true,
                    'message' => 'Produto excluído com sucesso (exclusão manual após erro de constraint)',
                    'warning' => true
                ], 200)
                    ->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
                    
            } catch (\Exception $e2) {
                \Log::error('Erro na exclusão manual: ' . $e2->getMessage());
            }
        }
        
        return response()->json([
            'error' => 'Erro ao excluir produto',
            'message' => $errorMessage,
            'details' => 'Verifique os logs do servidor para mais informações'
        ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
});

Route::delete('/produtos/{id}', function (Request $request, $id) {
    // Rota alternativa - usa a mesma lógica de /remover (exclusão forçada com avisos)
    try {
        // Valida se o produto existe
        $produto = DB::table('produtos')->where('id', $id)->first();
        if (!$produto) {
            return response()->json(['error' => 'Produto não encontrado'], 404)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
        
        // Verifica todas as conexões
        $countMovimentacoes = DB::table('movimentacoes')->where('produto_id', $id)->count();
        $countLotes = DB::table('lotes')->where('produto_id', $id)->count();
        $countStock = DB::table('stock_lotes')->where('produto_id', $id)->count();
        $countListas = DB::table('listas_itens')->where('produto_id', $id)->count();
        
        $temConexoes = ($countMovimentacoes > 0 || $countLotes > 0 || $countStock > 0 || $countListas > 0);
        
        $avisos = [];
        $detalhesExclusao = [];
        $totalRegistros = 0;
        
        if ($countMovimentacoes > 0) {
            $avisos[] = "⚠️ {$countMovimentacoes} movimentação(ões)";
            $detalhesExclusao[] = "{$countMovimentacoes} movimentação(ões)";
            $totalRegistros += $countMovimentacoes;
            DB::table('movimentacoes')->where('produto_id', $id)->delete();
        }
        
        if ($countStock > 0) {
            $avisos[] = "⚠️ {$countStock} registro(s) de estoque";
            $detalhesExclusao[] = "{$countStock} registro(s) de estoque";
            $totalRegistros += $countStock;
            DB::table('stock_lotes')->where('produto_id', $id)->delete();
        }
        
        if ($countLotes > 0) {
            $avisos[] = "⚠️ {$countLotes} lote(s)";
            $detalhesExclusao[] = "{$countLotes} lote(s)";
            $totalRegistros += $countLotes;
            DB::table('lotes')->where('produto_id', $id)->delete();
        }
        
        if ($countListas > 0) {
            $avisos[] = "⚠️ {$countListas} item(ns) em listas";
            $detalhesExclusao[] = "{$countListas} item(ns) de lista(s)";
            $totalRegistros += $countListas;
            DB::table('listas_itens')->where('produto_id', $id)->delete();
        }
        
        // Exclui o produto
        DB::table('produtos')->where('id', $id)->delete();
        
        $response = [
            'success' => true,
            'message' => $temConexoes 
                ? "Produto excluído com sucesso. ⚠️ ATENÇÃO: Foram apagados {$totalRegistros} registro(s) relacionados."
                : 'Produto excluído com sucesso'
        ];
        
        if ($temConexoes) {
            $response['warning'] = true;
            $response['avisos'] = $avisos;
            $response['total_registros_apagados'] = $totalRegistros;
        }
        
        return response()->json($response, 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            
    } catch (\Exception $e) {
        \Log::error('Erro ao excluir produto (rota alternativa): ' . $e->getMessage());
        
        // Tenta exclusão manual em caso de erro
        try {
            DB::table('movimentacoes')->where('produto_id', $id)->delete();
            DB::table('stock_lotes')->where('produto_id', $id)->delete();
            DB::table('lotes')->where('produto_id', $id)->delete();
            DB::table('listas_itens')->where('produto_id', $id)->delete();
            DB::table('produtos')->where('id', $id)->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Produto excluído com sucesso (exclusão manual)',
                'warning' => true
            ], 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Exception $e2) {
            return response()->json([
                'error' => 'Erro ao excluir produto',
                'message' => $e->getMessage()
            ], 500)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }
});

// ============================================
// FICHAS TÉCNICAS (pratos — persistência no banco)
// ============================================

$mapFichaTecnicaRow = static function ($row) {
    $ing = [];
    if (!empty($row->ingredientes_json)) {
        $decoded = json_decode($row->ingredientes_json, true);
        $ing = is_array($decoded) ? $decoded : [];
    }
    return [
        'id' => (int) $row->id,
        'nome_prato' => $row->nome_prato,
        'tempo_preparo' => $row->tempo_preparo,
        'responsavel_tecnico' => $row->responsavel_tecnico,
        'foto_base64' => $row->foto_base64,
        'preco_prato' => $row->preco_prato !== null ? (float) $row->preco_prato : null,
        'sugestao_venda' => $row->sugestao_venda !== null ? (float) $row->sugestao_venda : null,
        'modo_preparo' => $row->modo_preparo,
        'ingredientes' => $ing,
        'updatedAt' => $row->updated_at ? \Illuminate\Support\Carbon::parse($row->updated_at)->toIso8601String() : null,
    ];
};

Route::get('/fichas-tecnicas', function () use ($mapFichaTecnicaRow) {
    if (!Schema::hasTable('fichas_tecnicas')) {
        return response()->json([]);
    }
    try {
        $rows = DB::table('fichas_tecnicas')->orderByDesc('updated_at')->orderByDesc('id')->get();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $mapFichaTecnicaRow($row);
        }
        return response()->json($out);
    } catch (\Exception $e) {
        \Log::error('GET /fichas-tecnicas: ' . $e->getMessage());
        return response()->json([]);
    }
});

Route::post('/fichas-tecnicas', function (Request $request) use ($mapFichaTecnicaRow) {
    if (!Schema::hasTable('fichas_tecnicas')) {
        return response()->json([
            'error' => 'Tabela fichas_tecnicas não existe. Execute: php artisan migrate',
            'message' => 'Tabela fichas_tecnicas não existe. Execute: php artisan migrate',
        ], 503);
    }
    try {
        $data = $request->validate([
            'nome_prato' => 'required|string|max:500',
            'tempo_preparo' => 'nullable|string|max:255',
            'responsavel_tecnico' => 'nullable|string|max:500',
            'foto_base64' => 'nullable|string',
            'modo_preparo' => 'nullable|string',
            'preco_prato' => 'nullable|numeric',
            'sugestao_venda' => 'nullable|numeric',
            'ingredientes' => 'nullable|array',
        ]);
        $ingredientes = $data['ingredientes'] ?? [];
        $ingJson = json_encode($ingredientes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $now = now();
        $id = DB::table('fichas_tecnicas')->insertGetId([
            'nome_prato' => $data['nome_prato'],
            'tempo_preparo' => $data['tempo_preparo'] ?? null,
            'responsavel_tecnico' => $data['responsavel_tecnico'] ?? null,
            'foto_base64' => $data['foto_base64'] ?? null,
            'preco_prato' => isset($data['preco_prato']) ? $data['preco_prato'] : null,
            'sugestao_venda' => isset($data['sugestao_venda']) ? $data['sugestao_venda'] : null,
            'modo_preparo' => $data['modo_preparo'] ?? null,
            'ingredientes_json' => $ingJson,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $row = DB::table('fichas_tecnicas')->where('id', $id)->first();
        return response()->json($mapFichaTecnicaRow($row), 201);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['error' => 'Dados inválidos', 'message' => 'Dados inválidos', 'details' => $e->errors()], 422);
    } catch (\JsonException $e) {
        return response()->json(['error' => 'Ingredientes inválidos', 'message' => 'Ingredientes inválidos'], 422);
    } catch (\Exception $e) {
        \Log::error('POST /fichas-tecnicas: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao salvar ficha técnica', 'message' => $e->getMessage()], 500);
    }
});

Route::put('/fichas-tecnicas/{id}', function (Request $request, $id) use ($mapFichaTecnicaRow) {
    if (!Schema::hasTable('fichas_tecnicas')) {
        return response()->json([
            'error' => 'Tabela fichas_tecnicas não existe. Execute: php artisan migrate',
            'message' => 'Tabela fichas_tecnicas não existe. Execute: php artisan migrate',
        ], 503);
    }
    $id = (int) $id;
    $existing = DB::table('fichas_tecnicas')->where('id', $id)->first();
    if (!$existing) {
        return response()->json(['error' => 'Ficha não encontrada', 'message' => 'Ficha não encontrada'], 404);
    }
    try {
        $data = $request->validate([
            'nome_prato' => 'required|string|max:500',
            'tempo_preparo' => 'nullable|string|max:255',
            'responsavel_tecnico' => 'nullable|string|max:500',
            'foto_base64' => 'nullable|string',
            'modo_preparo' => 'nullable|string',
            'preco_prato' => 'nullable|numeric',
            'sugestao_venda' => 'nullable|numeric',
            'ingredientes' => 'nullable|array',
        ]);
        $ingredientes = $data['ingredientes'] ?? [];
        $ingJson = json_encode($ingredientes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        DB::table('fichas_tecnicas')->where('id', $id)->update([
            'nome_prato' => $data['nome_prato'],
            'tempo_preparo' => $data['tempo_preparo'] ?? null,
            'responsavel_tecnico' => $data['responsavel_tecnico'] ?? null,
            'foto_base64' => array_key_exists('foto_base64', $data) ? $data['foto_base64'] : $existing->foto_base64,
            'preco_prato' => array_key_exists('preco_prato', $data) ? $data['preco_prato'] : $existing->preco_prato,
            'sugestao_venda' => array_key_exists('sugestao_venda', $data) ? $data['sugestao_venda'] : $existing->sugestao_venda,
            'modo_preparo' => $data['modo_preparo'] ?? null,
            'ingredientes_json' => $ingJson,
            'updated_at' => now(),
        ]);
        $row = DB::table('fichas_tecnicas')->where('id', $id)->first();
        return response()->json($mapFichaTecnicaRow($row));
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['error' => 'Dados inválidos', 'message' => 'Dados inválidos', 'details' => $e->errors()], 422);
    } catch (\JsonException $e) {
        return response()->json(['error' => 'Ingredientes inválidos', 'message' => 'Ingredientes inválidos'], 422);
    } catch (\Exception $e) {
        \Log::error('PUT /fichas-tecnicas/{id}: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao atualizar ficha técnica', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/fichas-tecnicas/{id}', function ($id) {
    if (!Schema::hasTable('fichas_tecnicas')) {
        return response()->json(['error' => 'Tabela fichas_tecnicas não existe.', 'message' => 'Tabela fichas_tecnicas não existe.'], 503);
    }
    $id = (int) $id;
    $deleted = DB::table('fichas_tecnicas')->where('id', $id)->delete();
    if (!$deleted) {
        return response()->json(['error' => 'Ficha não encontrada', 'message' => 'Ficha não encontrada'], 404);
    }
    return response()->json(['success' => true, 'message' => 'Ficha excluída.']);
});

// ============================================
// UNIDADES
// ============================================

// CORS preflight para rotas de unidades
Route::options('/unidades', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::options('/unidades/{id}', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::get('/unidades', function (Request $request) {
    $query = DB::table('unidades')
        ->leftJoin('usuarios', 'unidades.gerente_usuario_id', '=', 'usuarios.id')
        ->select(
            'unidades.*',
            'usuarios.nome as gerente_nome'
        );
    
    // Se não tiver filtro, retorna todas (quando ?todas=1)
    $unidades = $query->orderBy('unidades.nome')->get();
    return response()->json($unidades);
});

Route::get('/unidades/{id}', function ($id) {
    $unidade = DB::table('unidades')
        ->leftJoin('usuarios', 'unidades.gerente_usuario_id', '=', 'usuarios.id')
        ->select(
            'unidades.*',
            'usuarios.nome as gerente_nome'
        )
        ->where('unidades.id', $id)
        ->first();
    if (!$unidade) {
        return response()->json(['error' => 'Unidade não encontrada'], 404);
    }
    return response()->json($unidade);
});

Route::post('/unidades', function (Request $request) {
    // Verifica se o usuário está autenticado
    $usuarioId = $request->header('X-Usuario-Id');
    if (!$usuarioId) {
        return response()->json(['error' => 'Usuário não autenticado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Busca o usuário que está fazendo a requisição
    $usuarioAutenticado = DB::table('usuarios')->where('id', $usuarioId)->first();
    if (!$usuarioAutenticado) {
        return response()->json(['error' => 'Usuário autenticado não encontrado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Verifica se é ADMIN
    $perfil = strtoupper(trim($usuarioAutenticado->perfil ?? ''));
    if ($perfil !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem criar unidades'], 403)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    $data = $request->all();
    $id = DB::table('unidades')->insertGetId($data);
    return response()->json(DB::table('unidades')->where('id', $id)->first(), 201)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::put('/unidades/{id}', function (Request $request, $id) {
    // Verifica se o usuário está autenticado
    $usuarioId = $request->header('X-Usuario-Id');
    if (!$usuarioId) {
        return response()->json(['error' => 'Usuário não autenticado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Busca o usuário que está fazendo a requisição
    $usuarioAutenticado = DB::table('usuarios')->where('id', $usuarioId)->first();
    if (!$usuarioAutenticado) {
        return response()->json(['error' => 'Usuário autenticado não encontrado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Verifica se é ADMIN
    $perfil = strtoupper(trim($usuarioAutenticado->perfil ?? ''));
    if ($perfil !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem editar unidades'], 403)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    $data = $request->all();
    DB::table('unidades')->where('id', $id)->update($data);
    return response()->json(DB::table('unidades')->where('id', $id)->first())
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::delete('/unidades/{id}', function (Request $request, $id) {
    // Verifica se o usuário está autenticado
    $usuarioId = $request->header('X-Usuario-Id');
    if (!$usuarioId) {
        return response()->json(['error' => 'Usuário não autenticado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Busca o usuário que está fazendo a requisição
    $usuarioAutenticado = DB::table('usuarios')->where('id', $usuarioId)->first();
    if (!$usuarioAutenticado) {
        return response()->json(['error' => 'Usuário autenticado não encontrado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Verifica se é ADMIN
    $perfil = strtoupper(trim($usuarioAutenticado->perfil ?? ''));
    if ($perfil !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem excluir unidades'], 403)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    DB::table('unidades')->where('id', $id)->delete();
    return response()->json(['message' => 'Unidade removida'])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::delete('/unidades/{id}/remover', function (Request $request, $id) {
    // Verifica se o usuário está autenticado
    $usuarioId = $request->header('X-Usuario-Id');
    if (!$usuarioId) {
        return response()->json(['error' => 'Usuário não autenticado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Busca o usuário que está fazendo a requisição
    $usuarioAutenticado = DB::table('usuarios')->where('id', $usuarioId)->first();
    if (!$usuarioAutenticado) {
        return response()->json(['error' => 'Usuário autenticado não encontrado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Verifica se é ADMIN
    $perfil = strtoupper(trim($usuarioAutenticado->perfil ?? ''));
    if ($perfil !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem excluir unidades'], 403)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    DB::table('unidades')->where('id', $id)->delete();
    return response()->json(['message' => 'Unidade removida'])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// ============================================
// USUÁRIOS
// ============================================

// CORS preflight para POST /usuarios
Route::options('/usuarios', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// CORS preflight para PUT /usuarios/{id}
Route::options('/usuarios/{id}', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'PUT, GET, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::get('/usuarios', function (Request $request) {
    $usuarios = DB::table('usuarios')
        ->leftJoin('unidades', 'usuarios.unidade_id', '=', 'unidades.id')
        ->select(
            'usuarios.*',
            'unidades.nome as unidade_nome'
        )
        ->orderBy('usuarios.nome')
        ->get();
    // Remove senha_hash da resposta
    $usuarios = $usuarios->map(function($u) {
        unset($u->senha_hash);
        return $u;
    });
    return response()->json($usuarios);
});

Route::get('/usuarios/{id}', function ($id) {
    $usuario = DB::table('usuarios')
        ->leftJoin('unidades', 'usuarios.unidade_id', '=', 'unidades.id')
        ->select(
            'usuarios.*',
            'unidades.nome as unidade_nome'
        )
        ->where('usuarios.id', $id)
        ->first();
    if (!$usuario) {
        return response()->json(['error' => 'Usuário não encontrado'], 404);
    }
    unset($usuario->senha_hash);
    return response()->json($usuario);
});

Route::post('/usuarios', function (Request $request) {
    try {
        // Log para debug
        \Log::info('POST /usuarios - Dados recebidos:', $request->all());
        
        // Obtém todos os dados do request (funciona com FormData e JSON)
        $allData = $request->all();
        
        // Normaliza unidade_id antes da validação
        if (isset($allData['unidade_id'])) {
            if ($allData['unidade_id'] === '' || $allData['unidade_id'] === 'null' || $allData['unidade_id'] === null) {
                $allData['unidade_id'] = null;
                $request->merge(['unidade_id' => null]);
            } else {
                $allData['unidade_id'] = (int)$allData['unidade_id'];
                $request->merge(['unidade_id' => $allData['unidade_id']]);
            }
        }
        
        // Validação dos campos obrigatórios
        $rules = [
            'nome' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'perfil' => 'required|string|max:50',
            'senha' => 'required|string|min:6|max:255',
        ];
        
        // Adiciona validação de unidade_id apenas se foi enviado e não for null
        if (isset($allData['unidade_id']) && $allData['unidade_id'] !== null && $allData['unidade_id'] !== '') {
            $rules['unidade_id'] = 'nullable|integer|exists:unidades,id';
        } else {
            // Se não foi enviado ou é null, permite nullable sem validação de exists
            $rules['unidade_id'] = 'nullable';
        }
        
        $validated = $request->validate($rules);

        // Prepara os dados para inserção
        $senhaLimpa = trim($validated['senha']);
        if (strlen($senhaLimpa) < 6) {
            return response()->json(['error' => 'A senha deve ter no mínimo 6 caracteres'], 422)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
        }

        $perfilNormalizado = strtoupper(trim($validated['perfil']));
        
        \Log::info('POST /usuarios - Perfil normalizado:', [
            'perfil_original' => $validated['perfil'],
            'perfil_normalizado' => $perfilNormalizado,
            'is_bar' => $perfilNormalizado === 'BAR'
        ]);
        
        // Validação específica para perfil BAR
        $perfisValidos = ['ADMIN', 'GERENTE', 'ESTOQUISTA', 'COZINHA', 'BAR', 'FINANCEIRO', 'ASSISTENTE_ADMINISTRATIVO', 'VISUALIZADOR', 'ATENDENTE', 'ATENDENTE_CAIXA'];
        if (!in_array($perfilNormalizado, $perfisValidos)) {
            \Log::warning('POST /usuarios - Perfil inválido:', ['perfil' => $perfilNormalizado]);
            return response()->json(['error' => 'Perfil inválido: ' . $perfilNormalizado], 422)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
        }
        
        $data = [
            'nome' => trim($validated['nome']),
            'email' => trim($validated['email']),
            'perfil' => $perfilNormalizado,
            'senha_hash' => Hash::make($senhaLimpa),
        ];

        // Adiciona campo ativo apenas se fornecido
        if (isset($allData['ativo'])) {
            $data['ativo'] = $allData['ativo'] ? 1 : 0;
        } else {
            $data['ativo'] = 1; // Padrão: ativo
        }

        // Adiciona unidade_id se fornecido
        if (isset($allData['unidade_id'])) {
            // Se for string vazia, null ou "null", define como null
            if ($allData['unidade_id'] === '' || $allData['unidade_id'] === 'null' || $allData['unidade_id'] === null) {
                $data['unidade_id'] = null;
            } else {
                $data['unidade_id'] = (int)$allData['unidade_id'];
            }
        }

        // Permissões de menu (array de sections)
        if (isset($allData['permissoes_menu'])) {
            $pm = $allData['permissoes_menu'];
            if (is_array($pm)) {
                $data['permissoes_menu'] = json_encode(array_values(array_filter($pm)));
            } elseif (is_string($pm)) {
                $decoded = json_decode($pm, true);
                $data['permissoes_menu'] = is_array($decoded) ? json_encode($decoded) : null;
            } else {
                $data['permissoes_menu'] = null;
            }
        }

        // Atendente com caixa: usado na auditoria de fechamento (sugestão de operador)
        if (Schema::hasTable('usuarios') && Schema::hasColumn('usuarios', 'atende_caixa')) {
            if ($perfilNormalizado === 'ATENDENTE') {
                $ac = $allData['atende_caixa'] ?? 0;
                $data['atende_caixa'] = ($ac === true || $ac === 1 || $ac === '1') ? 1 : 0;
            } else {
                $data['atende_caixa'] = 0;
            }
        }

        // Processa foto se fornecida
        if ($request->hasFile('foto')) {
            $foto = $request->file('foto');
            $uploadDir = public_path('uploads/usuarios');
            // Cria o diretório se não existir
            if (!File::exists($uploadDir)) {
                File::makeDirectory($uploadDir, 0755, true);
            }
            $nomeArquivo = time() . '_' . $foto->getClientOriginalName();
            $foto->move($uploadDir, $nomeArquivo);
            $data['foto'] = 'uploads/usuarios/' . $nomeArquivo;
        }

        // Log dos dados que serão inseridos
        \Log::info('POST /usuarios - Dados para inserção:', $data);
        
        // Insere no banco de dados com tratamento de erro específico
        try {
            $id = DB::table('usuarios')->insertGetId($data);
        } catch (\Exception $e) {
            \Log::error('POST /usuarios - Erro ao inserir no banco:', [
                'error' => $e->getMessage(),
                'data' => $data,
                'perfil' => $perfilNormalizado
            ]);
            
            // Se o erro for relacionado ao perfil, tenta corrigir
            if (strpos($e->getMessage(), 'perfil') !== false || strpos($e->getMessage(), 'truncated') !== false) {
                // Verifica se a coluna perfil precisa ser alterada
                \Log::warning('POST /usuarios - Possível problema com coluna perfil no banco de dados');
                return response()->json([
                    'error' => 'Erro ao salvar perfil. A coluna perfil no banco de dados pode não aceitar o valor BAR. Verifique a estrutura da tabela.',
                    'details' => $e->getMessage()
                ], 500)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
            }
            throw $e;
        }
        $usuario = DB::table('usuarios')->where('id', $id)->first();
        
        if (!$usuario) {
            \Log::error('POST /usuarios - Erro: usuário não foi criado após insertGetId');
            return response()->json(['error' => 'Erro ao criar usuário'], 500);
        }
        
        \Log::info('POST /usuarios - Usuário criado com sucesso:', ['id' => $id]);
        unset($usuario->senha_hash);
        return response()->json($usuario, 201)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::error('POST /usuarios - Erro de validação:', $e->errors());
        return response()->json([
            'error' => 'Erro de validação',
            'message' => $e->getMessage(),
            'errors' => $e->errors()
        ], 422)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Exception $e) {
        \Log::error('Erro ao criar usuário: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json(['error' => 'Erro ao criar usuário: ' . $e->getMessage()], 500)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
});

// Troca de senha do próprio usuário logado (Minha conta)
Route::put('/usuarios/me/senha', function (Request $request) {
    $usuarioId = $request->header('X-Usuario-Id');
    if (!$usuarioId) {
        return response()->json(['error' => 'Usuário não identificado. Faça login novamente.'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    $usuario = DB::table('usuarios')->where('id', $usuarioId)->where('ativo', 1)->first();
    if (!$usuario) {
        return response()->json(['error' => 'Usuário não encontrado.'], 404)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    $request->validate([
        'senha_atual' => 'required|string',
        'nova_senha' => 'required|string|min:6|max:255',
        'confirmar_senha' => 'required|string|same:nova_senha',
    ], [
        'nova_senha.min' => 'A nova senha deve ter no mínimo 6 caracteres.',
        'confirmar_senha.same' => 'A confirmação da senha não confere.',
    ]);
    if (!password_verify($request->senha_atual, $usuario->senha_hash)) {
        return response()->json(['error' => 'Senha atual incorreta.'], 422)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    DB::table('usuarios')->where('id', $usuarioId)->update([
        'senha_hash' => Hash::make($request->nova_senha),
    ]);
    return response()->json(['success' => true, 'message' => 'Senha alterada com sucesso.'])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::options('/usuarios/me/senha', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::put('/usuarios/{id}', function (Request $request, $id) {
    try {
        // Log para debug
        \Log::info("PUT /usuarios/{$id} - Dados recebidos:", $request->all());
        
        // Verifica se o usuário existe
        $usuarioExistente = DB::table('usuarios')->where('id', $id)->first();
        if (!$usuarioExistente) {
            \Log::warning("PUT /usuarios/{$id} - Usuário não encontrado");
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }

        // Obtém todos os dados do request (funciona com FormData e JSON)
        $allData = $request->all();
        
        // Normaliza unidade_id antes da validação
        if (isset($allData['unidade_id'])) {
            if ($allData['unidade_id'] === '' || $allData['unidade_id'] === 'null' || $allData['unidade_id'] === null) {
                $allData['unidade_id'] = null;
                $request->merge(['unidade_id' => null]);
            } else {
                $allData['unidade_id'] = (int)$allData['unidade_id'];
                $request->merge(['unidade_id' => $allData['unidade_id']]);
            }
        }
        
        // Log detalhado para debug da senha
        \Log::info("PUT /usuarios/{$id} - Dados brutos recebidos:", [
            'has_senha_key' => $request->has('senha'),
            'senha_value' => $request->has('senha') ? (strlen($request->input('senha')) > 0 ? '***presente***' : 'vazia') : 'não presente',
            'all_keys' => array_keys($allData)
        ]);

        // Se for atualização parcial só do status (ativo), processa direto
        if ($request->has('ativo') && !$request->has('nome') && !$request->has('email') && !$request->has('perfil')) {
            DB::table('usuarios')->where('id', $id)->update(['ativo' => (int)$request->input('ativo')]);
            $usuarioAtualizado = DB::table('usuarios')->where('id', $id)->first();
            unset($usuarioAtualizado->senha_hash);
            \Log::info("PUT /usuarios/{$id} - Status atualizado:", ['ativo' => $request->input('ativo')]);
            return response()->json($usuarioAtualizado)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
        }

        // Validação dos campos obrigatórios
        $rules = [
            'nome' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'perfil' => 'required|string|max:50',
        ];
        
        // Adiciona validação de unidade_id apenas se foi enviado e não for null
        if (isset($allData['unidade_id']) && $allData['unidade_id'] !== null && $allData['unidade_id'] !== '') {
            $rules['unidade_id'] = 'nullable|integer|exists:unidades,id';
        } else {
            // Se não foi enviado ou é null, permite nullable sem validação de exists
            $rules['unidade_id'] = 'nullable';
        }
        
        $validated = $request->validate($rules);

        // Prepara os dados para atualização
        $perfilNormalizado = strtoupper(trim($validated['perfil']));
        
        \Log::info("PUT /usuarios/{$id} - Perfil normalizado:", [
            'perfil_original' => $validated['perfil'],
            'perfil_normalizado' => $perfilNormalizado,
            'is_bar' => $perfilNormalizado === 'BAR'
        ]);
        
        // Validação específica para perfil BAR
        $perfisValidos = ['ADMIN', 'GERENTE', 'ESTOQUISTA', 'COZINHA', 'BAR', 'FINANCEIRO', 'ASSISTENTE_ADMINISTRATIVO', 'VISUALIZADOR', 'ATENDENTE', 'ATENDENTE_CAIXA'];
        if (!in_array($perfilNormalizado, $perfisValidos)) {
            \Log::warning("PUT /usuarios/{$id} - Perfil inválido:", ['perfil' => $perfilNormalizado]);
            return response()->json(['error' => 'Perfil inválido: ' . $perfilNormalizado], 422)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
        }
        
        $data = [
            'nome' => trim($validated['nome']),
            'email' => trim($validated['email']),
            'perfil' => $perfilNormalizado,
        ];

        // Atualiza senha apenas se fornecida e não vazia
        $senhaRecebida = isset($allData['senha']) ? trim($allData['senha']) : '';
        \Log::info("PUT /usuarios/{$id} - Verificando senha:", [
            'senhaPresente' => isset($allData['senha']),
            'senhaNaoVazia' => !empty($senhaRecebida),
            'tamanhoSenha' => strlen($senhaRecebida)
        ]);
        
        if (!empty($senhaRecebida)) {
            if (strlen($senhaRecebida) >= 6) {
                $data['senha_hash'] = Hash::make($senhaRecebida);
                \Log::info("PUT /usuarios/{$id} - Senha será atualizada (tamanho: " . strlen($senhaRecebida) . ")");
            } else {
                \Log::warning("PUT /usuarios/{$id} - Senha muito curta ({$senhaRecebida}), ignorando");
                return response()->json(['error' => 'A senha deve ter no mínimo 6 caracteres'], 422)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
            }
        } else {
            \Log::info("PUT /usuarios/{$id} - Senha não fornecida, mantendo senha atual");
        }

        // Atualiza status ativo se fornecido
        if (isset($allData['ativo'])) {
            $data['ativo'] = $allData['ativo'] ? 1 : 0;
        }

        // Atualiza unidade_id se fornecido
        if (isset($allData['unidade_id'])) {
            // Se for string vazia, null ou "null", define como null
            if ($allData['unidade_id'] === '' || $allData['unidade_id'] === 'null' || $allData['unidade_id'] === null) {
                $data['unidade_id'] = null;
            } else {
                $data['unidade_id'] = (int)$allData['unidade_id'];
            }
        } else {
            // Se não foi enviado, mantém o valor atual (não atualiza)
        }

        // Permissões de menu (array de sections)
        if (array_key_exists('permissoes_menu', $allData)) {
            $pm = $allData['permissoes_menu'];
            if (is_array($pm)) {
                $data['permissoes_menu'] = json_encode(array_values(array_filter($pm)));
            } elseif (is_string($pm)) {
                $decoded = json_decode($pm, true);
                $data['permissoes_menu'] = is_array($decoded) ? json_encode($decoded) : null;
            } else {
                $data['permissoes_menu'] = null;
            }
        }

        if (Schema::hasTable('usuarios') && Schema::hasColumn('usuarios', 'atende_caixa')) {
            if ($perfilNormalizado === 'ATENDENTE') {
                if (array_key_exists('atende_caixa', $allData)) {
                    $ac = $allData['atende_caixa'];
                    $data['atende_caixa'] = ($ac === true || $ac === 1 || $ac === '1') ? 1 : 0;
                }
            } else {
                $data['atende_caixa'] = 0;
            }
        }

        // Processa foto se fornecida
        if ($request->hasFile('foto')) {
            // Remove foto antiga se existir
            if ($usuarioExistente->foto && file_exists(public_path($usuarioExistente->foto))) {
                unlink(public_path($usuarioExistente->foto));
            }
            $foto = $request->file('foto');
            $uploadDir = public_path('uploads/usuarios');
            // Cria o diretório se não existir
            if (!File::exists($uploadDir)) {
                File::makeDirectory($uploadDir, 0755, true);
            }
            $nomeArquivo = time() . '_' . $foto->getClientOriginalName();
            $foto->move($uploadDir, $nomeArquivo);
            $data['foto'] = 'uploads/usuarios/' . $nomeArquivo;
        }

        // Remove foto se solicitado
        if (isset($allData['remove_foto']) && $allData['remove_foto'] == '1') {
            if ($usuarioExistente->foto && file_exists(public_path($usuarioExistente->foto))) {
                unlink(public_path($usuarioExistente->foto));
            }
            $data['foto'] = null;
        }

        // Remove campos que não devem ser atualizados
        unset($data['confirmar_senha']);
        unset($data['id']);
        
        // Log dos dados que serão atualizados
        \Log::info("PUT /usuarios/{$id} - Dados para atualização:", $data);
        
        // Atualiza no banco de dados com tratamento de erro específico
        try {
            $updated = DB::table('usuarios')->where('id', $id)->update($data);
        } catch (\Exception $e) {
            \Log::error("PUT /usuarios/{$id} - Erro ao atualizar no banco:", [
                'error' => $e->getMessage(),
                'data' => $data,
                'perfil' => $perfilNormalizado
            ]);
            
            // Se o erro for relacionado ao perfil, tenta corrigir
            if (strpos($e->getMessage(), 'perfil') !== false || strpos($e->getMessage(), 'truncated') !== false) {
                // Verifica se a coluna perfil precisa ser alterada
                \Log::warning("PUT /usuarios/{$id} - Possível problema com coluna perfil no banco de dados");
                return response()->json([
                    'error' => 'Erro ao salvar perfil. A coluna perfil no banco de dados pode não aceitar o valor BAR. Execute a migration para corrigir: php artisan migrate',
                    'details' => $e->getMessage()
                ], 500)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
            }
            throw $e;
        }
        
        \Log::info("PUT /usuarios/{$id} - Linhas afetadas: {$updated}");
        
        // Busca o usuário atualizado
        $usuario = DB::table('usuarios')->where('id', $id)->first();
        if (!$usuario) {
            \Log::error("PUT /usuarios/{$id} - Erro: usuário não encontrado após update");
            return response()->json(['error' => 'Erro ao atualizar usuário'], 500);
        }
        
        \Log::info("PUT /usuarios/{$id} - Usuário atualizado com sucesso");
        unset($usuario->senha_hash);
        return response()->json($usuario)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::error("PUT /usuarios/{$id} - Erro de validação:", $e->errors());
        return response()->json([
            'error' => 'Erro de validação',
            'message' => $e->getMessage(),
            'errors' => $e->errors()
        ], 422)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Exception $e) {
        \Log::error('Erro ao atualizar usuário: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json(['error' => 'Erro ao atualizar usuário: ' . $e->getMessage()], 500)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
});

Route::delete('/usuarios/{id}', function (Request $request, $id) {
    try {
        // ============================================
        // 1) VERIFICAÇÃO DE AUTENTICAÇÃO
        // ============================================
        $usuarioId = $request->header('X-Usuario-Id');
        if (!$usuarioId) {
            return response()->json(['error' => 'Usuário não autenticado'], 401)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
        }
        
        $usuarioAutenticado = DB::table('usuarios')->where('id', $usuarioId)->first();
        if (!$usuarioAutenticado) {
            return response()->json(['error' => 'Usuário autenticado não encontrado'], 401)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
        }
        
        // ============================================
        // 2) REGRA 1: APENAS ADMIN PODE EXCLUIR
        // ============================================
        $perfil = strtoupper(trim($usuarioAutenticado->perfil ?? ''));
        if ($perfil !== 'ADMIN') {
            return response()->json(['error' => 'Apenas administradores podem excluir usuários'], 403)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
        }
        
        // ============================================
        // 3) BUSCA O USUÁRIO ALVO
        // ============================================
        $usuarioAlvo = DB::table('usuarios')->where('id', $id)->first();
        if (!$usuarioAlvo) {
            return response()->json(['error' => 'Usuário não encontrado'], 404)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
        }
        
        // ============================================
        // 4) REGRA 2: IMPEDIR EXCLUIR A SI MESMO
        // ============================================
        if ((int)$id === (int)$usuarioId) {
            return response()->json(['error' => 'Você não pode excluir a si mesmo'], 403)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
        }
        
        // ============================================
        // 5) REGRA 3: IMPEDIR EXCLUIR ADMIN RAIZ/SISTEMA
        // ============================================
        $perfilAlvo = strtoupper(trim($usuarioAlvo->perfil ?? ''));
        $idAlvo = (int)$id;
        
        // Bloqueia exclusão do usuário com ID = 1 (primeiro usuário/raiz)
        if ($idAlvo === 1) {
            return response()->json(['error' => 'Não é permitido excluir o usuário raiz do sistema (ID: 1)'], 403)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
        }
        
        // Bloqueia exclusão se for SUPERADMIN (se existir esse perfil)
        if ($perfilAlvo === 'SUPERADMIN') {
            return response()->json(['error' => 'Não é permitido excluir usuários com perfil SUPERADMIN'], 403)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
        }
        
        // Busca o menor ID com perfil ADMIN (considerado raiz)
        $adminRaiz = DB::table('usuarios')
            ->where('perfil', 'ADMIN')
            ->orderBy('id', 'asc')
            ->first();
        
        if ($adminRaiz && (int)$adminRaiz->id === $idAlvo) {
            return response()->json(['error' => 'Não é permitido excluir o administrador raiz do sistema'], 403)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
        }
        
        // ============================================
        // 6) EXECUTA EXCLUSÃO SEGURA EM TRANSACTION
        // ============================================
        DB::beginTransaction();
        
        try {
            // 6.1) Verifica se existem movimentações
            $qtdMovimentacoes = DB::table('movimentacoes')
                ->where('usuario_id', $idAlvo)
                ->count();
            
            $qtdMovimentacoesTransferidas = 0;
            
            // 6.2) Se existir movimentações, transfere para o ADMIN logado
            if ($qtdMovimentacoes > 0) {
                $qtdMovimentacoesTransferidas = DB::table('movimentacoes')
                    ->where('usuario_id', $idAlvo)
                    ->update(['usuario_id' => $usuarioId]);
                
                \Log::info("Exclusão de usuário: {$qtdMovimentacoesTransferidas} movimentações transferidas do usuário {$idAlvo} para o ADMIN {$usuarioId}");
            }
            
            // 6.3) Salva informações do usuário ANTES de deletar (para o log)
            $nomeUsuarioAlvo = $usuarioAlvo->nome ?? 'N/A';
            $emailUsuarioAlvo = $usuarioAlvo->email ?? 'N/A';
            $nomeAdmin = $usuarioAutenticado->nome ?? 'N/A';
            
            // 6.4) Registra auditoria (log) ANTES de deletar o usuário
            if (Schema::hasTable('logs_usuarios')) {
                DB::table('logs_usuarios')->insert([
                    'ator_id' => $usuarioId,
                    'alvo_id' => $idAlvo,
                    'acao' => 'DELETE',
                    'qtd_movimentacoes_transferidas' => $qtdMovimentacoesTransferidas,
                    'observacoes' => "Usuário {$nomeUsuarioAlvo} ({$emailUsuarioAlvo}) DELETADO permanentemente do sistema pelo ADMIN {$nomeAdmin}",
                    'created_at' => now(),
                ]);
            }
            
            // 6.5) DELETA o usuário do banco de dados (após transferir movimentações e registrar log)
            // As movimentações já foram transferidas acima, então não há mais foreign key constraint
            DB::table('usuarios')->where('id', $idAlvo)->delete();
            
            $acao = 'DELETE';
            $mensagem = "Usuário removido permanentemente do sistema e do banco de dados";
            
            if ($qtdMovimentacoesTransferidas > 0) {
                $mensagem .= ". {$qtdMovimentacoesTransferidas} movimentação(ões) transferida(s) para seu usuário ADMIN.";
            }
            
            DB::commit();
            
            \Log::info("Usuário {$idAlvo} {$acao} com sucesso pelo ADMIN {$usuarioId}. Movimentações transferidas: {$qtdMovimentacoesTransferidas}");
            
            return response()->json([
                'message' => $mensagem,
                'acao' => $acao,
                'qtd_movimentacoes_transferidas' => $qtdMovimentacoesTransferidas,
            ])
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
                
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erro na transação de exclusão de usuário: ' . $e->getMessage());
            throw $e;
        }
        
    } catch (\Exception $e) {
        \Log::error('Erro ao remover usuário: ' . $e->getMessage());
        return response()->json([
            'error' => 'Erro ao remover usuário: ' . $e->getMessage()
        ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
});

// ============================================
// LOCAIS
// ============================================

// CORS preflight para rotas de locais
Route::options('/locais', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::options('/locais/{id}', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::get('/locais', function () {
    $locais = DB::table('locais')
        ->leftJoin('unidades', 'locais.unidade_id', '=', 'unidades.id')
        ->select('locais.*', 'unidades.nome as unidade_nome')
        ->orderBy('locais.nome')
        ->get();
    return response()->json($locais);
});

Route::get('/locais/{id}', function ($id) {
    $local = DB::table('locais')->where('id', $id)->first();
    if (!$local) {
        return response()->json(['error' => 'Local não encontrado'], 404);
    }
    return response()->json($local);
});

Route::post('/locais', function (Request $request) {
    // Verifica se o usuário está autenticado
    $usuarioId = $request->header('X-Usuario-Id');
    if (!$usuarioId) {
        return response()->json(['error' => 'Usuário não autenticado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Busca o usuário que está fazendo a requisição
    $usuarioAutenticado = DB::table('usuarios')->where('id', $usuarioId)->first();
    if (!$usuarioAutenticado) {
        return response()->json(['error' => 'Usuário autenticado não encontrado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Verifica se é ADMIN
    $perfil = strtoupper(trim($usuarioAutenticado->perfil ?? ''));
    if ($perfil !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem criar locais'], 403)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    $data = $request->only(['nome', 'tipo', 'unidade_id', 'ativo', 'temperatura_media', 'descricao', 'nivel_acesso', 'data_cadastro', 'observacoes', 'responsavel']);
    $data['nome'] = trim($data['nome'] ?? '');
    $data['tipo'] = strtoupper(trim($data['tipo'] ?? ''));
    $data['unidade_id'] = (int) ($data['unidade_id'] ?? 0);
    $data['ativo'] = isset($data['ativo']) ? (int) $data['ativo'] : 1;
    $data['temperatura_media'] = isset($data['temperatura_media']) && $data['temperatura_media'] !== '' ? (float) $data['temperatura_media'] : null;
    $data['descricao'] = isset($data['descricao']) && $data['descricao'] !== '' ? trim($data['descricao']) : null;
    $data['nivel_acesso'] = isset($data['nivel_acesso']) && $data['nivel_acesso'] !== '' ? trim($data['nivel_acesso']) : null;
    $data['data_cadastro'] = isset($data['data_cadastro']) && $data['data_cadastro'] !== '' ? $data['data_cadastro'] : null;
    $data['observacoes'] = isset($data['observacoes']) && $data['observacoes'] !== '' ? trim($data['observacoes']) : null;
    $data['responsavel'] = isset($data['responsavel']) && $data['responsavel'] !== '' ? trim($data['responsavel']) : null;
    if (!$data['nome'] || !$data['tipo'] || !$data['unidade_id']) {
        return response()->json(['error' => 'Nome, tipo e unidade são obrigatórios'], 422)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    $id = DB::table('locais')->insertGetId($data);
    return response()->json(DB::table('locais')->leftJoin('unidades', 'locais.unidade_id', '=', 'unidades.id')->select('locais.*', 'unidades.nome as unidade_nome')->where('locais.id', $id)->first(), 201)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::put('/locais/{id}', function (Request $request, $id) {
    // Verifica se o usuário está autenticado
    $usuarioId = $request->header('X-Usuario-Id');
    if (!$usuarioId) {
        return response()->json(['error' => 'Usuário não autenticado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Busca o usuário que está fazendo a requisição
    $usuarioAutenticado = DB::table('usuarios')->where('id', $usuarioId)->first();
    if (!$usuarioAutenticado) {
        return response()->json(['error' => 'Usuário autenticado não encontrado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Verifica se é ADMIN
    $perfil = strtoupper(trim($usuarioAutenticado->perfil ?? ''));
    if ($perfil !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem editar locais'], 403)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    $data = $request->only(['nome', 'tipo', 'unidade_id', 'ativo', 'temperatura_media', 'descricao', 'nivel_acesso', 'data_cadastro', 'observacoes', 'responsavel']);
    if (isset($data['nome'])) $data['nome'] = trim($data['nome']);
    if (isset($data['tipo'])) $data['tipo'] = strtoupper(trim($data['tipo']));
    if (isset($data['unidade_id'])) $data['unidade_id'] = (int) $data['unidade_id'];
    if (isset($data['ativo'])) $data['ativo'] = (int) $data['ativo'];
    $data['temperatura_media'] = isset($data['temperatura_media']) && $data['temperatura_media'] !== '' ? (float) $data['temperatura_media'] : null;
    $data['descricao'] = isset($data['descricao']) && $data['descricao'] !== '' ? trim($data['descricao']) : null;
    $data['nivel_acesso'] = isset($data['nivel_acesso']) && $data['nivel_acesso'] !== '' ? trim($data['nivel_acesso']) : null;
    $data['data_cadastro'] = isset($data['data_cadastro']) && $data['data_cadastro'] !== '' ? $data['data_cadastro'] : null;
    $data['observacoes'] = isset($data['observacoes']) && $data['observacoes'] !== '' ? trim($data['observacoes']) : null;
    $data['responsavel'] = isset($data['responsavel']) && $data['responsavel'] !== '' ? trim($data['responsavel']) : null;
    DB::table('locais')->where('id', $id)->update($data);
    return response()->json(DB::table('locais')->leftJoin('unidades', 'locais.unidade_id', '=', 'unidades.id')->select('locais.*', 'unidades.nome as unidade_nome')->where('locais.id', $id)->first())
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::patch('/locais/{id}/status', function (Request $request, $id) {
    // Verifica se o usuário está autenticado
    $usuarioId = $request->header('X-Usuario-Id');
    if (!$usuarioId) {
        return response()->json(['error' => 'Usuário não autenticado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PATCH, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Busca o usuário que está fazendo a requisição
    $usuarioAutenticado = DB::table('usuarios')->where('id', $usuarioId)->first();
    if (!$usuarioAutenticado) {
        return response()->json(['error' => 'Usuário autenticado não encontrado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PATCH, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Verifica se é ADMIN
    $perfil = strtoupper(trim($usuarioAutenticado->perfil ?? ''));
    if ($perfil !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem alterar o status de locais'], 403)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PATCH, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    $data = $request->validate(['ativo' => 'required|boolean']);
    DB::table('locais')->where('id', $id)->update(['ativo' => $data['ativo']]);
    return response()->json(DB::table('locais')->where('id', $id)->first())
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'PATCH, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::delete('/locais/{id}', function (Request $request, $id) {
    // Verifica se o usuário está autenticado
    $usuarioId = $request->header('X-Usuario-Id');
    if (!$usuarioId) {
        return response()->json(['error' => 'Usuário não autenticado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Busca o usuário que está fazendo a requisição
    $usuarioAutenticado = DB::table('usuarios')->where('id', $usuarioId)->first();
    if (!$usuarioAutenticado) {
        return response()->json(['error' => 'Usuário autenticado não encontrado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    // Verifica se é ADMIN
    $perfil = strtoupper(trim($usuarioAutenticado->perfil ?? ''));
    if ($perfil !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem excluir locais'], 403)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
    
    DB::table('locais')->where('id', $id)->delete();
    return response()->json(['message' => 'Local removido'])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// ============================================
// LOTES
// ============================================

Route::get('/lotes', function (Request $request) {
    $query = DB::table('lotes')
        ->leftJoin('produtos', 'lotes.produto_id', '=', 'produtos.id')
        ->leftJoin('unidades', 'lotes.unidade_id', '=', 'unidades.id')
        ->leftJoin('locais', 'lotes.local_id', '=', 'locais.id')
        ->select(
            'lotes.*', 
            'produtos.nome as produto_nome', 
            'unidades.nome as unidade_nome',
            'locais.nome as local_nome'
        );
    
    if ($request->has('produto_id') && $request->produto_id) {
        $query->where('lotes.produto_id', $request->produto_id);
    }
    
    if ($request->has('unidade_id') && $request->unidade_id) {
        $query->where('lotes.unidade_id', $request->unidade_id);
    }
    
    if ($request->has('status') && $request->status) {
        $query->where('lotes.ativo', $request->status == 'ATIVO' ? 1 : 0);
    }
    
    if ($request->has('validade_de') && $request->validade_de) {
        $query->where('lotes.data_validade', '>=', $request->validade_de);
    }
    
    if ($request->has('validade_ate') && $request->validade_ate) {
        $query->where('lotes.data_validade', '<=', $request->validade_ate);
    }
    
    // Filtro de pesquisa (busca por código do lote ou nome do produto)
    if ($request->has('pesquisa') && $request->pesquisa) {
        $pesquisa = '%' . $request->pesquisa . '%';
        $query->where(function($q) use ($pesquisa) {
            $q->where('lotes.numero_lote', 'LIKE', $pesquisa)
              ->orWhere('produtos.nome', 'LIKE', $pesquisa);
        });
    }
    
    $lotes = $query->orderBy('lotes.data_validade')->get();
    
    // Adiciona campos compatíveis com o frontend
    $lotes = $lotes->map(function($lote) {
        $lote->codigo_lote = $lote->numero_lote ?? null;
        $lote->quantidade = $lote->qtd_atual ?? 0;
        
        // Calcula dias para vencer
        if ($lote->data_validade) {
            $dataValidade = \Carbon\Carbon::parse($lote->data_validade);
            $hoje = \Carbon\Carbon::now()->startOfDay();
            $diasParaVencer = $hoje->diffInDays($dataValidade, false);
            $lote->dias_para_vencer = $diasParaVencer;
        } else {
            $lote->dias_para_vencer = null;
        }
        
        // Calcula status baseado em ativo e validade
        if (isset($lote->ativo) && $lote->ativo == 0) {
            $lote->status = 'INATIVO';
        } elseif ($lote->qtd_atual <= 0) {
            $lote->status = 'ESGOTADO';
        } elseif ($lote->data_validade && $lote->data_validade < now()->format('Y-m-d')) {
            $lote->status = 'VENCIDO';
        } else {
            $lote->status = 'ATIVO';
        }
        return $lote;
    });
    
    return response()->json($lotes);
});

Route::get('/lotes/stats', function () {
    // Calcula lotes a vencer (15 dias) - baseado em data_validade e ativo
    $dataLimite15 = now()->addDays(15)->format('Y-m-d');
    $hoje = now()->format('Y-m-d');
    
    $lotesAVencer15 = DB::table('lotes')
        ->where('data_validade', '<=', $dataLimite15)
        ->where('data_validade', '>=', $hoje)
        ->where('ativo', 1)
        ->where('qtd_atual', '>', 0)
        ->count();
    
    // Calcula lotes vencidos - baseado em data_validade
    $lotesVencidos = DB::table('lotes')
        ->where('data_validade', '<', $hoje)
        ->where('ativo', 1)
        ->where('qtd_atual', '>', 0)
        ->count();
    
    // Calcula estatísticas por status dinâmico
    $totalLotes = DB::table('lotes')->count();
    $lotesAtivos = DB::table('lotes')
        ->where('ativo', 1)
        ->where('qtd_atual', '>', 0)
        ->where(function($query) use ($hoje) {
            $query->whereNull('data_validade')
                  ->orWhere('data_validade', '>=', $hoje);
        })
        ->count();
    
    $lotesInativos = DB::table('lotes')
        ->where('ativo', 0)
        ->count();
    
    $lotesEsgotados = DB::table('lotes')
        ->where('qtd_atual', '<=', 0)
        ->where('ativo', 1)
        ->count();
    
    return response()->json([
        'status' => [
            'ATIVO' => $lotesAtivos,
            'INATIVO' => $lotesInativos,
            'ESGOTADO' => $lotesEsgotados,
            'VENCIDO' => $lotesVencidos
        ],
        'a_vencer' => $lotesAVencer15,
        'vencidos' => $lotesVencidos,
        'total' => $totalLotes
    ]);
});

Route::get('/lotes/{id}', function ($id) {
    // Busca lote com join nas tabelas produtos e unidades para trazer os nomes
    $lote = DB::table('lotes')
        ->leftJoin('produtos', 'lotes.produto_id', '=', 'produtos.id')
        ->leftJoin('unidades', 'lotes.unidade_id', '=', 'unidades.id')
        ->leftJoin('locais', 'lotes.local_id', '=', 'locais.id')
        ->select(
            'lotes.*',
            'produtos.nome as produto_nome',
            'unidades.nome as unidade_nome',
            'locais.nome as local_nome'
        )
        ->where('lotes.id', $id)
        ->first();
    
    if (!$lote) {
        return response()->json(['error' => 'Lote não encontrado'], 404);
    }
    
    // Adiciona campos compatíveis com o frontend
    $lote->codigo_lote = $lote->numero_lote ?? null;
    $lote->quantidade = $lote->qtd_atual ?? 0;
    
    return response()->json($lote);
});

Route::get('/lotes/{id}/etiqueta.pdf', function (Request $request, $id) {
    try {
        // Validação de permissão: apenas ADMIN e GERENTE podem imprimir etiquetas
        $usuarioId = $request->header('X-Usuario-Id');
        if (!$usuarioId) {
            return response()->json(['error' => 'Usuário não autenticado'], 401)
                ->header('Access-Control-Allow-Origin', '*');
        }
        
        $usuario = DB::table('usuarios')->where('id', $usuarioId)->first();
        if (!$usuario) {
            return response()->json(['error' => 'Usuário não encontrado'], 404)
                ->header('Access-Control-Allow-Origin', '*');
        }
        
        $perfil = strtoupper(trim($usuario->perfil ?? ''));
        // ADMIN, GERENTE e ESTOQUISTA podem imprimir etiquetas
        if ($perfil !== 'ADMIN' && $perfil !== 'GERENTE' && $perfil !== 'ESTOQUISTA') {
            return response()->json(['error' => 'Sem permissão para imprimir etiquetas'], 403)
                ->header('Access-Control-Allow-Origin', '*');
        }
        
        // Busca o lote com informações do produto
        $lote = DB::table('lotes')
            ->leftJoin('produtos', 'lotes.produto_id', '=', 'produtos.id')
            ->leftJoin('unidades', 'lotes.unidade_id', '=', 'unidades.id')
            ->select(
                'lotes.*',
                'produtos.nome as produto_nome',
                'unidades.nome as unidade_nome'
            )
            ->where('lotes.id', $id)
            ->first();
        
        if (!$lote) {
            return response()->json(['error' => 'Lote não encontrado'], 404)
                ->header('Access-Control-Allow-Origin', '*');
        }
        
        // Gera URL do QR Code (aponta para detalhe do lote)
        // Usa a URL base da aplicação - pode ser configurada via env ou usar a URL da requisição
        $appUrl = env('APP_URL', $request->getSchemeAndHttpHost() . $request->getBasePath());
        // Remove barra final se existir
        $appUrl = rtrim($appUrl, '/');
        // QR Code aponta para dashboard > Registro de Saída com lote pré-preenchido
        $qrUrl = $appUrl . '/#dashboard?saida=1&lote=' . $id;
        
        // Gera QR Code usando endroid/qr-code
        try {
            $qrCode = \Endroid\QrCode\Builder\Builder::create()
                ->writer(new \Endroid\QrCode\Writer\PngWriter())
                ->data($qrUrl)
                ->size(150)
                ->margin(10)
                ->build();
            
            $qrCodeDataUri = 'data:image/png;base64,' . base64_encode($qrCode->getString());
        } catch (\Exception $qrError) {
            \Log::error('Erro ao gerar QR Code: ' . $qrError->getMessage());
            // Se falhar, usa uma imagem placeholder ou continua sem QR Code
            $qrCodeDataUri = 'data:image/svg+xml;base64,' . base64_encode('<svg width="150" height="150" xmlns="http://www.w3.org/2000/svg"><rect width="150" height="150" fill="#f0f0f0"/><text x="75" y="75" text-anchor="middle" font-family="Arial" font-size="12" fill="#666">QR Code</text></svg>');
        }
        
        // Prepara dados para a etiqueta
        $numeroLote = $lote->numero_lote ?? $lote->codigo_lote ?? 'N/A';
        $produtoNome = $lote->produto_nome ?? 'Produto';
        $dataValidade = $lote->data_validade ? date('d/m/Y', strtotime($lote->data_validade)) : null;
        $dataGeracao = null;
        $criadoEm = $lote->criado_em ?? $lote->created_at ?? null;
        if ($criadoEm) {
            $dataGeracao = date('d/m/Y', strtotime($criadoEm));
        }

        $copies = (int) $request->query('copies', 1);
        if ($copies < 1) $copies = 1;
        if ($copies > 200) $copies = 200;

        // Layout fixo em A4 com 3 colunas (tamanho: 50mm x 30mm)
        $cols = 3;
        $rows = 9;
        $perPage = $cols * $rows; // 27 etiquetas por página
        $pageCount = (int) ceil($copies / $perPage);
        
        $labelInner = '
                <div class="etiqueta-info">
                    <div class="produto-nome">' . htmlspecialchars($produtoNome) . '</div>
                    <div class="numero-lote">LOTE: ' . htmlspecialchars($numeroLote) . '</div>
                    ' . ($dataGeracao ? '<div class="data-geracao">GER: ' . htmlspecialchars($dataGeracao) . '</div>' : '') . '
                    ' . ($dataValidade ? '<div class="validade">VAL: ' . htmlspecialchars($dataValidade) . '</div>' : '') . '
                </div>
                <img src="' . $qrCodeDataUri . '" class="qr-code" alt="QR Code" />
        ';

        $labelBlock = '<div class="etiqueta">' . $labelInner . '</div>';

        $pagesHtml = '';
        for ($p = 0; $p < $pageCount; $p++) {
            $start = $p * $perPage;
            $end = min($copies, $start + $perPage);
            $qtyOnPage = $end - $start;
            $pageClass = ($p === $pageCount - 1) ? 'page last-page' : 'page';

            $trs = '';
            for ($r = 0; $r < $rows; $r++) {
                $tds = '';
                for ($c = 0; $c < $cols; $c++) {
                    $idxInPage = $r * $cols + $c;
                    if ($idxInPage < $qtyOnPage) {
                        $tds .= '<td>' . $labelBlock . '</td>';
                    } else {
                        $tds .= '<td></td>';
                    }
                }
                $trs .= '<tr>' . $tds . '</tr>';
            }

            $pagesHtml .= '<div class="' . $pageClass . '"><table class="pageTable"><tbody>' . $trs . '</tbody></table></div>';
        }

        // Gera HTML das páginas no A4 (grid com várias etiquetas)
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page {
                    size: A4 portrait;
                    margin: 0mm;
                }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 8pt;
                    margin: 0;
                    padding: 0;
                }

                .page {
                    width: 210mm;
                    height: 297mm;
                    page-break-after: always;
                }
                .page.last-page {
                    page-break-after: avoid;
                }

                .pageTable {
                    border-collapse: collapse;
                    border-spacing: 0;
                    table-layout: fixed;
                    width: 210mm;
                }

                .pageTable td {
                    width: 50mm;
                    height: 30mm;
                    padding: 0;
                    margin: 0;
                    vertical-align: top;
                    overflow: hidden;
                }

                .etiqueta {
                    box-sizing: border-box;
                    width: 100%;
                    height: 100%;
                    padding: 2mm;
                    display: flex;
                    flex-direction: row;
                    align-items: center;
                    justify-content: space-between;
                }

                .etiqueta-info {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    gap: 2px;
                }
                .produto-nome {
                    font-size: 7pt;
                    color: #666;
                    margin-bottom: 2px;
                }
                .numero-lote {
                    font-size: 12pt;
                    font-weight: bold;
                    color: #000;
                }
                .data-geracao {
                    font-size: 8pt;
                    color: #1976d2;
                    margin-top: 1px;
                }
                .validade {
                    font-size: 8pt;
                    color: #333;
                    margin-top: 2px;
                }
                .qr-code {
                    width: 22mm;
                    height: 22mm;
                    margin-left: 3mm;
                    flex-shrink: 0;
                }
            </style>
        </head>
        <body>' . $pagesHtml . '</body>
        </html>';
        
        // Gera PDF usando dompdf
        try {
            $dompdf = new \Dompdf\Dompdf();
            
            // Configurações do dompdf
            $options = $dompdf->getOptions();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $dompdf->setOptions($options);
            
            $dompdf->loadHtml($html);
            $dompdf->setPaper('a4', 'portrait');
            $dompdf->render();
            
            \Log::info('PDF gerado com sucesso para lote ID: ' . $id);
        } catch (\Exception $pdfError) {
            \Log::error('Erro ao gerar PDF: ' . $pdfError->getMessage());
            \Log::error('Stack trace PDF: ' . $pdfError->getTraceAsString());
            throw $pdfError;
        }
        
        // Registra log de impressão
        try {
            DB::table('logs_etiquetas')->insert([
                'lote_id' => $id,
                'usuario_id' => $usuarioId,
                'acao' => 'imprimir_etiqueta',
                'data_hora' => now(),
            ]);
        } catch (\Exception $e) {
            // Se a tabela não existir, apenas loga o erro mas não falha
            \Log::warning('Erro ao registrar log de etiqueta: ' . $e->getMessage());
        }
        
        // Retorna PDF
        $pdfOutput = $dompdf->output();
        \Log::info('PDF gerado, tamanho: ' . strlen($pdfOutput) . ' bytes para lote ID: ' . $id);
        
        return response($pdfOutput, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="etiqueta-lote-' . $numeroLote . '.pdf"')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id')
            ->header('Content-Length', strlen($pdfOutput));
            
    } catch (\Exception $e) {
        \Log::error('Erro ao gerar etiqueta: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json(['error' => 'Erro ao gerar etiqueta: ' . $e->getMessage()], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
});

// Rota OPTIONS para CORS preflight
Route::options('/lotes/{id}/etiqueta.pdf', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::post('/lotes', function (Request $request) {
    try {
        $data = $request->validate([
            'produto_id' => 'required|integer|exists:produtos,id',
            'unidade_id' => 'required|integer|exists:unidades,id',
            'codigo_lote' => 'required|string|max:255',
            'quantidade' => 'required|numeric|min:0.001',
            'custo_unitario' => 'required|numeric|min:0',
            'data_fabricacao' => 'nullable|date',
            'data_validade' => 'nullable|date',
            'local_id' => 'nullable|integer|exists:locais,id',
        ]);
        
        // Busca o produto para pegar unidade_base
        $produto = DB::table('produtos')->where('id', $data['produto_id'])->first();
        if (!$produto) {
            return response()->json(['error' => 'Produto não encontrado'], 404);
        }
        
        // Mapeia unidade_base para o enum da tabela
        $unidadeBase = strtoupper(trim($produto->unidade_base ?? 'UND'));
        $unidadesValidas = ['UND', 'G', 'KG', 'ML', 'L', 'PCT', 'CX'];
        if (!in_array($unidadeBase, $unidadesValidas)) {
            $unidadeBase = 'UND'; // Padrão se não for válido
        }
        
        // Busca ou cria um local padrão se não informado
        $localId = $data['local_id'] ?? null;
        if (!$localId) {
            $localPadrao = DB::table('locais')->where('unidade_id', $data['unidade_id'])->first();
            if ($localPadrao) {
                $localId = $localPadrao->id;
            } else {
                // Cria um local padrão se não existir nenhum
                $localId = DB::table('locais')->insertGetId([
                    'nome' => 'Depósito Principal',
                    'unidade_id' => $data['unidade_id'],
                    'tipo' => 'DEPOSITO',
                    'ativo' => 1,
                ]);
            }
        }
        
        // Prepara dados para inserção na tabela lotes
        $insertData = [
            'produto_id' => $data['produto_id'],
            'unidade_id' => $data['unidade_id'],
            'numero_lote' => trim($data['codigo_lote']),
            'qtd_atual' => floatval($data['quantidade']),
            'unidade' => $unidadeBase,
            'custo_unitario' => floatval($data['custo_unitario']),
            'data_fabricacao' => $data['data_fabricacao'] ?? null,
            'data_validade' => $data['data_validade'] ?? null,
            'local_id' => $localId,
            'ativo' => 1, // Sempre cria como ativo
            'criado_em' => now(),
        ];
        
        $id = DB::table('lotes')->insertGetId($insertData);
        
        // Cria registro em stock_lotes para controle de estoque
        DB::table('stock_lotes')->insert([
            'produto_id' => $data['produto_id'],
            'unidade_id' => $data['unidade_id'],
            'codigo_lote' => trim($data['codigo_lote']),
            'quantidade' => floatval($data['quantidade']),
            'custo_unitario' => floatval($data['custo_unitario']),
            'data_fabricacao' => $data['data_fabricacao'] ?? null,
            'data_validade' => $data['data_validade'] ?? null,
        ]);
        
        // Retorna o lote criado com joins
        $lote = DB::table('lotes')
            ->leftJoin('produtos', 'lotes.produto_id', '=', 'produtos.id')
            ->leftJoin('unidades', 'lotes.unidade_id', '=', 'unidades.id')
            ->leftJoin('locais', 'lotes.local_id', '=', 'locais.id')
            ->select(
                'lotes.*',
                'produtos.nome as produto_nome',
                'unidades.nome as unidade_nome',
                'locais.nome as local_nome'
            )
            ->where('lotes.id', $id)
            ->first();
        
        // Adiciona campos compatíveis com o frontend
        $lote->codigo_lote = $lote->numero_lote;
        $lote->quantidade = $lote->qtd_atual;
        
        return response()->json($lote, 201);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'error' => 'Erro de validação',
            'messages' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Erro ao cadastrar lote: ' . $e->getMessage()
        ], 500);
    }
});

Route::put('/lotes/{id}', function (Request $request, $id) {
    try {
        $data = $request->validate([
            'produto_id' => 'sometimes|required|integer|exists:produtos,id',
            'unidade_id' => 'sometimes|required|integer|exists:unidades,id',
            'codigo_lote' => 'sometimes|required|string|max:255',
            'quantidade' => 'sometimes|required|numeric|min:0.001',
            'custo_unitario' => 'sometimes|required|numeric|min:0',
            'data_fabricacao' => 'nullable|date',
            'data_validade' => 'nullable|date',
            'local_id' => 'nullable|integer|exists:locais,id',
            'ativo' => 'sometimes|integer|in:0,1',
        ]);
        
        // Verifica se o lote existe
        $loteExiste = DB::table('lotes')->where('id', $id)->first();
        if (!$loteExiste) {
            return response()->json(['error' => 'Lote não encontrado'], 404);
        }
        
        // Prepara dados para atualização mapeando campos do frontend para a tabela
        $updateData = [];
        
        if (isset($data['produto_id'])) {
            $updateData['produto_id'] = $data['produto_id'];
            // Se mudou o produto, precisa atualizar a unidade também
            $produto = DB::table('produtos')->where('id', $data['produto_id'])->first();
            if ($produto) {
                $unidadeBase = strtoupper(trim($produto->unidade_base ?? 'UND'));
                $unidadesValidas = ['UND', 'G', 'KG', 'ML', 'L', 'PCT', 'CX'];
                if (in_array($unidadeBase, $unidadesValidas)) {
                    $updateData['unidade'] = $unidadeBase;
                }
            }
        }
        
        if (isset($data['unidade_id'])) {
            $updateData['unidade_id'] = $data['unidade_id'];
        }
        
        if (isset($data['codigo_lote'])) {
            $updateData['numero_lote'] = trim($data['codigo_lote']);
        }
        
        if (isset($data['quantidade'])) {
            $updateData['qtd_atual'] = floatval($data['quantidade']);
        }
        
        if (isset($data['custo_unitario'])) {
            $updateData['custo_unitario'] = floatval($data['custo_unitario']);
        }
        
        if (isset($data['data_fabricacao'])) {
            $updateData['data_fabricacao'] = $data['data_fabricacao'];
        }
        
        if (isset($data['data_validade'])) {
            $updateData['data_validade'] = $data['data_validade'];
        }
        
        if (isset($data['local_id'])) {
            $updateData['local_id'] = $data['local_id'];
        }
        
        // Suporta ativo (para ativar/desativar)
        if (isset($data['ativo'])) {
            $updateData['ativo'] = $data['ativo'] == 1 || $data['ativo'] === '1' || $data['ativo'] === true ? 1 : 0;
        }
        
        if (!empty($updateData)) {
            DB::table('lotes')->where('id', $id)->update($updateData);
        }
        
        // Prepara dados para atualização em stock_lotes
        $stockUpdateData = [];
        
        if (isset($data['produto_id'])) {
            $stockUpdateData['produto_id'] = $data['produto_id'];
        }
        
        if (isset($data['unidade_id'])) {
            $stockUpdateData['unidade_id'] = $data['unidade_id'];
        }
        
        if (isset($data['codigo_lote'])) {
            $stockUpdateData['codigo_lote'] = trim($data['codigo_lote']);
        }
        
        if (isset($data['quantidade'])) {
            $stockUpdateData['quantidade'] = floatval($data['quantidade']);
        }
        
        if (isset($data['custo_unitario'])) {
            $stockUpdateData['custo_unitario'] = floatval($data['custo_unitario']);
        }
        
        if (isset($data['data_fabricacao'])) {
            $stockUpdateData['data_fabricacao'] = $data['data_fabricacao'];
        }
        
        if (isset($data['data_validade'])) {
            $stockUpdateData['data_validade'] = $data['data_validade'];
        }
        
        // Atualiza stock_lotes se houver campos para atualizar
        if (!empty($stockUpdateData)) {
            // Busca o código do lote atual para localizar o registro em stock_lotes
            $codigoLoteAtual = isset($data['codigo_lote']) ? trim($data['codigo_lote']) : $loteExiste->numero_lote;
            
            // Atualiza ou cria registro em stock_lotes
            $stockExiste = DB::table('stock_lotes')
                ->where('codigo_lote', $codigoLoteAtual)
                ->where('produto_id', $loteExiste->produto_id)
                ->where('unidade_id', $loteExiste->unidade_id)
                ->first();
            
            if ($stockExiste) {
                DB::table('stock_lotes')
                    ->where('id', $stockExiste->id)
                    ->update($stockUpdateData);
            } else {
                // Se não existe, cria o registro em stock_lotes
                DB::table('stock_lotes')->insert([
                    'produto_id' => $loteExiste->produto_id,
                    'unidade_id' => $loteExiste->unidade_id,
                    'codigo_lote' => $codigoLoteAtual,
                    'quantidade' => $loteExiste->qtd_atual,
                    'custo_unitario' => $loteExiste->custo_unitario,
                    'data_fabricacao' => $loteExiste->data_fabricacao,
                    'data_validade' => $loteExiste->data_validade,
                ]);
            }
        }
        
        // Retorna o lote atualizado com joins
        $lote = DB::table('lotes')
            ->leftJoin('produtos', 'lotes.produto_id', '=', 'produtos.id')
            ->leftJoin('unidades', 'lotes.unidade_id', '=', 'unidades.id')
            ->leftJoin('locais', 'lotes.local_id', '=', 'locais.id')
            ->select(
                'lotes.*',
                'produtos.nome as produto_nome',
                'unidades.nome as unidade_nome',
                'locais.nome as local_nome'
            )
            ->where('lotes.id', $id)
            ->first();
        
        // Adiciona campos compatíveis com o frontend
        $lote->codigo_lote = $lote->numero_lote ?? null;
        $lote->quantidade = $lote->qtd_atual ?? 0;
        
        // Calcula dias para vencer
        if ($lote->data_validade) {
            $dataValidade = \Carbon\Carbon::parse($lote->data_validade);
            $hoje = \Carbon\Carbon::now()->startOfDay();
            $diasParaVencer = $hoje->diffInDays($dataValidade, false);
            $lote->dias_para_vencer = $diasParaVencer;
        } else {
            $lote->dias_para_vencer = null;
        }
        
        // Calcula status baseado em ativo e validade
        if (isset($lote->ativo) && $lote->ativo == 0) {
            $lote->status = 'INATIVO';
        } elseif ($lote->qtd_atual <= 0) {
            $lote->status = 'ESGOTADO';
        } elseif ($lote->data_validade && $lote->data_validade < now()->format('Y-m-d')) {
            $lote->status = 'VENCIDO';
        } else {
            $lote->status = 'ATIVO';
        }
        
        return response()->json($lote);
    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::error('Erro de validação ao atualizar lote: ' . json_encode($e->errors()));
        return response()->json([
            'error' => 'Erro de validação',
            'messages' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        \Log::error('Erro ao atualizar lote: ' . $e->getMessage());
        return response()->json([
            'error' => 'Erro ao atualizar lote: ' . $e->getMessage()
        ], 500);
    }
});

Route::delete('/lotes/{id}', function ($id) {
    DB::table('lotes')->where('id', $id)->delete();
    return response()->json(['message' => 'Lote removido']);
});

// ============================================
// MOVIMENTAÇÕES
// ============================================

Route::get('/movimentacoes', function (Request $request) {
    try {
        $query = DB::table('movimentacoes')
            ->leftJoin('produtos', 'movimentacoes.produto_id', '=', 'produtos.id')
            ->leftJoin('unidades as unidades_origem', 'movimentacoes.de_unidade_id', '=', 'unidades_origem.id')
            ->leftJoin('unidades as unidades_destino', 'movimentacoes.para_unidade_id', '=', 'unidades_destino.id')
            ->leftJoin('usuarios', 'movimentacoes.usuario_id', '=', 'usuarios.id')
            ->select(
                'movimentacoes.*',
                'produtos.nome as produto_nome',
                'unidades_origem.nome as unidade_nome',
                'unidades_origem.nome as unidade_origem_nome',
                'unidades_destino.nome as unidade_destino_nome',
                'usuarios.nome as responsavel_nome'
            );
        
        if ($request->has('tipo') && $request->tipo) {
            $query->where('movimentacoes.tipo', $request->tipo);
        }
        
        if ($request->has('produto_id') && $request->produto_id) {
            $query->where('movimentacoes.produto_id', $request->produto_id);
        }
        
        if ($request->has('unidade_id') && $request->unidade_id) {
            $query->where(function($q) use ($request) {
                $q->where('movimentacoes.de_unidade_id', $request->unidade_id)
                  ->orWhere('movimentacoes.para_unidade_id', $request->unidade_id);
            });
        }
        
        if ($request->has('data_ini') && $request->data_ini) {
            $query->where('movimentacoes.data_mov', '>=', $request->data_ini);
        }
        
        if ($request->has('data_fim') && $request->data_fim) {
            $query->where('movimentacoes.data_mov', '<=', $request->data_fim);
        }
        
        $limit = $request->has('limit') ? (int)$request->limit : 50;
        $movimentacoes = $query->orderBy('movimentacoes.id', 'desc')->orderBy('movimentacoes.data_mov', 'desc')->limit($limit)->get();
        
        // Garante que os campos esperados pelo frontend estejam presentes
        $movimentacoes = $movimentacoes->map(function($mov) {
            // Busca nome da unidade de origem se não tiver
            if (!$mov->unidade_origem_nome && $mov->de_unidade_id) {
                $unidadeOrigem = DB::table('unidades')->where('id', $mov->de_unidade_id)->first();
                if ($unidadeOrigem) {
                    $mov->unidade_origem_nome = $unidadeOrigem->nome;
                }
            }
            
            // Busca nome da unidade de destino se não tiver
            if (!$mov->unidade_destino_nome && $mov->para_unidade_id) {
                $unidadeDestino = DB::table('unidades')->where('id', $mov->para_unidade_id)->first();
                if ($unidadeDestino) {
                    $mov->unidade_destino_nome = $unidadeDestino->nome;
                }
            }
            
            // Se não tiver unidade_nome, usa a unidade de origem
            if (!$mov->unidade_nome) {
                if ($mov->unidade_origem_nome) {
                    $mov->unidade_nome = $mov->unidade_origem_nome;
                } elseif ($mov->de_unidade_id) {
                    $unidade = DB::table('unidades')->where('id', $mov->de_unidade_id)->first();
                    if ($unidade) {
                        $mov->unidade_nome = $unidade->nome;
                        $mov->unidade_origem_nome = $unidade->nome;
                    }
                }
            } else {
                // Se já tem unidade_nome mas não tem unidade_origem_nome, usa o unidade_nome
                if (!$mov->unidade_origem_nome) {
                    $mov->unidade_origem_nome = $mov->unidade_nome;
                }
            }
            
            // Se não tiver responsavel_nome, tenta buscar do usuário
            if (!$mov->responsavel_nome && $mov->usuario_id) {
                $usuario = DB::table('usuarios')->where('id', $mov->usuario_id)->first();
                if ($usuario) {
                    $mov->responsavel_nome = $usuario->nome;
                }
            }
            // data_mov em ISO 8601 com timezone para o frontend converter para horário local
            if (!empty($mov->data_mov)) {
                try {
                    $mov->data_mov = \Carbon\Carbon::parse($mov->data_mov, config('app.timezone', 'America/Sao_Paulo'))
                        ->format('Y-m-d\TH:i:sP');
                } catch (\Exception $e) {
                    // mantém o valor original em caso de erro
                }
            }
            return $mov;
        });
        
        return response()->json($movimentacoes);
    } catch (\Exception $e) {
        \Log::error('Erro ao buscar movimentações: ' . $e->getMessage());
        return response()->json([], 200); // Retorna array vazio em caso de erro
    }
});

/**
 * Excluir movimentação (apenas ADMIN) - reverte o impacto no estoque
 * Permite corrigir erros de digitação em entrada/saída do dashboard
 */
Route::delete('/movimentacoes/{id}', function (Request $request, $id) {
    $usuarioId = $request->header('X-Usuario-Id');
    if (!$usuarioId) {
        return response()->json(['error' => 'Usuário não autenticado'], 401);
    }
    $usuario = DB::table('usuarios')->where('id', $usuarioId)->first();
    if (!$usuario) {
        return response()->json(['error' => 'Usuário não encontrado'], 401);
    }
    $perfil = strtoupper(trim($usuario->perfil ?? ''));
    if ($perfil !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem excluir movimentações'], 403);
    }

    $mov = DB::table('movimentacoes')->where('id', $id)->first();
    if (!$mov) {
        return response()->json(['error' => 'Movimentação não encontrada'], 404);
    }

    $tipo = strtoupper(trim($mov->tipo ?? ''));
    $qtd = (float) ($mov->qtd ?? 0);
    $produtoId = (int) $mov->produto_id;
    $deUnidadeId = (int) ($mov->de_unidade_id ?? 0);
    $paraUnidadeId = (int) ($mov->para_unidade_id ?? 0);

    if ($qtd <= 0) {
        return response()->json(['error' => 'Quantidade inválida'], 400);
    }

    try {
        DB::beginTransaction();

        if ($tipo === 'ENTRADA') {
            $loteId = $mov->lote_id ?? null;
            if (!$loteId) {
                DB::rollBack();
                return response()->json(['error' => 'Movimentação de entrada sem lote associado'], 400);
            }
            $lote = DB::table('lotes')->where('id', $loteId)->first();
            if (!$lote) {
                DB::rollBack();
                return response()->json(['error' => 'Lote não encontrado'], 400);
            }
            $codigoLote = $lote->numero_lote ?? '';
            if (!$codigoLote) {
                DB::rollBack();
                return response()->json(['error' => 'Lote sem código'], 400);
            }
            $unidadeId = (int) ($lote->unidade_id ?? $deUnidadeId);
            $stock = DB::table('stock_lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $unidadeId)
                ->where('codigo_lote', $codigoLote)
                ->first();
            if (!$stock) {
                DB::rollBack();
                return response()->json(['error' => 'Estoque do lote não encontrado'], 400);
            }
            $novaQtd = (float) $stock->quantidade - $qtd;
            if ($novaQtd <= 0) {
                DB::table('stock_lotes')->where('id', $stock->id)->delete();
            } else {
                DB::table('stock_lotes')->where('id', $stock->id)->update(['quantidade' => $novaQtd]);
            }
            $totalLote = DB::table('stock_lotes')
                ->where('codigo_lote', $codigoLote)
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $unidadeId)
                ->sum('quantidade');
            DB::table('lotes')->where('id', $loteId)->update(['qtd_atual' => $totalLote]);
        } elseif ($tipo === 'SAIDA') {
            $loteId = $mov->lote_id ?? null;
            $custoUnitario = (float) ($mov->custo_unitario ?? 0);
            $unidadeId = $deUnidadeId;
            $codigoLote = null;
            if ($loteId) {
                $lote = DB::table('lotes')->where('id', $loteId)->first();
                if ($lote) {
                    $codigoLote = $lote->numero_lote ?? null;
                }
            }
            if (!$codigoLote) {
                $codigoLote = 'REV-' . $produtoId . '-' . $unidadeId . '-' . now()->format('YmdHis');
                $localPadrao = DB::table('locais')->where('unidade_id', $unidadeId)->first();
                $localId = $localPadrao ? $localPadrao->id : null;
                $loteId = DB::table('lotes')->insertGetId([
                    'produto_id' => $produtoId,
                    'unidade_id' => $unidadeId,
                    'numero_lote' => $codigoLote,
                    'local_id' => $localId,
                    'qtd_atual' => $qtd,
                    'custo_unitario' => $custoUnitario,
                    'data_fabricacao' => null,
                    'data_validade' => null,
                    'ativo' => 1,
                    'criado_em' => now(),
                ]);
                DB::table('stock_lotes')->insert([
                    'produto_id' => $produtoId,
                    'unidade_id' => $unidadeId,
                    'codigo_lote' => $codigoLote,
                    'quantidade' => $qtd,
                    'custo_unitario' => $custoUnitario,
                    'data_fabricacao' => null,
                    'data_validade' => null,
                ]);
            } else {
                $stock = DB::table('stock_lotes')
                    ->where('produto_id', $produtoId)
                    ->where('unidade_id', $unidadeId)
                    ->where('codigo_lote', $codigoLote)
                    ->first();
                if ($stock) {
                    $novaQtd = (float) $stock->quantidade + $qtd;
                    DB::table('stock_lotes')->where('id', $stock->id)->update(['quantidade' => $novaQtd]);
                } else {
                    DB::table('stock_lotes')->insert([
                        'produto_id' => $produtoId,
                        'unidade_id' => $unidadeId,
                        'codigo_lote' => $codigoLote,
                        'quantidade' => $qtd,
                        'custo_unitario' => $custoUnitario,
                        'data_fabricacao' => null,
                        'data_validade' => null,
                    ]);
                }
                $totalLote = DB::table('stock_lotes')
                    ->where('codigo_lote', $codigoLote)
                    ->where('produto_id', $produtoId)
                    ->where('unidade_id', $unidadeId)
                    ->sum('quantidade');
                if ($loteId) {
                    DB::table('lotes')->where('id', $loteId)->update(['qtd_atual' => $totalLote]);
                }
            }
        } elseif ($tipo === 'TRANSFERENCIA') {
            if (!$paraUnidadeId || !$deUnidadeId) {
                DB::rollBack();
                return response()->json(['error' => 'Transferência sem unidade origem ou destino'], 400);
            }
            $codigoLote = null;
            $loteOrigem = null;
            if ($mov->lote_id) {
                $loteOrigem = DB::table('lotes')->where('id', $mov->lote_id)->first();
                if ($loteOrigem) {
                    $codigoLote = $loteOrigem->numero_lote ?? null;
                }
            }
            if (!$codigoLote) {
                DB::rollBack();
                return response()->json(['error' => 'Transferência sem lote associado para reverter'], 400);
            }
            $custoUnitario = (float) ($mov->custo_unitario ?? 0);

            // Reverter destino: remover qtd do stock na unidade destino
            $stockDestino = DB::table('stock_lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $paraUnidadeId)
                ->where('codigo_lote', $codigoLote)
                ->first();
            if (!$stockDestino) {
                DB::rollBack();
                return response()->json(['error' => 'Estoque do destino da transferência não encontrado'], 400);
            }
            $novaQtdDestino = (float) $stockDestino->quantidade - $qtd;
            if ($novaQtdDestino <= 0) {
                DB::table('stock_lotes')->where('id', $stockDestino->id)->delete();
            } else {
                DB::table('stock_lotes')->where('id', $stockDestino->id)->update(['quantidade' => $novaQtdDestino]);
            }
            $totalLoteDestino = DB::table('stock_lotes')
                ->where('codigo_lote', $codigoLote)
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $paraUnidadeId)
                ->sum('quantidade');
            $loteDestino = DB::table('lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $paraUnidadeId)
                ->where('numero_lote', $codigoLote)
                ->first();
            if ($loteDestino) {
                DB::table('lotes')->where('id', $loteDestino->id)->update(['qtd_atual' => $totalLoteDestino]);
            }

            // Reverter origem: devolver qtd ao stock na unidade origem
            $stockOrigem = DB::table('stock_lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $deUnidadeId)
                ->where('codigo_lote', $codigoLote)
                ->first();
            if ($stockOrigem) {
                $novaQtdOrigem = (float) $stockOrigem->quantidade + $qtd;
                DB::table('stock_lotes')->where('id', $stockOrigem->id)->update(['quantidade' => $novaQtdOrigem]);
            } else {
                $localPadrao = DB::table('locais')->where('unidade_id', $deUnidadeId)->first();
                $localId = $localPadrao ? $localPadrao->id : null;
                DB::table('stock_lotes')->insert([
                    'produto_id' => $produtoId,
                    'unidade_id' => $deUnidadeId,
                    'codigo_lote' => $codigoLote,
                    'quantidade' => $qtd,
                    'custo_unitario' => $custoUnitario,
                    'data_fabricacao' => null,
                    'data_validade' => $loteOrigem ? $loteOrigem->data_validade : null,
                ]);
            }
            $totalLoteOrigem = DB::table('stock_lotes')
                ->where('codigo_lote', $codigoLote)
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $deUnidadeId)
                ->sum('quantidade');
            $loteOrigemRow = DB::table('lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $deUnidadeId)
                ->where('numero_lote', $codigoLote)
                ->first();
            if ($loteOrigemRow) {
                DB::table('lotes')->where('id', $loteOrigemRow->id)->update(['qtd_atual' => $totalLoteOrigem]);
            } elseif ($mov->lote_id) {
                DB::table('lotes')->where('id', $mov->lote_id)->update(['qtd_atual' => $totalLoteOrigem]);
            }
        } else {
            DB::rollBack();
            return response()->json(['error' => 'Tipo de movimentação não suportado para exclusão'], 400);
        }

        // Registra a reversão no histórico de movimentações (antes de deletar a original)
        $produto = DB::table('produtos')->where('id', $produtoId)->first();
        $unidadeBase = $produto ? strtoupper(trim($produto->unidade_base ?? 'UND')) : 'UND';
        $unidadesValidas = ['UND', 'G', 'KG', 'ML', 'L', 'PCT', 'CX'];
        if (!in_array($unidadeBase, $unidadesValidas)) {
            $unidadeBase = 'UND';
        }
        $revDe = $deUnidadeId;
        $revPara = null;
        $descTipo = $tipo;
        if ($tipo === 'TRANSFERENCIA') {
            $revDe = $paraUnidadeId;
            $revPara = $deUnidadeId;
            $descTipo = 'transferência';
        } elseif ($tipo === 'ENTRADA') {
            $loteRev = $mov->lote_id ? DB::table('lotes')->where('id', $mov->lote_id)->first() : null;
            $revDe = $loteRev ? (int) ($loteRev->unidade_id ?? $deUnidadeId) : $deUnidadeId;
            $descTipo = 'entrada';
        } else {
            $descTipo = 'saída';
        }
        DB::table('movimentacoes')->insert([
            'produto_id' => $produtoId,
            'lote_id' => $mov->lote_id,
            'usuario_id' => $usuarioId,
            'tipo' => 'REVERSAO',
            'qtd' => $qtd,
            'unidade' => $unidadeBase,
            'custo_unitario' => (float) ($mov->custo_unitario ?? 0),
            'data_mov' => now(),
            'motivo' => 'REVERSAO',
            'observacao' => "Reversão da {$descTipo} #{$id}",
            'de_unidade_id' => $revDe ?: null,
            'para_unidade_id' => $revPara,
        ]);

        DB::table('movimentacoes')->where('id', $id)->delete();
        DB::commit();

        return response()->json([
            'message' => 'Movimentação excluída e estoque revertido com sucesso',
            'movimentacao_id' => (int) $id,
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Erro ao excluir movimentação: ' . $e->getMessage());
        return response()->json([
            'error' => 'Erro ao excluir movimentação',
            'message' => $e->getMessage(),
        ], 500);
    }
});

// ============================================
// ENTRADA DE ESTOQUE (centralizada via Service)
// ============================================

Route::post('/estoque/entradas', [EntradaEstoqueController::class, 'store']);

/**
 * Rota legada /entrada - delega para EntradaEstoqueService (retrocompatibilidade)
 * numero_lote é opcional: se vazio, o service gera automaticamente
 */
Route::post('/entrada', function (Request $request) {
    try {
        $data = $request->validate([
            'produto_id' => 'required|integer|exists:produtos,id',
            'numero_lote' => 'nullable|string|max:255',
            'qtd' => 'required|numeric|min:0.001',
            'custo_unitario' => 'required|numeric|min:0',
            'unidade_id' => 'required|integer|exists:unidades,id',
            'local_id' => 'nullable|integer|exists:locais,id',
            'data_validade' => 'nullable|date',
            'motivo' => 'nullable|string',
            'usuario_id' => 'required|integer|exists:usuarios,id',
        ]);
        $numeroLote = isset($data['numero_lote']) ? trim($data['numero_lote']) : null;
        $dados = [
            'produto_id' => $data['produto_id'],
            'unidade_id' => $data['unidade_id'],
            'quantidade' => floatval($data['qtd']),
            'qtd' => floatval($data['qtd']),
            'custo_unitario' => floatval($data['custo_unitario']),
            'usuario_id' => $data['usuario_id'],
            'numero_lote' => $numeroLote !== '' ? $numeroLote : null,
            'local_id' => $data['local_id'] ?? null,
            'data_validade' => $data['data_validade'] ?? null,
            'motivo' => $data['motivo'] ?? 'Entrada de estoque',
            'origem' => 'DASHBOARD',
        ];
        $service = app(EntradaEstoqueService::class);
        $resultado = $service->registrarEntrada($dados);
        $produto = DB::table('produtos')->where('id', $data['produto_id'])->first();
        return response()->json([
            'success' => true,
            'message' => "Entrada registrada com sucesso!",
            'details' => [
                'produto' => $produto->nome ?? 'Produto',
                'quantidade' => $data['qtd'],
                'lote' => $resultado['codigo_lote'],
                'unidade' => DB::table('unidades')->where('id', $data['unidade_id'])->value('nome') ?? 'N/A'
            ],
            'lote_id' => $resultado['lote_id'],
            'movimentacao_id' => $resultado['movimentacao_id'],
        ], 201)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    } catch (\Illuminate\Validation\ValidationException $e) {
        $errors = $e->errors();
        $firstError = collect($errors)->flatten()->first();
        return response()->json([
            'error' => 'Dados inválidos',
            'message' => $firstError ?? 'Verifique os dados informados e tente novamente.',
            'details' => $errors
        ], 422)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    } catch (\Exception $e) {
        \Log::error('Erro ao registrar entrada: ' . $e->getMessage());
        return response()->json([
            'error' => 'Erro ao registrar entrada',
            'message' => $e->getMessage()
        ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
});

/**
 * ⚠️ ROTA DE SAÍDA DE ESTOQUE ⚠️
 * ⚠️ CÓDIGO IMPLEMENTADO E TESTADO - MODIFICAR COM CUIDADO ⚠️
 * Esta rota reduz estoque usando FIFO, atualiza lotes e cria movimentações.
 * Suporta transferências entre unidades. Implementação completa com transações.
 */
/**
 * ⚠️ ROTA DE SAÍDA DE ESTOQUE ⚠️
 * ⚠️ CÓDIGO IMPLEMENTADO E TESTADO - MODIFICAR COM CUIDADO ⚠️
 * Esta rota reduz estoque usando FIFO, atualiza lotes e cria movimentações.
 * Suporta transferências entre unidades. Implementação completa com transações.
 */
Route::post('/saida', function (Request $request) {
    try {
        DB::beginTransaction();
        
        // Validação dos dados
        $data = $request->validate([
            'produto_id' => 'required|integer|exists:produtos,id',
            'de_unidade_id' => 'required|integer|exists:unidades,id',
            'qtd' => 'required|numeric|min:0.001',
            'motivo' => 'required|string|in:PRODUCAO,CONSUMO,PERDA,TRANSFERENCIA',
            'para_unidade_id' => 'nullable|integer|exists:unidades,id|required_if:motivo,TRANSFERENCIA',
            'usuario_id' => 'required|integer|exists:usuarios,id',
            'forcar' => 'nullable|boolean',
            'codigo_lote' => 'nullable|string|max:255',
        ]);
        
        $produtoId = $data['produto_id'];
        $unidadeId = $data['de_unidade_id'];
        $quantidadeSolicitada = floatval($data['qtd']);
        $motivo = $data['motivo'];
        $usuarioId = $data['usuario_id'];
        $forcar = $data['forcar'] ?? false;
        $isTransferencia = $motivo === 'TRANSFERENCIA';
        $paraUnidadeId = $isTransferencia ? $data['para_unidade_id'] : null;
        $codigoLoteFiltro = isset($data['codigo_lote']) ? trim($data['codigo_lote']) : null;
        
        // Verifica se o produto existe e está ativo
        $produto = DB::table('produtos')->where('id', $produtoId)->first();
        if (!$produto) {
            DB::rollBack();
            return response()->json([
                'error' => 'Produto não encontrado',
                'message' => 'O produto selecionado não existe no sistema.'
            ], 404);
        }
        
        // Verifica se o produto está ativo
        $produtoAtivo = isset($produto->ativo) ? (int)$produto->ativo : 1;
        if ($produtoAtivo != 1) {
            DB::rollBack();
            return response()->json([
                'error' => 'Produto inativo',
                'message' => 'O produto selecionado está inativo. Ative o produto antes de registrar saída.'
            ], 400);
        }
        
        // Verifica estoque disponível (filtra por lote específico se informado)
        $queryEstoque = DB::table('stock_lotes')
            ->where('produto_id', $produtoId)
            ->where('unidade_id', $unidadeId)
            ->where('quantidade', '>', 0);
        if ($codigoLoteFiltro) {
            $queryEstoque->where('codigo_lote', $codigoLoteFiltro);
        }
        $estoqueDisponivel = $queryEstoque->sum('quantidade');
        
        if ($estoqueDisponivel <= 0) {
            DB::rollBack();
            $msgLote = $codigoLoteFiltro ? " no lote '{$codigoLoteFiltro}'" : '';
            return response()->json([
                'error' => 'Sem estoque disponível',
                'message' => "Não há estoque disponível deste produto na unidade selecionada{$msgLote}.",
                'disponivel' => 0,
                'solicitado' => $quantidadeSolicitada,
            ], 400);
        }
        
        if ($estoqueDisponivel < $quantidadeSolicitada) {
            DB::rollBack();
            $produtoNome = $produto->nome ?? 'Produto';
            $msgLote = $codigoLoteFiltro ? " (lote: {$codigoLoteFiltro})" : '';
            return response()->json([
                'error' => 'Estoque insuficiente',
                'message' => "Estoque insuficiente{$msgLote}. Disponível: {$estoqueDisponivel}, Solicitado: {$quantidadeSolicitada}",
                'disponivel' => $estoqueDisponivel,
                'solicitado' => $quantidadeSolicitada,
                'produto' => $produtoNome,
            ], 400);
        }
        
        // Busca lotes disponíveis ordenados por validade (FIFO - primeiro a vencer primeiro)
        // Busca em stock_lotes e faz join com lotes
        $queryLotes = DB::table('stock_lotes')
            ->leftJoin('lotes', function($join) use ($produtoId, $unidadeId) {
                $join->on('lotes.numero_lote', '=', 'stock_lotes.codigo_lote')
                     ->where('lotes.produto_id', '=', $produtoId)
                     ->where('lotes.unidade_id', '=', $unidadeId);
            })
            ->where('stock_lotes.produto_id', $produtoId)
            ->where('stock_lotes.unidade_id', $unidadeId)
            ->where('stock_lotes.quantidade', '>', 0)
            ->select(
                'stock_lotes.id as stock_id',
                'lotes.id as lote_id',
                'stock_lotes.quantidade as quantidade_disponivel',
                'stock_lotes.custo_unitario',
                'lotes.data_validade',
                'stock_lotes.codigo_lote',
                'lotes.ativo as lote_status'
            )
            ->orderBy('lotes.data_validade', 'asc')
            ->orderBy('stock_lotes.id', 'asc');

        // Se um lote específico foi solicitado, filtra apenas ele
        if ($codigoLoteFiltro) {
            $queryLotes->where('stock_lotes.codigo_lote', $codigoLoteFiltro);
        }

        $lotesDisponiveis = $queryLotes->get();
        
        // Verifica se há lotes disponíveis
        if ($lotesDisponiveis->isEmpty()) {
            DB::rollBack();
            $produtoNome = $produto->nome ?? 'Produto';
            return response()->json([
                'error' => 'Nenhum lote disponível',
                'message' => "Não há lotes disponíveis para o produto '{$produtoNome}' na unidade selecionada.",
                'produto' => $produtoNome,
            ], 400);
        }
        
        // Se não forçar e houver lotes vencidos, verifica se deve usar
        $lotesVencidos = collect();
        if (!$forcar) {
            $lotesVencidos = $lotesDisponiveis->filter(function($lote) {
                return $lote->data_validade && $lote->data_validade < now()->format('Y-m-d');
            });
            
            if ($lotesVencidos->count() > 0 && $lotesDisponiveis->count() > $lotesVencidos->count()) {
                // Há lotes vencidos e não vencidos - não usar vencidos a menos que force
                $lotesDisponiveis = $lotesDisponiveis->filter(function($lote) {
                    return !$lote->data_validade || $lote->data_validade >= now()->format('Y-m-d');
                });
                
                // Se após filtrar vencidos não há lotes suficientes, informa
                $quantidadeDisponivelAposFiltro = $lotesDisponiveis->sum('quantidade_disponivel');
                if ($quantidadeDisponivelAposFiltro < $quantidadeSolicitada) {
                    DB::rollBack();
                    $produtoNome = $produto->nome ?? 'Produto';
                    return response()->json([
                        'error' => 'Lotes vencidos bloqueiam a saída',
                        'message' => "Há lotes vencidos que impedem a saída. Disponível (sem vencidos): {$quantidadeDisponivelAposFiltro}, Solicitado: {$quantidadeSolicitada}. Marque 'Forçar' para usar lotes vencidos.",
                        'disponivel' => $quantidadeDisponivelAposFiltro,
                        'solicitado' => $quantidadeSolicitada,
                        'produto' => $produtoNome,
                    ], 400);
                }
            }
        }
        
        $quantidadeRestante = $quantidadeSolicitada;
        $lotesUsados = [];
        $custoMedio = 0;
        $totalCusto = 0;
        
        // Processa saída dos lotes (FIFO)
        foreach ($lotesDisponiveis as $lote) {
            if ($quantidadeRestante <= 0) break;
            
            $quantidadeUsar = min($quantidadeRestante, $lote->quantidade_disponivel);
            $quantidadeRestante -= $quantidadeUsar;
            
            // Atualiza stock_lotes
            $novaQuantidade = $lote->quantidade_disponivel - $quantidadeUsar;
            
            if ($novaQuantidade <= 0) {
                // Remove o registro
                DB::table('stock_lotes')->where('id', $lote->stock_id)->delete();
            } else {
                DB::table('stock_lotes')
                    ->where('id', $lote->stock_id)
                    ->update([
                        'quantidade' => $novaQuantidade,
                    ]);
            }
            
            // Atualiza quantidade do lote se existir
            if ($lote->lote_id) {
                $quantidadeTotalLote = DB::table('stock_lotes')
                    ->where('codigo_lote', $lote->codigo_lote)
                    ->where('produto_id', $produtoId)
                    ->where('unidade_id', $unidadeId)
                    ->sum('quantidade');
                
                DB::table('lotes')
                    ->where('id', $lote->lote_id)
                    ->update([
                        'qtd_atual' => $quantidadeTotalLote,
                    ]);
            }
            
            $lotesUsados[] = [
                'lote_id' => $lote->lote_id,
                'codigo_lote' => $lote->codigo_lote,
                'quantidade' => $quantidadeUsar,
                'custo_unitario' => $lote->custo_unitario,
            ];
            
            $totalCusto += $quantidadeUsar * $lote->custo_unitario;
        }
        
        if ($quantidadeRestante > 0) {
            DB::rollBack();
            $produtoNome = $produto->nome ?? 'Produto';
            $quantidadeProcessada = $quantidadeSolicitada - $quantidadeRestante;
            return response()->json([
                'error' => 'Estoque insuficiente após processamento',
                'message' => "Não foi possível completar a saída. Processado: {$quantidadeProcessada}, Restante: {$quantidadeRestante}. Pode haver conflito de concorrência ou lotes indisponíveis.",
                'processado' => $quantidadeProcessada,
                'restante' => $quantidadeRestante,
                'solicitado' => $quantidadeSolicitada,
                'produto' => $produtoNome,
            ], 400);
        }
        
        $custoMedio = $totalCusto / $quantidadeSolicitada;
        
        // Se for transferência, cria entrada na unidade destino
        if ($isTransferencia && $paraUnidadeId) {
            // Busca o primeiro lote usado para criar entrada no destino
            $primeiroLote = $lotesUsados[0];
            
            // Busca unidade_base do produto
            $produto = DB::table('produtos')->where('id', $produtoId)->first();
            $unidadeBase = strtoupper(trim($produto->unidade_base ?? 'UND'));
            $unidadesValidas = ['UND', 'G', 'KG', 'ML', 'L', 'PCT', 'CX'];
            if (!in_array($unidadeBase, $unidadesValidas)) {
                $unidadeBase = 'UND';
            }
            
            // Busca data de validade do lote original se existir
            $dataValidadeDestino = null;
            if ($primeiroLote['lote_id']) {
                $loteOriginal = DB::table('lotes')->where('id', $primeiroLote['lote_id'])->first();
                if ($loteOriginal) {
                    $dataValidadeDestino = $loteOriginal->data_validade;
                }
            }
            
            // Busca ou cria depósito padrão na unidade destino
            // Prioriza depósitos do tipo DEPOSITO na unidade destino
            $localDestinoId = null;
            
            // Primeiro tenta encontrar um depósito do tipo DEPOSITO na unidade destino
            $depositoDestino = DB::table('locais')
                ->where('unidade_id', $paraUnidadeId)
                ->where('tipo', 'DEPOSITO')
                ->where('ativo', 1)
                ->orderBy('nome')
                ->first();
            
            if ($depositoDestino) {
                $localDestinoId = $depositoDestino->id;
                \Log::info("Transferência: Usando depósito existente na unidade destino", [
                    'local_id' => $localDestinoId,
                    'local_nome' => $depositoDestino->nome,
                    'unidade_id' => $paraUnidadeId
                ]);
            } else {
                // Se não encontrar depósito, busca qualquer local ativo na unidade
                $localPadrao = DB::table('locais')
                    ->where('unidade_id', $paraUnidadeId)
                    ->where('ativo', 1)
                    ->first();
                
                if ($localPadrao) {
                    $localDestinoId = $localPadrao->id;
                    \Log::info("Transferência: Usando local padrão na unidade destino", [
                        'local_id' => $localDestinoId,
                        'local_nome' => $localPadrao->nome,
                        'unidade_id' => $paraUnidadeId
                    ]);
                } else {
                    // Se não encontrar nenhum local, cria um depósito padrão
                    $unidadeDestino = DB::table('unidades')->where('id', $paraUnidadeId)->first();
                    $nomeDeposito = $unidadeDestino ? "Depósito {$unidadeDestino->nome}" : 'Depósito Principal';
                    
                    $localDestinoId = DB::table('locais')->insertGetId([
                        'nome' => $nomeDeposito,
                        'unidade_id' => $paraUnidadeId,
                        'tipo' => 'DEPOSITO',
                        'ativo' => 1,
                    ]);
                    
                    \Log::info("Transferência: Criado novo depósito na unidade destino", [
                        'local_id' => $localDestinoId,
                        'local_nome' => $nomeDeposito,
                        'unidade_id' => $paraUnidadeId
                    ]);
                }
            }
            
            // Busca stock_lotes existente na unidade destino
            $stockDestino = DB::table('stock_lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $paraUnidadeId)
                ->where('codigo_lote', $primeiroLote['codigo_lote'])
                ->lockForUpdate() // Lock para evitar concorrência
                ->first();
            
            // Atualiza ou cria stock_lotes na unidade destino
            if ($stockDestino) {
                // Se já existe stock_lotes, soma APENAS a quantidade sendo transferida (não duplica)
                $quantidadeAnterior = floatval($stockDestino->quantidade);
                $novaQuantidadeDestino = $quantidadeAnterior + $quantidadeSolicitada;
                $custoMedioDestino = (($quantidadeAnterior * floatval($stockDestino->custo_unitario)) + ($quantidadeSolicitada * $custoMedio)) / $novaQuantidadeDestino;
                
                DB::table('stock_lotes')
                    ->where('id', $stockDestino->id)
                    ->update([
                        'quantidade' => $novaQuantidadeDestino,
                        'custo_unitario' => $custoMedioDestino,
                    ]);
            } else {
                // Cria novo registro em stock_lotes
                DB::table('stock_lotes')->insert([
                    'produto_id' => $produtoId,
                    'unidade_id' => $paraUnidadeId,
                    'codigo_lote' => $primeiroLote['codigo_lote'],
                    'quantidade' => $quantidadeSolicitada,
                    'custo_unitario' => $custoMedio,
                    'data_fabricacao' => null,
                    'data_validade' => $dataValidadeDestino,
                ]);
            }
            
            // RECALCULA a quantidade total do lote baseado em stock_lotes (garante consistência)
            $quantidadeTotalLoteDestino = DB::table('stock_lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $paraUnidadeId)
                ->where('codigo_lote', $primeiroLote['codigo_lote'])
                ->sum('quantidade');
            
            // Verifica se já existe lote na unidade destino
            $loteDestino = DB::table('lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $paraUnidadeId)
                ->where('numero_lote', $primeiroLote['codigo_lote'])
                ->first();
            
            $loteDestinoId = null;
            if ($loteDestino) {
                // Se o lote já existe, atualiza a quantidade total baseado em stock_lotes
                $loteDestinoId = $loteDestino->id;
                DB::table('lotes')
                    ->where('id', $loteDestinoId)
                    ->update([
                        'qtd_atual' => $quantidadeTotalLoteDestino,
                    ]);
            } else {
                // Cria novo lote na unidade destino
                $loteDestinoId = DB::table('lotes')->insertGetId([
                    'produto_id' => $produtoId,
                    'unidade_id' => $paraUnidadeId,
                    'numero_lote' => $primeiroLote['codigo_lote'],
                    'qtd_atual' => $quantidadeTotalLoteDestino,
                    'unidade' => $unidadeBase,
                    'custo_unitario' => $custoMedio,
                    'data_fabricacao' => null,
                    'data_validade' => $dataValidadeDestino,
                    'local_id' => $localDestinoId,
                    'ativo' => 1,
                    'criado_em' => now(),
                ]);
            }
        }
        
        // Busca unidade_base do produto para o enum
        $produto = DB::table('produtos')->where('id', $produtoId)->first();
        $unidadeBase = strtoupper(trim($produto->unidade_base ?? 'UND'));
        $unidadesValidas = ['UND', 'G', 'KG', 'ML', 'L', 'PCT', 'CX'];
        if (!in_array($unidadeBase, $unidadesValidas)) {
            $unidadeBase = 'UND';
        }
        
        // Cria movimentação de saída
        $observacoes = "Lotes: " . implode(', ', array_map(function($l) {
            return $l['codigo_lote'] . " (" . $l['quantidade'] . ")";
        }, $lotesUsados));
        
        $loteIdUsado = !empty($lotesUsados) && isset($lotesUsados[0]['lote_id']) ? $lotesUsados[0]['lote_id'] : null;
        
        $movimentacaoId = DB::table('movimentacoes')->insertGetId([
            'produto_id' => $produtoId,
            'lote_id' => $loteIdUsado,
            'usuario_id' => $usuarioId,
            'tipo' => $isTransferencia ? 'TRANSFERENCIA' : 'SAIDA',
            'qtd' => $quantidadeSolicitada,
            'unidade' => $unidadeBase,
            'custo_unitario' => $custoMedio,
            'data_mov' => now(),
            'motivo' => $motivo,
            'observacao' => $observacoes,
            'de_unidade_id' => $unidadeId,
            'para_unidade_id' => $isTransferencia ? $paraUnidadeId : null,
        ]);
        
        // NOTA: Uma transferência gera apenas UMA movimentação do tipo TRANSFERENCIA
        // que já contém as informações de origem (de_unidade_id) e destino (para_unidade_id)
        // Não é necessário criar uma movimentação ENTRADA adicional
        
        DB::commit();
        
        return response()->json([
            'message' => $isTransferencia ? 'Transferência realizada com sucesso' : 'Saída registrada com sucesso',
            'movimentacao_id' => $movimentacaoId,
            'lotes_usados' => $lotesUsados,
            'custo_medio' => $custoMedio,
        ], 201);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
        return response()->json(['error' => 'Dados inválidos', 'details' => $e->errors()], 422);
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Erro ao registrar saída: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao registrar saída: ' . $e->getMessage()], 500);
    }
});

// ============================================
// DASHBOARD
// ============================================

Route::get('/estoque-abaixo-minimo', function () {
    // Produtos que já tiveram ao menos uma movimentação de ENTRADA (já foram abastecidos)
    $produtosComEntrada = DB::table('movimentacoes')
        ->where('tipo', 'ENTRADA')
        ->distinct()
        ->pluck('produto_id');

    $produtos = DB::table('produtos')
        ->leftJoin('stock_lotes', function($join) {
            $join->on('produtos.id', '=', 'stock_lotes.produto_id')
                 ->where('stock_lotes.quantidade', '>', 0);
        })
        ->select(
            'produtos.id',
            'produtos.nome',
            'produtos.unidade_base',
            'produtos.estoque_minimo',
            'produtos.ativo',
            DB::raw('COALESCE(SUM(stock_lotes.quantidade), 0) as estoque_atual')
        )
        ->where('produtos.ativo', 1)
        ->where('produtos.estoque_minimo', '>', 0)
        ->whereIn('produtos.id', $produtosComEntrada)
        ->groupBy(
            'produtos.id',
            'produtos.nome',
            'produtos.unidade_base',
            'produtos.estoque_minimo',
            'produtos.ativo'
        )
        ->havingRaw('COALESCE(SUM(stock_lotes.quantidade), 0) < produtos.estoque_minimo')
        ->get();
    
    return response()->json([
        'total' => $produtos->count(),
        'produtos' => $produtos
    ]);
});

Route::get('/perdas-recentes', function () {
    $movimentacoes = DB::table('movimentacoes')
        ->where('tipo', 'SAIDA')
        ->where('motivo', 'PERDA')
        ->where('data_mov', '>=', now()->subDays(30)->format('Y-m-d H:i:s'))
        ->orderBy('data_mov', 'desc')
        ->get();
    
    $quantidadeTotal = $movimentacoes->sum('qtd');
    
    return response()->json([
        'total_registros' => $movimentacoes->count(),
        'quantidade_total' => $quantidadeTotal,
        'movimentacoes' => $movimentacoes
    ]);
});

Route::get('/lotes-a-vencer', function (Request $request) {
    $dias = $request->has('dias') ? (int)$request->dias : 7;
    $dataLimite = now()->addDays($dias)->format('Y-m-d');
    $hoje = now()->format('Y-m-d');
    
    $lotes = DB::table('lotes')
        ->leftJoin('produtos', 'lotes.produto_id', '=', 'produtos.id')
        ->leftJoin('unidades', 'lotes.unidade_id', '=', 'unidades.id')
        ->select('lotes.*', 'produtos.nome as produto_nome', 'unidades.nome as unidade_nome')
        ->where('lotes.data_validade', '<=', $dataLimite)
        ->where('lotes.data_validade', '>=', $hoje)
        ->where('lotes.ativo', 1)
        ->where('lotes.qtd_atual', '>', 0)
        ->orderBy('lotes.data_validade')
        ->get();
    
    return response()->json($lotes);
});

// ============================================
// LISTAS DE COMPRAS
// ============================================

Route::get('/listas', function () {
    // Busca todas as listas com informações da unidade e responsável
    $listas = DB::table('listas_compras')
        ->leftJoin('unidades', 'listas_compras.unidade_id', '=', 'unidades.id')
        ->leftJoin('usuarios', 'listas_compras.responsavel_id', '=', 'usuarios.id')
        ->select(
            'listas_compras.*',
            'unidades.nome as unidade_nome',
            'usuarios.nome as responsavel_nome'
        )
        ->orderBy('listas_compras.criado_em', 'desc')
        ->get();
    
    // Para cada lista, calcula totais e contagem de itens
    $listas = $listas->map(function($lista) {
        $itens = DB::table('listas_itens')
            ->where('lista_id', $lista->id)
            ->get();
        
        $itensComprados = $itens->filter(function($item) {
            return ($item->status ?? '') === 'COMPRADO';
        });
        
        $lista->itens_total = $itens->count();
        $lista->itens_comprados = $itensComprados->count();
        $lista->total_planejado = $itens->sum('valor_planejado') ?? 0;
        $lista->total_realizado = $itens->sum('valor_total') ?? 0;
        
        return $lista;
    });
    
    return response()->json($listas);
});

Route::get('/listas/{id}', function ($id) {
    // Busca a lista com informações da unidade
    $lista = DB::table('listas_compras')
        ->leftJoin('unidades', 'listas_compras.unidade_id', '=', 'unidades.id')
        ->leftJoin('usuarios', 'listas_compras.responsavel_id', '=', 'usuarios.id')
        ->select(
            'listas_compras.*',
            'unidades.nome as unidade_nome',
            'usuarios.nome as responsavel_nome'
        )
        ->where('listas_compras.id', $id)
        ->first();
    
    if (!$lista) {
        return response()->json(['error' => 'Lista não encontrada'], 404);
    }
    
    // Busca os itens da lista
    $itens = DB::table('listas_itens')
        ->leftJoin('produtos', 'listas_itens.produto_id', '=', 'produtos.id')
        ->leftJoin('estabelecimentos_compra', 'listas_itens.estabelecimento_id', '=', 'estabelecimentos_compra.id')
        ->select(
            'listas_itens.*',
            'produtos.nome as produto_nome',
            'estabelecimentos_compra.nome as estabelecimento_nome'
        )
        ->where('listas_itens.lista_id', $id)
        ->orderBy('listas_itens.id')
        ->get();
    
    // Calcula totais
    $totalPlanejado = $itens->sum('valor_planejado') ?? 0;
    $totalRealizado = $itens->sum('valor_total') ?? 0;
    
    // Busca os estabelecimentos visitados desta lista
    $estabelecimentos = DB::table('estabelecimentos_compra')
        ->where('lista_id', $id)
        ->orderBy('nome')
        ->get();
    
    // Adiciona itens, estabelecimentos e totais ao objeto da lista
    $lista->itens = $itens;
    $lista->estabelecimentos = $estabelecimentos;
    $lista->total_planejado = $totalPlanejado;
    $lista->total_realizado = $totalRealizado;
    
    return response()->json($lista);
});

// CORS preflight para POST /listas
Route::options('/listas', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::post('/listas', function (Request $request) {
    try {
        $data = $request->all();
        
        \Log::info('POST /listas - Dados recebidos:', $data);
        
        // Validações básicas
        if (empty($data['nome']) || trim($data['nome']) === '') {
            return response()->json(['error' => 'Nome da lista é obrigatório'], 400);
        }
        
        // Valida unidade_id se fornecido
        if (isset($data['unidade_id']) && $data['unidade_id'] !== null) {
            $unidadeExiste = DB::table('unidades')->where('id', $data['unidade_id'])->exists();
            if (!$unidadeExiste) {
                return response()->json(['error' => 'Unidade não encontrada'], 400);
            }
        }
        
        // Valida responsavel_id se fornecido
        if (isset($data['responsavel_id']) && $data['responsavel_id'] !== null) {
            $usuarioExiste = DB::table('usuarios')->where('id', $data['responsavel_id'])->exists();
            if (!$usuarioExiste) {
                return response()->json(['error' => 'Usuário responsável não encontrado'], 400);
            }
        }
        
        // Prepara dados para inserção - apenas campos permitidos
        $insertData = [
            'nome' => trim($data['nome']),
            'unidade_id' => isset($data['unidade_id']) && $data['unidade_id'] !== null ? (int)$data['unidade_id'] : null,
            'responsavel_id' => isset($data['responsavel_id']) && $data['responsavel_id'] !== null ? (int)$data['responsavel_id'] : null,
            'status' => $data['status'] ?? 'RASCUNHO',
            'observacoes' => isset($data['observacoes']) && trim($data['observacoes']) !== '' ? trim($data['observacoes']) : null,
            'criado_em' => now(),
        ];
        
        \Log::info('POST /listas - Dados para inserção:', $insertData);
        
        $id = DB::table('listas_compras')->insertGetId($insertData);
        
        \Log::info('POST /listas - Lista criada com ID: ' . $id);
        
        // Retorna a lista criada com joins
        $lista = DB::table('listas_compras')
            ->leftJoin('unidades', 'listas_compras.unidade_id', '=', 'unidades.id')
            ->leftJoin('usuarios', 'listas_compras.responsavel_id', '=', 'usuarios.id')
            ->select(
                'listas_compras.*',
                'unidades.nome as unidade_nome',
                'usuarios.nome as responsavel_nome'
            )
            ->where('listas_compras.id', $id)
            ->first();
        
        if (!$lista) {
            \Log::error('POST /listas - Lista criada mas não foi possível recuperar');
            return response()->json(['error' => 'Lista criada mas não foi possível recuperar'], 500);
        }
        
        // Converte para array para poder adicionar propriedades
        $listaArray = (array) $lista;
        $listaArray['itens'] = [];
        $listaArray['total_planejado'] = 0;
        $listaArray['total_realizado'] = 0;
        $listaArray['itens_total'] = 0;
        $listaArray['itens_comprados'] = 0;
        
        return response()->json($listaArray, 201)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    } catch (\Exception $e) {
        \Log::error('Erro ao criar lista: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json(['error' => 'Erro ao criar lista: ' . $e->getMessage()], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
});

Route::put('/listas/{id}', function (Request $request, $id) {
    try {
        // Verifica se a lista existe
        $lista = DB::table('listas_compras')->where('id', $id)->first();
        if (!$lista) {
            return response()->json(['error' => 'Lista não encontrada'], 404)
                ->header('Access-Control-Allow-Origin', '*');
        }
        
        $data = $request->all();
        
        // Não permite alterar status para FINALIZADA por aqui (use a rota /finalizar)
        if (isset($data['status']) && $data['status'] === 'FINALIZADA' && ($lista->status ?? '') !== 'FINALIZADA') {
            return response()->json(['error' => 'Use a rota /listas/{id}/finalizar para finalizar a lista'], 400)
                ->header('Access-Control-Allow-Origin', '*');
        }
        
        // Prepara dados para atualização - apenas campos permitidos (sem updated_at que não existe na tabela)
        $updateData = [];
        if (isset($data['nome'])) $updateData['nome'] = trim($data['nome']);
        if (isset($data['unidade_id'])) $updateData['unidade_id'] = $data['unidade_id'] !== null ? (int)$data['unidade_id'] : null;
        if (isset($data['responsavel_id'])) $updateData['responsavel_id'] = $data['responsavel_id'] !== null ? (int)$data['responsavel_id'] : null;
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['observacoes'])) $updateData['observacoes'] = trim($data['observacoes']) !== '' ? trim($data['observacoes']) : null;
        
        DB::table('listas_compras')->where('id', $id)->update($updateData);
        
        // Retorna a lista atualizada com joins
        $listaAtualizada = DB::table('listas_compras')
            ->leftJoin('unidades', 'listas_compras.unidade_id', '=', 'unidades.id')
            ->leftJoin('usuarios', 'listas_compras.responsavel_id', '=', 'usuarios.id')
            ->select(
                'listas_compras.*',
                'unidades.nome as unidade_nome',
                'usuarios.nome as responsavel_nome'
            )
            ->where('listas_compras.id', $id)
            ->first();
        
        // Busca itens para calcular totais
        $itens = DB::table('listas_itens')
            ->leftJoin('produtos', 'listas_itens.produto_id', '=', 'produtos.id')
            ->select('listas_itens.*', 'produtos.nome as produto_nome')
            ->where('listas_itens.lista_id', $id)
            ->get();
        
        $listaArray = (array) $listaAtualizada;
        $listaArray['itens'] = $itens;
        $listaArray['total_planejado'] = $itens->sum('valor_planejado') ?? 0;
        $listaArray['total_realizado'] = $itens->sum('valor_total') ?? 0;
        $listaArray['itens_total'] = $itens->count();
        $listaArray['itens_comprados'] = $itens->filter(function($item) {
            return ($item->status ?? '') === 'COMPRADO';
        })->count();
        
        return response()->json($listaArray)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, GET, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Exception $e) {
        \Log::error('Erro ao atualizar lista: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao atualizar lista: ' . $e->getMessage()], 500);
    }
});

Route::options('/listas/{id}/finalizar', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

Route::put('/listas/{id}/finalizar', function (Request $request, $id) {
    try {
        \Log::info("Tentando finalizar lista ID: {$id}");
        
        // Verifica se a lista existe
        $lista = DB::table('listas_compras')->where('id', $id)->first();
        if (!$lista) {
            \Log::warning("Lista não encontrada: {$id}");
            return response()->json(['error' => 'Lista não encontrada'], 404)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
        
        // Verifica se já está finalizada
        $statusAtual = strtoupper($lista->status ?? '');
        if ($statusAtual === 'FINALIZADA') {
            \Log::info("Lista já está finalizada: {$id}");
            return response()->json(['error' => 'Lista já está finalizada'], 400)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
        
        $data = $request->all();
        \Log::info("Dados recebidos para finalizar lista: " . json_encode($data));
        
        // Atualiza a lista - usa os campos corretos da tabela
        $updateData = [
            'status' => 'FINALIZADA',
            'data_finalizacao' => now(),
        ];
        
        // Se observações foram fornecidas, atualiza o campo observacoes
        if (isset($data['observacoes']) && trim($data['observacoes']) !== '') {
            $updateData['observacoes'] = trim($data['observacoes']);
        }
        
        $updated = DB::table('listas_compras')->where('id', $id)->update($updateData);
        
        \Log::info("Lista atualizada: {$updated} linhas afetadas");
        
        // Retorna a lista atualizada com joins
        $listaAtualizada = DB::table('listas_compras')
            ->leftJoin('unidades', 'listas_compras.unidade_id', '=', 'unidades.id')
            ->leftJoin('usuarios', 'listas_compras.responsavel_id', '=', 'usuarios.id')
            ->select(
                'listas_compras.*',
                'unidades.nome as unidade_nome',
                'usuarios.nome as responsavel_nome'
            )
            ->where('listas_compras.id', $id)
            ->first();
        
        if (!$listaAtualizada) {
            \Log::error("Erro ao buscar lista atualizada: {$id}");
            return response()->json(['error' => 'Erro ao buscar lista atualizada'], 500)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
        
        // Busca itens para calcular totais
        $itens = DB::table('listas_itens')
            ->leftJoin('produtos', 'listas_itens.produto_id', '=', 'produtos.id')
            ->leftJoin('estabelecimentos_compra', 'listas_itens.estabelecimento_id', '=', 'estabelecimentos_compra.id')
            ->select(
                'listas_itens.*',
                'produtos.nome as produto_nome',
                'estabelecimentos_compra.nome as estabelecimento_nome'
            )
            ->where('listas_itens.lista_id', $id)
            ->orderBy('listas_itens.id')
            ->get();
        
        $listaAtualizada->itens = $itens;
        $listaAtualizada->total_planejado = $itens->sum('valor_planejado') ?? 0;
        $listaAtualizada->total_realizado = $itens->sum('valor_total') ?? 0;
        
        \Log::info("Lista finalizada com sucesso: {$id}");
        
        return response()->json($listaAtualizada)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    } catch (\Exception $e) {
        \Log::error('Erro ao finalizar lista: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json(['error' => 'Erro ao finalizar lista: ' . $e->getMessage()], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'PUT, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
});

/**
 * ⚠️ ROTA DE LANÇAR LISTA DE COMPRAS NO ESTOQUE ⚠️
 * ⚠️ CÓDIGO IMPLEMENTADO E TESTADO - MODIFICAR COM CUIDADO ⚠️
 * 
 * Esta rota processa itens comprados e cria entradas no estoque automaticamente.
 * Implementação completa com validações, transações e tratamento de erros.
 * 
 * IMPORTANTE: Esta rota segue o mesmo padrão da rota /entrada que funciona corretamente:
 * - Usa 'numero_lote' na tabela 'lotes'
 * - Usa 'codigo_lote' na tabela 'stock_lotes'
 * - Cria local padrão se não existir
 * - Atualiza ou cria registros em stock_lotes usando codigo_lote
 * 
 * Fluxo:
 * 1. Valida lista e itens comprados
 * 2. Para cada item: cria/atualiza lote, atualiza stock_lotes, cria movimentação
 * 3. Marca lista como lançada no estoque
 * 4. Retorna lista atualizada
 */
Route::options('/listas/{id}/estoque', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

Route::post('/listas/{id}/estoque', function (Request $request, $id) {
    try {
        \Log::info("Tentando lançar lista no estoque ID: {$id}");
        
        DB::beginTransaction();
        
        // Validação
        $data = $request->validate([
            'usuario_id' => 'required|integer|exists:usuarios,id',
        ]);
        
        $usuarioId = $data['usuario_id'];
        
        // Busca a lista de compras
        $lista = DB::table('listas_compras')->where('id', $id)->first();
        if (!$lista) {
            return response()->json(['error' => 'Lista de compras não encontrada'], 404);
        }
        
        // Verifica se a lista está finalizada
        if (strtoupper($lista->status ?? '') !== 'FINALIZADA') {
            return response()->json(['error' => 'A lista deve estar finalizada para lançar no estoque'], 400);
        }
        
        // Verifica se já foi lançada
        if ($lista->estoque_lancado_em) {
            return response()->json(['error' => 'Lista já foi lançada no estoque'], 400);
        }
        
        // Busca TODOS os itens da lista (não apenas os com status COMPRADO)
        // Filtra apenas itens que têm quantidade comprada > 0
        $itens = DB::table('listas_itens')
            ->where('lista_id', $id)
            ->where('quantidade_comprada', '>', 0)
            ->get();
    
        if ($itens->isEmpty()) {
            return response()->json(['error' => 'Nenhum item com quantidade comprada encontrado na lista'], 400);
        }
        
        $unidadeId = $lista->unidade_id;
        
        // Valida unidade_id da lista antes de processar
        if (!$unidadeId || $unidadeId <= 0) {
            DB::rollBack();
            return response()->json(['error' => 'Lista sem unidade válida'], 400);
        }
        
        $entradasCriadas = [];
        $erros = [];

        // Processa cada item: soma direto no stock_lotes existente, sem criar lote novo
        foreach ($itens as $item) {
            try {
                $quantidadeComprada = floatval($item->quantidade_comprada ?? 0);
                $precoUnitario      = floatval($item->preco_unitario ?? $item->valor_unitario ?? 0);

                if ($quantidadeComprada <= 0) {
                    $erros[] = "Item ID {$item->id}: quantidade comprada inválida";
                    continue;
                }
                if ($precoUnitario < 0) {
                    $erros[] = "Item ID {$item->id}: preço unitário inválido";
                    continue;
                }

                $produto = DB::table('produtos')->where('id', $item->produto_id)->first();
                if (!$produto) {
                    $erros[] = "Item ID {$item->id}: produto não encontrado";
                    continue;
                }

                // Busca o stock_lotes existente para produto+unidade (qualquer lote ativo)
                $stockLote = DB::table('stock_lotes')
                    ->where('produto_id', $item->produto_id)
                    ->where('unidade_id', $unidadeId)
                    ->orderByDesc('id')
                    ->first();

                if ($stockLote) {
                    // Soma a quantidade no registro existente e recalcula custo médio
                    $novaQtd    = $stockLote->quantidade + $quantidadeComprada;
                    $custoMedio = (($stockLote->quantidade * $stockLote->custo_unitario) + ($quantidadeComprada * $precoUnitario)) / $novaQtd;

                    DB::table('stock_lotes')
                        ->where('id', $stockLote->id)
                        ->update([
                            'quantidade'     => $novaQtd,
                            'custo_unitario' => $custoMedio,
                        ]);

                    $codigoLote = $stockLote->codigo_lote;
                    $loteId     = DB::table('lotes')
                        ->where('produto_id', $item->produto_id)
                        ->where('unidade_id', $unidadeId)
                        ->where('numero_lote', $codigoLote)
                        ->value('id');

                    // Atualiza qtd_atual do lote pai se existir
                    if ($loteId) {
                        DB::table('lotes')->where('id', $loteId)->update(['qtd_atual' => $novaQtd]);
                    }
                } else {
                    // Não existe stock ainda — cria um registro novo com código único
                    $codigoLote = 'ENT-' . $item->produto_id . '-' . $unidadeId . '-' . now()->format('YmdHis');

                    // Garante que o local existe
                    $localId = DB::table('locais')->where('unidade_id', $unidadeId)->value('id');
                    if (!$localId) {
                        $localId = DB::table('locais')->insertGetId([
                            'nome'      => 'Depósito Principal',
                            'unidade_id'=> $unidadeId,
                            'tipo'      => 'DEPOSITO',
                            'ativo'     => 1,
                        ]);
                    }

                    $loteId = DB::table('lotes')->insertGetId([
                        'produto_id'     => $item->produto_id,
                        'unidade_id'     => $unidadeId,
                        'numero_lote'    => $codigoLote,
                        'qtd_atual'      => $quantidadeComprada,
                        'unidade'        => strtoupper(trim($produto->unidade_base ?? 'UND')),
                        'custo_unitario' => $precoUnitario,
                        'data_validade'  => $item->data_validade ?? null,
                        'local_id'       => $localId,
                        'ativo'          => 1,
                        'criado_em'      => now(),
                    ]);

                    DB::table('stock_lotes')->insert([
                        'produto_id'     => $item->produto_id,
                        'unidade_id'     => $unidadeId,
                        'codigo_lote'    => $codigoLote,
                        'quantidade'     => $quantidadeComprada,
                        'custo_unitario' => $precoUnitario,
                        'data_validade'  => $item->data_validade ?? null,
                        'data_fabricacao'=> null,
                    ]);
                }

                // Registra a movimentação de ENTRADA
                $movimentacaoId = DB::table('movimentacoes')->insertGetId([
                    'produto_id'    => $item->produto_id,
                    'lote_id'       => $loteId ?? null,
                    'usuario_id'    => $usuarioId,
                    'tipo'          => 'ENTRADA',
                    'qtd'           => $quantidadeComprada,
                    'unidade'       => strtoupper(trim($produto->unidade_base ?? 'UND')),
                    'custo_unitario'=> $precoUnitario,
                    'data_mov'      => now(),
                    'motivo'        => 'Lista de compras',
                    'observacao'    => "Lista de compras #{$id}",
                    'de_unidade_id' => $unidadeId,
                ]);

                $entradasCriadas[] = [
                    'item_id'        => $item->id,
                    'produto_id'     => $item->produto_id,
                    'lote_id'        => $loteId ?? null,
                    'movimentacao_id'=> $movimentacaoId,
                ];
                \Log::info("Entrada lista #{$id} - Produto: {$item->produto_id}, Qtd: {$quantidadeComprada}, Unidade: {$unidadeId}");

            } catch (\Exception $e) {
                $erros[] = "Item ID {$item->id}: " . $e->getMessage();
                \Log::error("Erro ao processar item da lista: " . $e->getMessage());
            }
        }
        
        if (empty($entradasCriadas) && !empty($erros)) {
            DB::rollBack();
            return response()->json([
                'error' => 'Nenhum item pôde ser lançado no estoque',
                'erros' => $erros,
            ], 400);
        }
        
        // Atualiza a lista para marcar que foi lançada no estoque
        DB::table('listas_compras')
            ->where('id', $id)
            ->update([
                'estoque_lancado_em' => now(),
            ]);
        
        DB::commit();
        
        // ✅ Verifica se as movimentações foram realmente criadas e estão acessíveis
        $movimentacoesCriadas = DB::table('movimentacoes')
            ->whereIn('id', array_column($entradasCriadas, 'movimentacao_id'))
            ->get();
        
        \Log::info("Total de movimentações criadas: " . count($entradasCriadas));
        \Log::info("Total de movimentações verificadas no banco: " . $movimentacoesCriadas->count());
        
        if ($movimentacoesCriadas->count() !== count($entradasCriadas)) {
            \Log::warning("Algumas movimentações podem não ter sido criadas corretamente");
        }
        
        // Busca a lista atualizada
        $listaAtualizada = DB::table('listas_compras')
            ->leftJoin('unidades', 'listas_compras.unidade_id', '=', 'unidades.id')
            ->leftJoin('usuarios', 'listas_compras.responsavel_id', '=', 'usuarios.id')
            ->select(
                'listas_compras.*',
                'unidades.nome as unidade_nome',
                'usuarios.nome as responsavel_nome'
            )
            ->where('listas_compras.id', $id)
            ->first();
        
        // Busca itens para calcular totais
        $itens = DB::table('listas_itens')
            ->leftJoin('produtos', 'listas_itens.produto_id', '=', 'produtos.id')
            ->leftJoin('estabelecimentos_compra', 'listas_itens.estabelecimento_id', '=', 'estabelecimentos_compra.id')
            ->select(
                'listas_itens.*',
                'produtos.nome as produto_nome',
                'estabelecimentos_compra.nome as estabelecimento_nome'
            )
            ->where('listas_itens.lista_id', $id)
            ->orderBy('listas_itens.id')
            ->get();
        
        // Busca os estabelecimentos visitados desta lista
        $estabelecimentos = DB::table('estabelecimentos_compra')
            ->where('lista_id', $id)
            ->orderBy('nome')
            ->get();
        
        $listaAtualizada->itens = $itens;
        $listaAtualizada->estabelecimentos = $estabelecimentos;
        $listaAtualizada->total_planejado = $itens->sum('valor_planejado') ?? 0;
        $listaAtualizada->total_realizado = $itens->sum('valor_total') ?? 0;
        
        // Adiciona informações adicionais à lista (se necessário para debug)
        if (!empty($erros)) {
            $listaAtualizada->estoque_lancamento_erros = $erros;
        }
        $listaAtualizada->estoque_entradas_criadas = count($entradasCriadas);
        
        // Retorna a lista atualizada (frontend espera receber a lista diretamente)
        \Log::info("Lista lançada no estoque com sucesso: {$id}");
        
        return response()->json($listaAtualizada, 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
        \Log::error('Erro de validação ao lançar lista no estoque: ' . json_encode($e->errors()));
        return response()->json(['error' => 'Dados inválidos', 'details' => $e->errors()], 422)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Erro ao lançar lista no estoque: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json(['error' => 'Erro ao lançar lista no estoque: ' . $e->getMessage()], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
});

// Rota de PDF removida - o PDF é gerado diretamente no navegador (frontend)
// sem criar arquivo no servidor, seguindo o mesmo padrão dos relatórios

// ============================================
// ITENS DE COMPRA
// ============================================

// CORS preflight para POST /itens
Route::options('/itens', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::get('/itens', function (Request $request) {
    $query = DB::table('listas_itens')
        ->leftJoin('produtos', 'listas_itens.produto_id', '=', 'produtos.id');
    
    if ($request->has('lista_id')) {
        $query->where('listas_itens.lista_id', $request->lista_id);
    }
    
    $itens = $query->select('listas_itens.*', 'produtos.nome as produto_nome')->get();
    return response()->json($itens);
});

Route::get('/itens/{id}', function ($id) {
    $item = DB::table('listas_itens')->where('id', $id)->first();
    if (!$item) {
        return response()->json(['error' => 'Item não encontrado'], 404);
    }
    return response()->json($item);
});

Route::post('/itens', function (Request $request) {
    try {
        $requestData = $request->all();
        \Log::info('POST /itens - Dados recebidos:', $requestData);
        
        $data = $request->validate([
            'lista_id' => 'required|integer|exists:listas_compras,id',
            'produto_id' => 'required|integer|exists:produtos,id', // NOT NULL na tabela
            'quantidade_planejada' => 'nullable|numeric|min:0',
            'quantidade_comprada' => 'nullable|numeric|min:0',
            'valor_unitario' => 'nullable|numeric|min:0',
            'valor_planejado' => 'nullable|numeric|min:0',
            'valor_total' => 'nullable|numeric|min:0',
            'unidade' => 'required|string|max:20', // NOT NULL na tabela, max 20 caracteres
            'observacoes' => 'nullable|string',
            'estabelecimento_id' => 'nullable|integer',
            'status' => 'nullable|string|in:PENDENTE,COMPRADO,CANCELADO', // Valores válidos do ENUM
        ]);
        
        // Prepara dados para inserção - apenas campos que existem na tabela
        // NOTA: 'status' não é incluído - o banco usará o valor padrão 'PENDENTE'
        $insertData = [
            'lista_id' => (int)$data['lista_id'],
            'produto_id' => (int)$data['produto_id'], // Obrigatório
            'quantidade_planejada' => isset($data['quantidade_planejada']) ? floatval($data['quantidade_planejada']) : 0,
            'quantidade_comprada' => isset($data['quantidade_comprada']) ? floatval($data['quantidade_comprada']) : 0,
            'valor_unitario' => isset($data['valor_unitario']) ? floatval($data['valor_unitario']) : 0,
            'valor_planejado' => isset($data['valor_planejado']) ? floatval($data['valor_planejado']) : 0,
            'valor_total' => isset($data['valor_total']) ? floatval($data['valor_total']) : 0,
            'unidade' => trim($data['unidade']), // Obrigatório, max 20 caracteres
            'observacoes' => isset($data['observacoes']) && trim($data['observacoes']) !== '' ? trim($data['observacoes']) : null,
            'estabelecimento_id' => isset($data['estabelecimento_id']) && $data['estabelecimento_id'] !== null && $data['estabelecimento_id'] !== '' ? (int)$data['estabelecimento_id'] : null,
        ];
        
        // Valida se o estabelecimento existe antes de inserir (se fornecido)
        if ($insertData['estabelecimento_id'] !== null) {
            $estabelecimentoExiste = DB::table('estabelecimentos_compra')
                ->where('id', $insertData['estabelecimento_id'])
                ->exists();
            if (!$estabelecimentoExiste) {
                \Log::warning('POST /itens - Estabelecimento ID ' . $insertData['estabelecimento_id'] . ' não existe em estabelecimentos_compra');
                $insertData['estabelecimento_id'] = null; // Define como null se não existir
            }
        }
        
        \Log::info('POST /itens - Dados para inserção:', $insertData);
        
        $id = DB::table('listas_itens')->insertGetId($insertData);
        
        \Log::info('POST /itens - Item criado com ID: ' . $id);
        
        // Retorna o item criado com join do produto
        $item = DB::table('listas_itens')
            ->leftJoin('produtos', 'listas_itens.produto_id', '=', 'produtos.id')
            ->leftJoin('estabelecimentos_compra', 'listas_itens.estabelecimento_id', '=', 'estabelecimentos_compra.id')
            ->select(
                'listas_itens.*',
                'produtos.nome as produto_nome',
                'estabelecimentos_compra.nome as estabelecimento_nome'
            )
            ->where('listas_itens.id', $id)
            ->first();
        
        if (!$item) {
            \Log::error('POST /itens - Item criado mas não foi possível recuperar');
            return response()->json(['error' => 'Item criado mas não foi possível recuperar'], 500)
                ->header('Access-Control-Allow-Origin', '*');
        }
        
        return response()->json($item, 201)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::error('POST /itens - Erro de validação: ' . json_encode($e->errors()));
        $errors = $e->errors();
        $errorMessage = 'Dados inválidos';
        if (!empty($errors)) {
            $errorMessage = implode(', ', array_map(function($fieldErrors) {
                return implode(', ', $fieldErrors);
            }, array_values($errors)));
        }
        return response()->json([
            'error' => $errorMessage,
            'details' => $errors
        ], 422)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Illuminate\Database\QueryException $e) {
        \Log::error('POST /itens - Erro de banco de dados: ' . $e->getMessage());
        \Log::error('SQL: ' . $e->getSql());
        \Log::error('Bindings: ' . json_encode($e->getBindings()));
        
        // Trata erros específicos do MySQL
        $errorMessage = 'Erro ao salvar item no banco de dados';
        if (strpos($e->getMessage(), 'Data truncated for column') !== false) {
            $errorMessage = 'Valor inválido para o campo status. Use: PENDENTE, COMPRADO ou CANCELADO';
        } elseif (strpos($e->getMessage(), 'Column') !== false && strpos($e->getMessage(), 'cannot be null') !== false) {
            $errorMessage = 'Campos obrigatórios não preenchidos';
        } elseif (strpos($e->getMessage(), 'foreign key constraint fails') !== false && strpos($e->getMessage(), 'estabelecimento') !== false) {
            $errorMessage = 'Estabelecimento selecionado não existe. O item será salvo sem estabelecimento.';
        }
        
        return response()->json([
            'error' => $errorMessage,
            'message' => $e->getMessage()
        ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Exception $e) {
        \Log::error('POST /itens - Erro ao criar item: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json([
            'error' => 'Erro ao criar item',
            'message' => $e->getMessage()
        ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
});

Route::put('/itens/{id}', function (Request $request, $id) {
    try {
        // Verifica se o item existe
        $item = DB::table('listas_itens')->where('id', $id)->first();
        if (!$item) {
            return response()->json(['error' => 'Item não encontrado'], 404);
        }
        
        $data = $request->validate([
            'produto_id' => 'nullable|integer|exists:produtos,id',
            'quantidade_planejada' => 'nullable|numeric|min:0',
            'quantidade_comprada' => 'nullable|numeric|min:0',
            'valor_unitario' => 'nullable|numeric|min:0',
            'valor_planejado' => 'nullable|numeric|min:0',
            'valor_total' => 'nullable|numeric|min:0',
            'unidade' => 'nullable|string|max:20', // Max 20 caracteres conforme tabela
            'observacoes' => 'nullable|string',
            'estabelecimento_id' => 'nullable|integer',
            'status' => 'nullable|string|in:PENDENTE,COMPRADO,CANCELADO', // Valores válidos do ENUM
        ]);
        
        // Prepara dados para atualização - apenas campos que existem na tabela
        $updateData = [];
        if (isset($data['produto_id'])) $updateData['produto_id'] = $data['produto_id'] !== null ? (int)$data['produto_id'] : null;
        if (isset($data['quantidade_planejada'])) $updateData['quantidade_planejada'] = floatval($data['quantidade_planejada']);
        if (isset($data['quantidade_comprada'])) $updateData['quantidade_comprada'] = floatval($data['quantidade_comprada']);
        if (isset($data['valor_unitario'])) $updateData['valor_unitario'] = floatval($data['valor_unitario']);
        if (isset($data['valor_planejado'])) $updateData['valor_planejado'] = floatval($data['valor_planejado']);
        if (isset($data['valor_total'])) $updateData['valor_total'] = floatval($data['valor_total']);
        if (isset($data['unidade'])) $updateData['unidade'] = trim($data['unidade']) !== '' ? trim($data['unidade']) : null;
        if (isset($data['observacoes'])) $updateData['observacoes'] = trim($data['observacoes']) !== '' ? trim($data['observacoes']) : null;
        if (isset($data['estabelecimento_id'])) {
            $estId = $data['estabelecimento_id'] !== null && $data['estabelecimento_id'] !== '' ? (int)$data['estabelecimento_id'] : null;
            // Valida se o estabelecimento existe antes de atualizar (se fornecido)
            if ($estId !== null) {
                $estabelecimentoExiste = DB::table('estabelecimentos_compra')
                    ->where('id', $estId)
                    ->exists();
                if (!$estabelecimentoExiste) {
                    \Log::warning('PUT /itens/{id} - Estabelecimento ID ' . $estId . ' não existe em estabelecimentos_compra');
                    $estId = null; // Define como null se não existir
                }
            }
            $updateData['estabelecimento_id'] = $estId;
        }
        // Status: apenas atualiza se for um valor válido do ENUM
        if (isset($data['status']) && in_array($data['status'], ['PENDENTE', 'COMPRADO', 'CANCELADO'])) {
            $updateData['status'] = $data['status'];
        }
        
        DB::table('listas_itens')->where('id', $id)->update($updateData);
        
        // Retorna o item atualizado com join do produto
        $itemAtualizado = DB::table('listas_itens')
            ->leftJoin('produtos', 'listas_itens.produto_id', '=', 'produtos.id')
            ->leftJoin('estabelecimentos_compra', 'listas_itens.estabelecimento_id', '=', 'estabelecimentos_compra.id')
            ->select(
                'listas_itens.*',
                'produtos.nome as produto_nome',
                'estabelecimentos_compra.nome as estabelecimento_nome'
            )
            ->where('listas_itens.id', $id)
            ->first();
        
        return response()->json($itemAtualizado)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Illuminate\Validation\ValidationException $e) {
        $errors = $e->errors();
        $errorMessage = 'Dados inválidos';
        if (!empty($errors)) {
            $errorMessage = implode(', ', array_map(function($fieldErrors) {
                return implode(', ', $fieldErrors);
            }, array_values($errors)));
        }
        return response()->json([
            'error' => $errorMessage,
            'details' => $errors
        ], 422)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Illuminate\Database\QueryException $e) {
        \Log::error('PUT /itens/{id} - Erro de banco de dados: ' . $e->getMessage());
        \Log::error('SQL: ' . $e->getSql());
        \Log::error('Bindings: ' . json_encode($e->getBindings()));
        
        $errorMessage = 'Erro ao atualizar item no banco de dados';
        if (strpos($e->getMessage(), 'Data truncated for column') !== false) {
            $errorMessage = 'Valor inválido para o campo status. Use: PENDENTE, COMPRADO ou CANCELADO';
        } elseif (strpos($e->getMessage(), 'Column') !== false && strpos($e->getMessage(), 'cannot be null') !== false) {
            $errorMessage = 'Campos obrigatórios não preenchidos';
        } elseif (strpos($e->getMessage(), 'foreign key constraint fails') !== false && strpos($e->getMessage(), 'estabelecimento') !== false) {
            $errorMessage = 'Estabelecimento selecionado não existe. O item será atualizado sem estabelecimento.';
        }
        
        return response()->json([
            'error' => $errorMessage,
            'message' => $e->getMessage()
        ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Exception $e) {
        \Log::error('PUT /itens/{id} - Erro ao atualizar item: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json([
            'error' => 'Erro ao atualizar item',
            'message' => $e->getMessage()
        ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
});

Route::delete('/itens/{id}', function ($id) {
    DB::table('listas_itens')->where('id', $id)->delete();
    return response()->json(['message' => 'Item removido']);
});

// ============================================
// ESTABELECIMENTOS
// ============================================

Route::get('/estabelecimentos-globais', function () {
    $estabelecimentos = DB::table('estabelecimentos_globais')->orderBy('nome')->get();
    return response()->json($estabelecimentos);
});

Route::post('/estabelecimentos-globais', function (Request $request) {
    $data = $request->all();
    $id = DB::table('estabelecimentos_globais')->insertGetId($data);
    return response()->json(DB::table('estabelecimentos_globais')->where('id', $id)->first(), 201);
});

Route::put('/estabelecimentos-globais/{id}', function (Request $request, $id) {
    $data = $request->all();
    DB::table('estabelecimentos_globais')->where('id', $id)->update($data);
    return response()->json(DB::table('estabelecimentos_globais')->where('id', $id)->first());
});

Route::delete('/estabelecimentos-globais/{id}', function ($id) {
    DB::table('estabelecimentos_globais')->where('id', $id)->delete();
    return response()->json(['message' => 'Estabelecimento removido']);
});

// ============================================
// ESTABELECIMENTOS VISITADOS POR LISTA
// ============================================

Route::get('/listas/{lista_id}/estabelecimentos', function ($lista_id) {
    $estabelecimentos = DB::table('estabelecimentos_compra')
        ->where('lista_id', $lista_id)
        ->orderBy('nome')
        ->get();
    return response()->json($estabelecimentos);
});

Route::post('/listas/{lista_id}/estabelecimentos', function (Request $request, $lista_id) {
    try {
        // Valida se a lista existe
        $lista = DB::table('listas_compras')->where('id', $lista_id)->first();
        if (!$lista) {
            return response()->json(['error' => 'Lista não encontrada'], 404);
        }
        
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'localizacao' => 'nullable|string|max:500',
            'forma_pagamento' => 'nullable|string|max:100',
            'observacoes' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);
        
        $insertData = [
            'lista_id' => (int)$lista_id,
            'nome' => trim($data['nome']),
            'localizacao' => isset($data['localizacao']) && trim($data['localizacao']) !== '' ? trim($data['localizacao']) : null,
            'forma_pagamento' => isset($data['forma_pagamento']) && trim($data['forma_pagamento']) !== '' ? trim($data['forma_pagamento']) : null,
            'observacoes' => isset($data['observacoes']) && trim($data['observacoes']) !== '' ? trim($data['observacoes']) : null,
            'latitude' => isset($data['latitude']) && $data['latitude'] !== null ? floatval($data['latitude']) : null,
            'longitude' => isset($data['longitude']) && $data['longitude'] !== null ? floatval($data['longitude']) : null,
            'criado_em' => now(),
        ];
        
        $id = DB::table('estabelecimentos_compra')->insertGetId($insertData);
        $estabelecimento = DB::table('estabelecimentos_compra')->where('id', $id)->first();
        
        return response()->json($estabelecimento, 201)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Exception $e) {
        \Log::error('POST /listas/{lista_id}/estabelecimentos - Erro: ' . $e->getMessage());
        return response()->json([
            'error' => 'Erro ao criar estabelecimento',
            'message' => $e->getMessage()
        ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
});

Route::put('/listas/{lista_id}/estabelecimentos/{id}', function (Request $request, $lista_id, $id) {
    try {
        // Valida se a lista existe
        $lista = DB::table('listas_compras')->where('id', $lista_id)->first();
        if (!$lista) {
            return response()->json(['error' => 'Lista não encontrada'], 404);
        }
        
        // Valida se o estabelecimento existe e pertence à lista
        $estabelecimento = DB::table('estabelecimentos_compra')
            ->where('id', $id)
            ->where('lista_id', $lista_id)
            ->first();
        
        if (!$estabelecimento) {
            return response()->json(['error' => 'Estabelecimento não encontrado'], 404);
        }
        
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'localizacao' => 'nullable|string|max:500',
            'forma_pagamento' => 'nullable|string|max:100',
            'observacoes' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);
        
        $updateData = [
            'nome' => trim($data['nome']),
            'localizacao' => isset($data['localizacao']) && trim($data['localizacao']) !== '' ? trim($data['localizacao']) : null,
            'forma_pagamento' => isset($data['forma_pagamento']) && trim($data['forma_pagamento']) !== '' ? trim($data['forma_pagamento']) : null,
            'observacoes' => isset($data['observacoes']) && trim($data['observacoes']) !== '' ? trim($data['observacoes']) : null,
            'latitude' => isset($data['latitude']) && $data['latitude'] !== null ? floatval($data['latitude']) : null,
            'longitude' => isset($data['longitude']) && $data['longitude'] !== null ? floatval($data['longitude']) : null,
            'atualizado_em' => now(),
        ];
        
        DB::table('estabelecimentos_compra')
            ->where('id', $id)
            ->where('lista_id', $lista_id)
            ->update($updateData);
        
        $estabelecimentoAtualizado = DB::table('estabelecimentos_compra')->where('id', $id)->first();
        
        return response()->json($estabelecimentoAtualizado)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Exception $e) {
        \Log::error('PUT /listas/{lista_id}/estabelecimentos/{id} - Erro: ' . $e->getMessage());
        return response()->json([
            'error' => 'Erro ao atualizar estabelecimento',
            'message' => $e->getMessage()
        ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
});

Route::delete('/listas/{lista_id}/estabelecimentos/{id}', function ($lista_id, $id) {
    try {
        // Valida se o estabelecimento existe e pertence à lista
        $estabelecimento = DB::table('estabelecimentos_compra')
            ->where('id', $id)
            ->where('lista_id', $lista_id)
            ->first();
        
        if (!$estabelecimento) {
            return response()->json(['error' => 'Estabelecimento não encontrado'], 404);
        }
        
        // Verifica se há itens vinculados a este estabelecimento
        $itensVinculados = DB::table('listas_itens')
            ->where('estabelecimento_id', $id)
            ->where('lista_id', $lista_id)
            ->count();
        
        if ($itensVinculados > 0) {
            // Remove o vínculo dos itens antes de deletar
            DB::table('listas_itens')
                ->where('estabelecimento_id', $id)
                ->where('lista_id', $lista_id)
                ->update(['estabelecimento_id' => null]);
        }
        
        DB::table('estabelecimentos_compra')
            ->where('id', $id)
            ->where('lista_id', $lista_id)
            ->delete();
        
        return response()->json(['message' => 'Estabelecimento removido'])
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    } catch (\Exception $e) {
        \Log::error('DELETE /listas/{lista_id}/estabelecimentos/{id} - Erro: ' . $e->getMessage());
        return response()->json([
            'error' => 'Erro ao remover estabelecimento',
            'message' => $e->getMessage()
        ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
});

// CORS preflight para estabelecimentos de lista
Route::options('/listas/{lista_id}/estabelecimentos', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::options('/listas/{lista_id}/estabelecimentos/{id}', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// ============================================
// SUGESTÕES DE COMPRAS BASEADAS EM MOVIMENTAÇÕES
// ============================================

Route::options('/sugestoes-compras', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

Route::get('/sugestoes-compras', function (Request $request) {
    try {
        $unidadeId = $request->has('unidade_id') ? (int)$request->unidade_id : null;
        $diasAnalise = $request->has('dias') ? (int)$request->dias : 30; // Padrão: últimos 30 dias
        $diasProjecao = ($request->has('dias_projecao') && (int)$request->dias_projecao > 0) ? (int)$request->dias_projecao : 0; // Só usa projeção se usuário informar
        
        // Busca todas as saídas (consumo) do período
        $query = DB::table('movimentacoes')
            ->join('produtos', 'movimentacoes.produto_id', '=', 'produtos.id')
            ->where('movimentacoes.tipo', 'SAIDA')
            ->where('movimentacoes.motivo', '!=', 'TRANSFERENCIA') // Exclui transferências
            ->where('movimentacoes.data_mov', '>=', now()->subDays($diasAnalise)->format('Y-m-d H:i:s'))
            ->select(
                'movimentacoes.produto_id',
                'movimentacoes.de_unidade_id',
                'produtos.nome as produto_nome',
                'produtos.unidade_base',
                'produtos.estoque_minimo',
                DB::raw('SUM(movimentacoes.qtd) as total_consumido'),
                DB::raw('COUNT(*) as total_movimentacoes'),
                DB::raw('AVG(movimentacoes.qtd) as media_por_movimentacao')
            )
            ->groupBy('movimentacoes.produto_id', 'movimentacoes.de_unidade_id', 'produtos.nome', 'produtos.unidade_base', 'produtos.estoque_minimo');
        
        // Filtra por unidade se especificada
        if ($unidadeId) {
            $query->where('movimentacoes.de_unidade_id', $unidadeId);
        }
        
        $consumos = $query->get();
        
        // Chave para evitar duplicatas: "produto_id_unidade_id"
        $chavesUsadas = [];
        $sugestoes = [];
        
        foreach ($consumos as $consumo) {
            $produtoId = $consumo->produto_id;
            $unidadeIdConsumo = $consumo->de_unidade_id;
            
            // Busca estoque atual na unidade
            $estoqueAtual = DB::table('stock_lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $unidadeIdConsumo)
                ->sum('quantidade');
            
            // Calcula consumo médio diário e projeção (só se dias_projecao > 0)
            $consumoMedioDiario = $consumo->total_consumido / $diasAnalise;
            $consumoProjetado = $diasProjecao > 0 ? ($consumoMedioDiario * $diasProjecao) : 0;
            
            $estoqueMinimo = $consumo->estoque_minimo ?? 0;
            // Quantidade para completar o mínimo (produtos abaixo do mínimo)
            $quantidadeParaCompletar = max(0, $estoqueMinimo - $estoqueAtual);
            // Sugestão total: completar mínimo + consumo projetado (só se usuário informou dias)
            $quantidadeSugerida = $diasProjecao > 0
                ? (($consumoProjetado + $estoqueMinimo) - $estoqueAtual)
                : $quantidadeParaCompletar;
            
            // Só sugere se a quantidade for positiva e significativa
            if ($quantidadeSugerida > 0 && $quantidadeSugerida >= ($estoqueMinimo * 0.1)) {
                $unidade = DB::table('unidades')->where('id', $unidadeIdConsumo)->first();
                $chave = "{$produtoId}_{$unidadeIdConsumo}";
                $chavesUsadas[$chave] = true;
                
                $sugestoes[] = [
                    'produto_id' => $produtoId,
                    'produto_nome' => $consumo->produto_nome,
                    'unidade_id' => $unidadeIdConsumo,
                    'unidade_nome' => $unidade->nome ?? 'N/A',
                    'unidade_base' => $consumo->unidade_base,
                    'estoque_atual' => round($estoqueAtual, 3),
                    'estoque_minimo' => $estoqueMinimo,
                    'quantidade_para_completar' => round($quantidadeParaCompletar, 3),
                    'consumo_total_periodo' => round($consumo->total_consumido, 3),
                    'consumo_medio_diario' => round($consumoMedioDiario, 3),
                    'consumo_projetado' => round($consumoProjetado, 3),
                    'quantidade_sugerida' => round($quantidadeSugerida, 3),
                    'dias_analise' => $diasAnalise,
                    'dias_projecao' => $diasProjecao,
                    'prioridade' => $estoqueAtual <= $estoqueMinimo ? 'ALTA' : ($estoqueAtual <= ($estoqueMinimo * 1.5) ? 'MEDIA' : 'BAIXA')
                ];
            }
        }
        
        // Inclui produtos abaixo do mínimo que NÃO tiveram consumo no período
        $queryAbaixoMin = DB::table('stock_lotes')
            ->join('produtos', 'stock_lotes.produto_id', '=', 'produtos.id')
            ->where('produtos.estoque_minimo', '>', 0)
            ->where('produtos.ativo', 1)
            ->select(
                'produtos.id as produto_id',
                'produtos.nome as produto_nome',
                'produtos.unidade_base',
                'produtos.estoque_minimo',
                DB::raw('stock_lotes.unidade_id as unidade_id'),
                DB::raw('SUM(stock_lotes.quantidade) as estoque_atual')
            )
            ->groupBy('produtos.id', 'produtos.nome', 'produtos.unidade_base', 'produtos.estoque_minimo', 'stock_lotes.unidade_id')
            ->havingRaw('SUM(stock_lotes.quantidade) < produtos.estoque_minimo');
        
        if ($unidadeId) {
            $queryAbaixoMin->where('stock_lotes.unidade_id', $unidadeId);
        }
        
        $abaixoMinimo = $queryAbaixoMin->get();
        
        foreach ($abaixoMinimo as $item) {
            $chave = "{$item->produto_id}_{$item->unidade_id}";
            if (isset($chavesUsadas[$chave])) continue;
            
            $estoqueMinimo = (float) $item->estoque_minimo;
            $estoqueAtual = (float) $item->estoque_atual;
            $quantidadeParaCompletar = $estoqueMinimo - $estoqueAtual;
            
            $unidade = DB::table('unidades')->where('id', $item->unidade_id)->first();
            
            $sugestoes[] = [
                'produto_id' => $item->produto_id,
                'produto_nome' => $item->produto_nome,
                'unidade_id' => $item->unidade_id,
                'unidade_nome' => $unidade->nome ?? 'N/A',
                'unidade_base' => $item->unidade_base ?? 'UND',
                'estoque_atual' => round($estoqueAtual, 3),
                'estoque_minimo' => $estoqueMinimo,
                'quantidade_para_completar' => round($quantidadeParaCompletar, 3),
                'consumo_total_periodo' => 0,
                'consumo_medio_diario' => 0,
                'consumo_projetado' => 0,
                'quantidade_sugerida' => round($quantidadeParaCompletar, 3),
                'dias_analise' => $diasAnalise,
                'dias_projecao' => $diasProjecao,
                'prioridade' => 'ALTA'
            ];
        }
        
        // Ordena por prioridade e quantidade sugerida
        usort($sugestoes, function($a, $b) {
            $prioridadeOrder = ['ALTA' => 3, 'MEDIA' => 2, 'BAIXA' => 1];
            if ($prioridadeOrder[$a['prioridade']] !== $prioridadeOrder[$b['prioridade']]) {
                return $prioridadeOrder[$b['prioridade']] - $prioridadeOrder[$a['prioridade']];
            }
            return $b['quantidade_sugerida'] <=> $a['quantidade_sugerida'];
        });
        
        return response()->json([
            'total_sugestoes' => count($sugestoes),
            'dias_analise' => $diasAnalise,
            'dias_projecao' => $diasProjecao,
            'unidade_id' => $unidadeId,
            'sugestoes' => $sugestoes
        ])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        
    } catch (\Exception $e) {
        \Log::error('Erro ao buscar sugestões de compras: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao buscar sugestões: ' . $e->getMessage()], 500)
            ->header('Access-Control-Allow-Origin', '*');
    }
});

// ============================================
// FORNECEDORES - CRUD + BACKUP/RESTAURAÇÃO
// ============================================

use App\Http\Controllers\FornecedorController;
use App\Http\Controllers\FornecedorBackupController;

Route::options('/fornecedores', fn () => response()->json([])->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::options('/fornecedores/{id}', fn () => response()->json([])->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'GET, PUT, DELETE, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::options('/fornecedores-backup', fn () => response()->json([])->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'GET, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::options('/fornecedores-backup/{id}', fn () => response()->json([])->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

Route::get('/fornecedores', fn (Request $request) => (new FornecedorController())->index($request)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::get('/fornecedores/{id}/check-historico', fn ($id) => (new FornecedorController())->checkHistorico($id)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::get('/fornecedores/{id}', fn (Request $request, $id) => (new FornecedorController())->show($id)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::post('/fornecedores', fn (Request $request) => (new FornecedorController())->store($request)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::put('/fornecedores/{id}', fn (Request $request, $id) => (new FornecedorController())->update($request, $id)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::put('/fornecedores/{id}/desativar', fn (Request $request, $id) => (new FornecedorController())->desativar($request, $id)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::put('/fornecedores/{id}/ativar', fn (Request $request, $id) => (new FornecedorController())->ativar($request, $id)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::delete('/fornecedores/{id}', fn (Request $request, $id) => (new FornecedorController())->destroy($request, $id)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

Route::get('/fornecedores-backup', fn (Request $request) => (new FornecedorBackupController())->index($request)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::get('/fornecedores-backup/{id}', fn (Request $request, $id) => (new FornecedorBackupController())->show($request, $id)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::post('/fornecedores-backup/{id}/restaurar', fn (Request $request, $id) => (new FornecedorBackupController())->restaurar($request, $id)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::delete('/fornecedores-backup/{id}', fn (Request $request, $id) => (new FornecedorBackupController())->destroy($request, $id)->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

// ============================================
// BOLETOS - CONTROLE FINANCEIRO
// ============================================

use App\Http\Controllers\BoletoController;

// CORS preflight para boletos
Route::options('/boletos', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::options('/boletos/resumo', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::options('/boletos/economia-mensal', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::options('/boletos/{id}', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, PUT, POST, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// Listar boletos
Route::get('/boletos', function (Request $request) {
    $controller = new BoletoController();
    $response = $controller->index($request);
    return $response
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// Criar boleto
Route::post('/boletos', function (Request $request) {
    $controller = new BoletoController();
    $response = $controller->store($request);
    return $response
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// Resumo financeiro
Route::get('/boletos/resumo', function (Request $request) {
    $controller = new BoletoController();
    $response = $controller->resumo($request);
    return $response
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// Economia mensal
Route::get('/boletos/economia-mensal', function (Request $request) {
    $controller = new BoletoController();
    $response = $controller->economiaMensal();
    return $response
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// Buscar boleto específico
Route::get('/boletos/{id}', function (Request $request, $id) {
    $controller = new BoletoController();
    $response = $controller->show($id);
    return $response
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// Atualizar boleto
Route::put('/boletos/{id}', function (Request $request, $id) {
    $controller = new BoletoController();
    $response = $controller->update($request, $id);
    return $response
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, PUT, POST, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// Atualizar boleto com anexo (multipart) — POST evita falhas comuns de PUT + ficheiro no PHP
Route::post('/boletos/{id}', function (Request $request, $id) {
    $controller = new BoletoController();
    $response = $controller->update($request, $id);
    return $response
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, PUT, POST, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// Excluir boleto
Route::delete('/boletos/{id}', function (Request $request, $id) {
    $controller = new BoletoController();
    $response = $controller->destroy($id);
    return $response
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// Download de anexo
Route::get('/boletos/{id}/anexo', function (Request $request, $id) {
    $controller = new BoletoController();
    return $controller->downloadAnexo($id);
});

// Remover anexo
Route::delete('/boletos/{id}/anexo', function (Request $request, $id) {
    $controller = new BoletoController();
    $response = $controller->removerAnexo($id);
    return $response
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

Route::options('/boletos/{id}/anexo', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
});

// ============================================
// ALVARÁS - CONTROLE FINANCEIRO
// ============================================

use App\Http\Controllers\AlvaraController;

Route::options('/alvaras', fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

Route::options('/alvaras/{id}', fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

Route::get('/alvaras', fn (Request $request) => (new AlvaraController())->index($request)
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

Route::post('/alvaras', fn (Request $request) => (new AlvaraController())->store($request)
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

Route::get('/alvaras/{id}', fn (Request $request, $id) => (new AlvaraController())->show($id)
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

Route::put('/alvaras/{id}', fn (Request $request, $id) => (new AlvaraController())->update($request, $id)
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

Route::delete('/alvaras/{id}', fn (Request $request, $id) => (new AlvaraController())->destroy($id)
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

// Anexo: CORS aplicado dentro de AlvaraController::downloadAnexo (aplicarCorsRespostaAnexo).
// Não re-adicionar ->header() aqui em cima de BinaryFileResponse — ver docblock no controller.
Route::get('/alvaras/{id}/anexo', fn (Request $request, $id) => (new AlvaraController())->downloadAnexo($request, $id));

Route::delete('/alvaras/{id}/anexo', fn (Request $request, $id) => (new AlvaraController())->removerAnexo($id)
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

Route::options('/alvaras/{id}/anexo', fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform'));

// ============================================
// RESERVAS DE MESAS
// ============================================

use App\Http\Controllers\MesaController;
use App\Http\Controllers\ReservaMesaController;

$cors = ['Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS', 'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Usuario-Id'];

Route::get('/mesas', fn (Request $r) => (new MesaController())->index($r)->withHeaders($cors));
Route::post('/mesas', fn (Request $r) => (new MesaController())->store($r)->withHeaders($cors));
Route::get('/mesas/{id}', fn (Request $r, $id) => (new MesaController())->show($id)->withHeaders($cors));
Route::put('/mesas/{id}', fn (Request $r, $id) => (new MesaController())->update($r, $id)->withHeaders($cors));
Route::delete('/mesas/{id}', fn (Request $r, $id) => (new MesaController())->destroy($id)->withHeaders($cors));

Route::get('/reservas-mesas', fn (Request $r) => (new ReservaMesaController())->index($r)->withHeaders($cors));
Route::get('/reservas-mesas/resumo', fn (Request $r) => (new ReservaMesaController())->resumo($r)->withHeaders($cors));
Route::get('/reservas-mesas/historico', fn (Request $r) => (new ReservaMesaController())->historico($r)->withHeaders($cors));
Route::post('/reservas-mesas', fn (Request $r) => (new ReservaMesaController())->store($r)->withHeaders($cors));
Route::get('/reservas-mesas/{id}', fn (Request $r, $id) => (new ReservaMesaController())->show($id)->withHeaders($cors));
Route::put('/reservas-mesas/{id}', fn (Request $r, $id) => (new ReservaMesaController())->update($r, $id)->withHeaders($cors));
Route::post('/reservas-mesas/{id}/cancelar', fn (Request $r, $id) => (new ReservaMesaController())->cancelar($id)->withHeaders($cors));
Route::patch('/reservas-mesas/{id}/status', fn (Request $r, $id) => (new ReservaMesaController())->alterarStatus($r, $id)->withHeaders($cors));

// ============================================
// ROTA TEMPORÁRIA — ZERAR HISTÓRICOS DE TESTE
// Remover após uso
// ============================================
Route::post('/admin/zerar-historicos', function (Request $request) {
    $userId = $request->header('X-Usuario-Id');
    $usuario = $userId ? DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first() : null;
    if (! $usuario || strtoupper((string) ($usuario->perfil ?? '')) !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem executar esta ação.'], 403)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $chave = $request->input('chave');
    if ($chave !== 'ZERAR-SABORPARAENSE-2026') {
        return response()->json(['error' => 'Chave inválida.'], 403);
    }

    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    DB::table('movimentacoes')->truncate();
    DB::table('stock_lotes')->truncate();
    DB::table('lotes')->truncate();
    DB::table('listas_itens')->truncate();
    DB::table('listas_compras')->truncate();
    DB::table('logs_etiquetas')->truncate();
    DB::table('logs_usuarios')->truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS = 1');

    \Log::warning('ADMIN zerar-historicos executado', [
        'usuario_id' => (int) $userId,
        'ip' => $request->ip(),
        'ua' => (string) $request->userAgent(),
    ]);

    return response()->json([
        'sucesso' => true,
        'mensagem' => 'Históricos zerados com sucesso.',
        'tabelas_zeradas' => [
            'movimentacoes'  => DB::table('movimentacoes')->count(),
            'stock_lotes'    => DB::table('stock_lotes')->count(),
            'lotes'          => DB::table('lotes')->count(),
            'listas_itens'   => DB::table('listas_itens')->count(),
            'listas_compras' => DB::table('listas_compras')->count(),
            'logs_etiquetas' => DB::table('logs_etiquetas')->count(),
            'logs_usuarios'  => DB::table('logs_usuarios')->count(),
        ],
        'preservados' => [
            'produtos'  => DB::table('produtos')->count(),
            'unidades'  => DB::table('unidades')->count(),
            'locais'    => DB::table('locais')->count(),
            'usuarios'  => DB::table('usuarios')->count(),
        ],
    ]);
});

// ============================================
// ROTAS DE BACKUP E RESTAURAÇÃO
// ============================================

// Gerar backup completo
Route::post('/admin/backup', function (Request $request) {
    $userId = $request->header('X-Usuario-Id');
    $usuario = $userId ? DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first() : null;
    if (! $usuario || strtoupper((string) ($usuario->perfil ?? '')) !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem gerar backup.'], 403)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $chave = $request->input('chave');
    if ($chave !== 'BACKUP-SABORPARAENSE-2026') {
        return response()->json(['error' => 'Chave inválida.'], 403);
    }

    try {
        $snapshot = [
            'versao'     => '1.2',
            'gerado_em'  => now()->toIso8601String(),
            'tabelas'    => [
                'produtos'              => DB::table('produtos')->get()->toArray(),
                'unidades'              => DB::table('unidades')->get()->toArray(),
                'locais'                => DB::table('locais')->get()->toArray(),
                'usuarios'              => DB::table('usuarios')->get()->toArray(),
                // RH (funcionários)
                'funcionarios'          => Schema::hasTable('funcionarios') ? DB::table('funcionarios')->get()->toArray() : [],
                'financeiro_vale_consumo' => Schema::hasTable('financeiro_vale_consumo') ? DB::table('financeiro_vale_consumo')->get()->toArray() : [],
                // Recrutamento (vagas + candidatos e vínculos; usado em backup/restore e merge)
                'rh_vagas'              => Schema::hasTable('rh_vagas') ? DB::table('rh_vagas')->get()->toArray() : [],
                'rh_candidatos'         => Schema::hasTable('rh_candidatos') ? DB::table('rh_candidatos')->get()->toArray() : [],
                'rh_curriculos'         => Schema::hasTable('rh_curriculos') ? DB::table('rh_curriculos')->get()->toArray() : [],
                'rh_entrevistas'        => Schema::hasTable('rh_entrevistas') ? DB::table('rh_entrevistas')->get()->toArray() : [],
                'rh_documentos'         => Schema::hasTable('rh_documentos') ? DB::table('rh_documentos')->get()->toArray() : [],
                'rh_historico'          => Schema::hasTable('rh_historico') ? DB::table('rh_historico')->get()->toArray() : [],
                'lotes'                 => DB::table('lotes')->get()->toArray(),
                'stock_lotes'           => DB::table('stock_lotes')->get()->toArray(),
                'movimentacoes'         => DB::table('movimentacoes')->get()->toArray(),
                'listas_compras'        => DB::table('listas_compras')->get()->toArray(),
                'listas_itens'          => DB::table('listas_itens')->get()->toArray(),
                'boletos'               => DB::table('boletos')->get()->toArray(),
                'estabelecimentos_compra' => DB::table('estabelecimentos_compra')->get()->toArray(),
            ],
        ];

        $dir = storage_path('app/backups');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $nomeArquivo = 'backup_' . now()->format('Y-m-d_H-i-s') . '.json';
        $caminho = $dir . '/' . $nomeArquivo;
        file_put_contents($caminho, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        \Log::info('ADMIN backup gerado', [
            'usuario_id' => (int) $userId,
            'ip' => $request->ip(),
            'arquivo' => $nomeArquivo,
            'totais' => array_map(fn($t) => count((array)$t), $snapshot['tabelas']),
        ]);

        return response()->json([
            'sucesso'      => true,
            'arquivo'      => $nomeArquivo,
            'gerado_em'    => $snapshot['gerado_em'],
            'tamanho_kb'   => round(filesize($caminho) / 1024, 1),
            'totais'       => array_map(fn($t) => count((array)$t), $snapshot['tabelas']),
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Erro ao gerar backup: ' . $e->getMessage()], 500);
    }
});

// Listar backups disponíveis
Route::get('/admin/backups', function (Request $request) {
    $chave = $request->query('chave');
    if ($chave !== 'BACKUP-SABORPARAENSE-2026') {
        return response()->json(['error' => 'Chave inválida.'], 403);
    }

    $dir = storage_path('app/backups');
    if (!is_dir($dir)) return response()->json([]);

    $arquivos = glob($dir . '/backup_*.json');
    usort($arquivos, fn($a, $b) => filemtime($b) - filemtime($a));

    $lista = array_map(function($caminho) {
        $nome = basename($caminho);
        $conteudo = json_decode(file_get_contents($caminho), true);
        $totais = [];
        if (isset($conteudo['tabelas'])) {
            foreach ($conteudo['tabelas'] as $tabela => $dados) {
                $totais[$tabela] = count($dados);
            }
        }
        return [
            'arquivo'    => $nome,
            'gerado_em'  => $conteudo['gerado_em'] ?? null,
            'tamanho_kb' => round(filesize($caminho) / 1024, 1),
            'totais'     => $totais,
        ];
    }, $arquivos);

    return response()->json($lista);
});

// Excluir backup (ADMIN): POST body JSON { chave, arquivo } — não use ".json" na URL (Apache trata como arquivo estático).
$executarExclusaoBackupJson = static function (Request $request, string $arquivo) {
    $arquivo = trim($arquivo);
    $userId = $request->header('X-Usuario-Id');
    $usuario = $userId ? DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first() : null;
    if (! $usuario || strtoupper((string) ($usuario->perfil ?? '')) !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem excluir backups.'], 403)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $chave = $request->query('chave') ?? $request->input('chave');
    if ($chave !== 'BACKUP-SABORPARAENSE-2026') {
        return response()->json(['error' => 'Chave inválida.'], 403)
            ->header('Access-Control-Allow-Origin', '*');
    }
    if (! preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json$/', $arquivo)) {
        return response()->json(['error' => 'Arquivo inválido.'], 400)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $caminho = storage_path('app/backups/' . $arquivo);
    if (! file_exists($caminho)) {
        return response()->json(['error' => 'Backup não encontrado.'], 404)
            ->header('Access-Control-Allow-Origin', '*');
    }
    if (! @unlink($caminho)) {
        return response()->json(['error' => 'Não foi possível excluir (permissão na pasta storage/app/backups).'], 500)
            ->header('Access-Control-Allow-Origin', '*');
    }
    \Log::info('ADMIN backup excluído', [
        'usuario_id' => (int) $userId,
        'ip' => $request->ip(),
        'arquivo' => $arquivo,
    ]);

    return response()->json([
        'sucesso' => true,
        'mensagem' => 'Backup removido.',
    ])->header('Access-Control-Allow-Origin', '*');
};
Route::post('/admin/backups/excluir', function (Request $request) use ($executarExclusaoBackupJson) {
    $nome = trim((string) $request->input('arquivo', ''));
    if ($nome === '') {
        return response()->json(['error' => 'Informe o nome do arquivo (arquivo).'], 422)
            ->header('Access-Control-Allow-Origin', '*');
    }

    return $executarExclusaoBackupJson($request, $nome);
});
Route::options('/admin/backups/excluir', fn() => response('', 204)
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

// Prévia do impacto do restore (apenas ADMIN)
Route::get('/admin/backups/{arquivo}/preview', function (Request $request, $arquivo) {
    $userId = $request->header('X-Usuario-Id');
    $usuario = $userId ? DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first() : null;
    if (! $usuario || strtoupper((string) ($usuario->perfil ?? '')) !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem visualizar esta prévia.'], 403)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $chave = $request->query('chave');
    if ($chave !== 'BACKUP-SABORPARAENSE-2026') {
        return response()->json(['error' => 'Chave inválida.'], 403);
    }
    if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json$/', $arquivo)) {
        return response()->json(['error' => 'Arquivo inválido.'], 400)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $caminho = storage_path('app/backups/' . $arquivo);
    if (!file_exists($caminho)) {
        return response()->json(['error' => 'Backup não encontrado.'], 404)
            ->header('Access-Control-Allow-Origin', '*');
    }

    $snapshot = json_decode(file_get_contents($caminho), true);
    if (!isset($snapshot['tabelas']) || !is_array($snapshot['tabelas'])) {
        return response()->json(['error' => 'Arquivo de backup corrompido.'], 400)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $totaisArquivo = [];
    foreach ($snapshot['tabelas'] as $tabela => $dados) {
        $totaisArquivo[$tabela] = is_array($dados) ? count($dados) : 0;
    }

    $tabelasCriticas = ['usuarios', 'funcionarios', 'produtos', 'unidades', 'locais'];
    $atuais = [];
    foreach ($tabelasCriticas as $t) {
        if (!Schema::hasTable($t)) {
            $atuais[$t] = null;
            continue;
        }
        $atuais[$t] = (int) DB::table($t)->count();
    }

    return response()->json([
        'arquivo' => $arquivo,
        'gerado_em' => $snapshot['gerado_em'] ?? null,
        'versao' => $snapshot['versao'] ?? null,
        'totais_arquivo' => $totaisArquivo,
        'totais_atuais' => $atuais,
        'alertas' => [
            'zeraria_funcionarios' => ($atuais['funcionarios'] ?? 0) > 0 && (($totaisArquivo['funcionarios'] ?? 0) === 0),
            'zeraria_usuarios' => ($atuais['usuarios'] ?? 0) > 0 && (($totaisArquivo['usuarios'] ?? 0) === 0),
        ],
    ])->header('Access-Control-Allow-Origin', '*');
});

// Download de um backup
Route::get('/admin/backup/{arquivo}', function (Request $request, $arquivo) {
    $chave = $request->query('chave');
    if ($chave !== 'BACKUP-SABORPARAENSE-2026') {
        return response()->json(['error' => 'Chave inválida.'], 403);
    }

    // Segurança: só permite nomes no formato backup_YYYY-MM-DD_HH-II-SS.json
    if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json$/', $arquivo)) {
        return response()->json(['error' => 'Arquivo inválido.'], 400);
    }

    $caminho = storage_path('app/backups/' . $arquivo);
    if (!file_exists($caminho)) {
        return response()->json(['error' => 'Backup não encontrado.'], 404);
    }

    return response()->download($caminho, $arquivo, ['Content-Type' => 'application/json']);
});

// Rotas legadas (fallback): DELETE ou POST com nome na URL — pode falhar no Apache por causa de ".json" no path.
Route::delete('/admin/backups/{arquivo}', function (Request $request, string $arquivo) use ($executarExclusaoBackupJson) {
    return $executarExclusaoBackupJson($request, $arquivo);
});
Route::post('/admin/backups/{arquivo}/excluir', function (Request $request, string $arquivo) use ($executarExclusaoBackupJson) {
    return $executarExclusaoBackupJson($request, $arquivo);
});
Route::options('/admin/backups/{arquivo}/excluir', fn() => response('', 204)
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

// Restaurar a partir de um backup
Route::post('/admin/restaurar', function (Request $request) {
    $userId = $request->header('X-Usuario-Id');
    $usuario = $userId ? DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first() : null;
    if (! $usuario || strtoupper((string) ($usuario->perfil ?? '')) !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem restaurar backups.'], 403)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $chave = $request->input('chave');
    if ($chave !== 'BACKUP-SABORPARAENSE-2026') {
        return response()->json(['error' => 'Chave inválida.'], 403);
    }

    $arquivo = $request->input('arquivo');
    if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json$/', $arquivo)) {
        return response()->json(['error' => 'Arquivo inválido.'], 400);
    }

    $caminho = storage_path('app/backups/' . $arquivo);
    if (!file_exists($caminho)) {
        return response()->json(['error' => 'Backup não encontrado.'], 404);
    }

    try {
        $antes = [
            'funcionarios' => Schema::hasTable('funcionarios') ? (int) DB::table('funcionarios')->count() : null,
            'usuarios' => Schema::hasTable('usuarios') ? (int) DB::table('usuarios')->count() : null,
            'produtos' => Schema::hasTable('produtos') ? (int) DB::table('produtos')->count() : null,
        ];
        $snapshot = json_decode(file_get_contents($caminho), true);
        if (!isset($snapshot['tabelas'])) {
            return response()->json(['error' => 'Arquivo de backup corrompido.'], 400);
        }

        // Ordem respeitando dependências (recrutamento: vagas antes de candidatos; filhos após candidatos)
        $ordem = [
            'unidades', 'locais', 'usuarios', 'funcionarios', 'financeiro_vale_consumo', 'produtos',
            'lotes', 'stock_lotes', 'movimentacoes',
            'listas_compras', 'listas_itens',
            'boletos', 'estabelecimentos_compra',
            'rh_vagas', 'rh_candidatos', 'rh_curriculos', 'rh_entrevistas', 'rh_documentos', 'rh_historico',
        ];

        $restaurados = [];

        DB::beginTransaction();
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($ordem as $tabela) {
                if (! isset($snapshot['tabelas'][$tabela]) || ! Schema::hasTable($tabela)) {
                    continue;
                }
                $registrosBrutos = $snapshot['tabelas'][$tabela];
                if (! is_array($registrosBrutos)) {
                    continue;
                }
                $mapaColunas = array_flip(Schema::getColumnListing($tabela));
                $filtrados = [];
                foreach ($registrosBrutos as $r) {
                    $linha = (array) $r;
                    $linha = array_intersect_key($linha, $mapaColunas);
                    if ($linha !== []) {
                        $filtrados[] = $linha;
                    }
                }

                DB::table($tabela)->delete();
                foreach (array_chunk($filtrados, 200) as $lote) {
                    if ($lote !== []) {
                        DB::table($tabela)->insert($lote);
                    }
                }
                $restaurados[$tabela] = count($filtrados);
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            throw $e;
        }

        \Log::warning('ADMIN restore executado', [
            'usuario_id' => (int) $userId,
            'ip' => $request->ip(),
            'arquivo' => $arquivo,
            'antes' => $antes,
            'restaurados' => $restaurados,
        ]);

        return response()->json([
            'sucesso'     => true,
            'mensagem'    => 'Backup restaurado com sucesso.',
            'arquivo'     => $arquivo,
            'restaurados' => $restaurados,
        ])->header('Access-Control-Allow-Origin', '*');
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Erro ao restaurar: ' . $e->getMessage()], 500)
            ->header('Access-Control-Allow-Origin', '*');
    }
});

// Reintegrar só registros de RH (recrutamento) que faltam no banco, a partir de um backup JSON (merge por id).
// Útil para trazer de volta candidatos apagados se o arquivo de backup for anterior à exclusão.
// Requer que o JSON contenha as chaves rh_* (backups gerados com versão >= 1.2).
Route::post('/admin/restaurar-rh-merge', function (Request $request) {
    $userId = $request->header('X-Usuario-Id');
    $usuario = $userId ? DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first() : null;
    if (! $usuario || strtoupper((string) ($usuario->perfil ?? '')) !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem executar merge de RH.'], 403)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $chave = $request->input('chave');
    if ($chave !== 'BACKUP-SABORPARAENSE-2026') {
        return response()->json(['error' => 'Chave inválida.'], 403)
            ->header('Access-Control-Allow-Origin', '*');
    }

    $arquivo = $request->input('arquivo');
    if (! preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json$/', (string) $arquivo)) {
        return response()->json(['error' => 'Arquivo inválido.'], 400)
            ->header('Access-Control-Allow-Origin', '*');
    }

    $caminho = storage_path('app/backups/' . $arquivo);
    if (! file_exists($caminho)) {
        return response()->json(['error' => 'Backup não encontrado.'], 404)
            ->header('Access-Control-Allow-Origin', '*');
    }

    try {
        $snapshot = json_decode(file_get_contents($caminho), true);
        if (! isset($snapshot['tabelas']) || ! is_array($snapshot['tabelas'])) {
            return response()->json(['error' => 'Arquivo de backup corrompido.'], 400)
                ->header('Access-Control-Allow-Origin', '*');
        }

        $temRh = isset($snapshot['tabelas']['rh_candidatos']) || isset($snapshot['tabelas']['rh_vagas']);
        if (! $temRh) {
            return response()->json([
                'error' => 'Este arquivo não contém dados de recrutamento (rh_*). Gere um backup novo (versão 1.2+) ou use um dump MySQL do provedor.',
                'inseridos' => [],
            ], 422)->header('Access-Control-Allow-Origin', '*');
        }

        $inseridos = RhRecruitmentMergeController::mergeFromSnapshot($snapshot);

        \Log::warning('ADMIN restaurar-rh-merge executado', [
            'usuario_id' => (int) $userId,
            'ip' => $request->ip(),
            'arquivo' => $arquivo,
            'inseridos' => $inseridos,
        ]);

        return response()->json([
            'sucesso' => true,
            'mensagem' => 'Merge concluído: foram inseridas apenas linhas cujo id ainda não existia.',
            'arquivo' => $arquivo,
            'inseridos' => $inseridos,
        ])->header('Access-Control-Allow-Origin', '*');
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Erro no merge: ' . $e->getMessage()], 500)
            ->header('Access-Control-Allow-Origin', '*');
    }
});

Route::options('/admin/restaurar-rh-merge', fn () => response('', 204)
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

Route::options('/admin/backups/{arquivo}', fn() => response('', 204)
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

// ============================================
// FUNCIONÁRIOS (Módulo RH)
// ============================================

Route::options('/funcionarios', fn() => response()->json([])->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::options('/funcionarios/{id}', fn() => response()->json([])->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'GET, PUT, POST, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::options('/funcionarios/{id}/atualizar', fn() => response()->json([])->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'POST, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::options('/funcionarios/rh-diagnostico', fn() => response()->json([])->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'GET, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::options('/funcionarios/relatorio/contatos.pdf', fn() => response('', 200)
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));
Route::options('/funcionarios/{id}/excluir', fn() => response()->json([])->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'DELETE, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id'));

/** Diagnóstico RH: lista colunas reais da tabela (precisa estar logado). Deve vir ANTES de /funcionarios/{id}. */
Route::get('/funcionarios/rh-diagnostico', function (Request $request) {
    $userId = $request->header('X-Usuario-Id');
    if (! $userId || ! DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first()) {
        return response()->json(['error' => 'Não autorizado'], 401)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $out = [
        // Ajuda a detectar "tô olhando outro banco / outra API"
        'db_driver' => null,
        'db_name' => null,
        'funcionarios_total' => null,
        'funcionarios_ultimo_created_at' => null,
        'tabela_funcionarios_existe' => Schema::hasTable('funcionarios'),
        'colunas' => [],
        'tem_escolaridade' => false,
        'tem_formacao_json' => false,
        'tem_banco' => false,
        'tem_agencia' => false,
        'tem_conta' => false,
        'tem_conta_digito' => false,
        'tem_pix' => false,
        'acao_se_faltar_colunas' => 'No servidor, na pasta backend: php artisan migrate --force',
    ];
    if (! $out['tabela_funcionarios_existe']) {
        return response()->json($out)->header('Access-Control-Allow-Origin', '*');
    }
    try {
        $driver = Schema::getConnection()->getDriverName();
        $out['db_driver'] = $driver;
        if ($driver === 'mysql') {
            $dbRow = DB::selectOne('SELECT DATABASE() AS db');
            $out['db_name'] = is_object($dbRow) ? ($dbRow->db ?? null) : ($dbRow['db'] ?? null);
        }
        $out['funcionarios_total'] = (int) (DB::table('funcionarios')->count());
        $out['funcionarios_ultimo_created_at'] = DB::table('funcionarios')->max('created_at');

        if ($driver === 'mysql') {
            foreach (DB::select('SHOW COLUMNS FROM funcionarios') as $row) {
                $f = is_object($row)
                    ? (string) ($row->Field ?? $row->field ?? '')
                    : (string) ($row['Field'] ?? $row['field'] ?? '');
                if ($f !== '') {
                    $out['colunas'][] = $f;
                }
            }
        } else {
            $out['colunas'] = Schema::getColumnListing('funcionarios');
        }
    } catch (\Throwable $e) {
        $out['erro_listar_colunas'] = $e->getMessage();
    }
    $lower = array_map('strtolower', $out['colunas']);
    $out['tem_escolaridade'] = in_array('escolaridade', $lower, true);
    $out['tem_formacao_json'] = in_array('formacao_json', $lower, true);
    $out['tem_banco'] = in_array('banco', $lower, true);
    $out['tem_agencia'] = in_array('agencia', $lower, true);
    $out['tem_conta'] = in_array('conta', $lower, true);
    $out['tem_conta_digito'] = in_array('conta_digito', $lower, true);
    $out['tem_pix'] = in_array('pix', $lower, true);

    return response()->json($out)->header('Access-Control-Allow-Origin', '*');
});

/** Relatório PDF: nome, WhatsApp, unidade e função (cargo). Respeita os mesmos filtros de GET /funcionarios. */
Route::get('/funcionarios/relatorio/contatos.pdf', function (Request $request) {
    $userId = $request->header('X-Usuario-Id');
    if (! $userId || ! DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first()) {
        return response()->json(['error' => 'Não autorizado'], 401)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }

    if (! Schema::hasTable('funcionarios')) {
        return response()->json(['error' => 'Módulo de funcionários indisponível'], 404)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }

    try {
        $query = DB::table('funcionarios')
            ->leftJoin('unidades', 'funcionarios.unidade_id', '=', 'unidades.id')
            ->select(
                'funcionarios.nome_completo',
                'funcionarios.whatsapp',
                'funcionarios.cargo',
                'unidades.nome as unidade_nome',
                'funcionarios.status'
            );

        if ($nome = trim($request->query('nome', ''))) {
            $query->where('funcionarios.nome_completo', 'like', '%' . $nome . '%');
        }
        if ($cpf = preg_replace('/\D/', '', trim($request->query('cpf', '')))) {
            $query->whereRaw('REPLACE(REPLACE(REPLACE(funcionarios.cpf, ".", ""), "-", ""), " ", "") LIKE ?', ['%' . $cpf . '%']);
        }
        if ($cargo = trim($request->query('cargo', ''))) {
            $query->where('funcionarios.cargo', $cargo);
        }
        if ($unidadeId = $request->query('unidade_id')) {
            $query->where('funcionarios.unidade_id', $unidadeId);
        }
        if (in_array($request->query('status'), ['ativo', 'inativo'], true)) {
            $query->where('funcionarios.status', $request->query('status'));
        }

        $funcionarios = $query->orderBy('funcionarios.nome_completo')->get();

        $h = static fn (?string $s): string => htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $rowsHtml = '';
        foreach ($funcionarios as $f) {
            $nome = $h($f->nome_completo ?? '');
            $wa = $h(trim((string) ($f->whatsapp ?? '')) !== '' ? (string) $f->whatsapp : '—');
            $uni = $h(trim((string) ($f->unidade_nome ?? '')) !== '' ? (string) $f->unidade_nome : '—');
            $cargo = $h(trim((string) ($f->cargo ?? '')) !== '' ? (string) $f->cargo : '—');
            $st = ($f->status ?? '') === 'inativo' ? ' <span style="color:#757575;font-size:8pt;">(inativo)</span>' : '';
            $rowsHtml .= '<tr><td>' . $nome . $st . '</td><td>' . $wa . '</td><td>' . $uni . '</td><td>' . $cargo . '</td></tr>';
        }
        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="4" style="text-align:center;color:#607d8b;padding:12px;">Nenhum funcionário encontrado com os filtros atuais.</td></tr>';
        }

        $emitido = now()->format('d/m/Y H:i');
        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8" />
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #212121; }
h1 { font-size: 15pt; margin: 0 0 4px 0; color: #0d47a1; }
.meta { font-size: 9pt; color: #616161; margin-bottom: 14px; }
table { width: 100%; border-collapse: collapse; }
th { background: #1565c0; color: #fff; text-align: left; padding: 8px 6px; font-size: 9pt; }
td { border-bottom: 1px solid #e0e0e0; padding: 7px 6px; vertical-align: top; }
tr:nth-child(even) td { background: #fafafa; }
</style></head><body>
<h1>Relatório de funcionários — contato</h1>
<p class="meta">Colunas: nome, WhatsApp, unidade e função (cargo). Emitido em ' . $h($emitido) . '.</p>
<table><thead><tr>
<th>Nome</th><th>WhatsApp</th><th>Unidade</th><th>Função</th>
</tr></thead><tbody>' . $rowsHtml . '</tbody></table>
</body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfOutput = $dompdf->output();

        return response($pdfOutput, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="funcionarios-contatos.pdf"')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id')
            ->header('Content-Length', (string) strlen($pdfOutput));
    } catch (\Throwable $e) {
        \Log::error('GET /funcionarios/relatorio/contatos.pdf: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao gerar PDF: ' . $e->getMessage()], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
});

Route::get('/funcionarios', function (Request $request) {
    try {
        if (!Schema::hasTable('funcionarios')) {
            return response()->json([])->header('Access-Control-Allow-Origin', '*');
        }
    $query = DB::table('funcionarios')
        ->leftJoin('unidades', 'funcionarios.unidade_id', '=', 'unidades.id')
        ->leftJoin('usuarios', 'funcionarios.usuario_id', '=', 'usuarios.id')
        ->select(
            'funcionarios.*',
            'unidades.nome as unidade_nome',
            'usuarios.nome as usuario_nome',
            'usuarios.email as usuario_email'
        );

    if ($nome = trim($request->query('nome', ''))) {
        $query->where('funcionarios.nome_completo', 'like', '%' . $nome . '%');
    }
    if ($cpf = preg_replace('/\D/', '', trim($request->query('cpf', '')))) {
        $query->whereRaw('REPLACE(REPLACE(REPLACE(funcionarios.cpf, ".", ""), "-", ""), " ", "") LIKE ?', ['%' . $cpf . '%']);
    }
    if ($cargo = trim($request->query('cargo', ''))) {
        $query->where('funcionarios.cargo', $cargo);
    }
    if ($unidadeId = $request->query('unidade_id')) {
        $query->where('funcionarios.unidade_id', $unidadeId);
    }
    if (in_array($request->query('status'), ['ativo', 'inativo'])) {
        $query->where('funcionarios.status', $request->query('status'));
    }

    $funcionarios = $query->orderBy('funcionarios.nome_completo')->get();
    return response()->json($funcionarios)
        ->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /funcionarios: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao listar funcionários'], 500)
            ->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/funcionarios/{id}', function ($id) {
    $f = DB::table('funcionarios')
        ->leftJoin('unidades', 'funcionarios.unidade_id', '=', 'unidades.id')
        ->leftJoin('usuarios', 'funcionarios.usuario_id', '=', 'usuarios.id')
        ->select('funcionarios.*', 'unidades.nome as unidade_nome', 'usuarios.nome as usuario_nome', 'usuarios.email as usuario_email', 'usuarios.perfil as perfil_usuario')
        ->where('funcionarios.id', $id)
        ->first();
    if (!$f) {
        return response()->json(['error' => 'Funcionário não encontrado'], 404)
            ->header('Access-Control-Allow-Origin', '*');
    }
    return response()->json($f)->header('Access-Control-Allow-Origin', '*');
});

/**
 * Colunas reais da tabela `funcionarios` (escolaridade, formacao_json, banco…).
 * Usa SHOW COLUMNS no MySQL + cache só no $GLOBALS da requisição atual (cada HTTP limpa o GLOBALS),
 * evitando Schema do Laravel desatualizado e cache estático entre requests no PHP-FPM.
 */
$funcionariosTableHasColumn = static function (string $column): bool {
    if (! Schema::hasTable('funcionarios')) {
        return false;
    }
    $g = '__rh_funcionarios_colset';
    if (! isset($GLOBALS[$g]) || ! is_array($GLOBALS[$g])) {
        $GLOBALS[$g] = [];
        try {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                foreach (DB::select('SHOW COLUMNS FROM funcionarios') as $row) {
                    $f = is_object($row)
                        ? ($row->Field ?? $row->field ?? '')
                        : ($row['Field'] ?? $row['field'] ?? '');
                    if ($f !== '') {
                        $GLOBALS[$g][strtolower((string) $f)] = true;
                    }
                }
            } else {
                foreach (Schema::getColumnListing('funcionarios') as $f) {
                    $GLOBALS[$g][strtolower((string) $f)] = true;
                }
            }
        } catch (\Throwable $e) {
            $GLOBALS[$g] = [];
            foreach (Schema::getColumnListing('funcionarios') as $f) {
                $GLOBALS[$g][strtolower((string) $f)] = true;
            }
        }
        // MySQL: se SHOW COLUMNS veio vazio (permissão/driver), não deixar colset vazio — senão RH nunca grava.
        if ($GLOBALS[$g] === [] && Schema::hasTable('funcionarios')) {
            try {
                foreach (Schema::getColumnListing('funcionarios') as $f) {
                    $GLOBALS[$g][strtolower((string) $f)] = true;
                }
            } catch (\Throwable $e) {
                // mantém vazio
            }
        }
    }

    return isset($GLOBALS[$g][strtolower($column)]);
};

$normalizeFuncionarioFormacaoJson = static function ($requestData) {
    $raw = $requestData['formacao_json'] ?? null;
    if ($raw === null || $raw === '') {
        return null;
    }
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }
    } elseif (is_array($raw)) {
        $decoded = $raw;
    } else {
        return null;
    }
    $allowedKeys = ['curso_complementar', 'tecnico', 'graduacao', 'pos_graduacao'];
    $clean = [];
    $normalizeItem = static function (array $b) {
        $curso = isset($b['curso']) ? trim((string) $b['curso']) : '';
        $inst = isset($b['instituicao']) ? trim((string) $b['instituicao']) : '';
        $local = isset($b['local']) ? trim((string) $b['local']) : '';
        $di = isset($b['data_inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $b['data_inicio']) ? $b['data_inicio'] : null;
        $df = isset($b['data_conclusao']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $b['data_conclusao']) ? $b['data_conclusao'] : null;
        $emAnd = ! empty($b['em_andamento']);
        if ($curso === '' && $inst === '' && $local === '' && ! $di && ! $df && ! $emAnd) {
            return null;
        }

        return [
            'curso' => mb_substr($curso, 0, 255),
            'instituicao' => mb_substr($inst, 0, 255),
            'local' => mb_substr($local, 0, 500),
            'data_inicio' => $di,
            'data_conclusao' => $emAnd ? null : $df,
            'em_andamento' => $emAnd,
        ];
    };
    foreach ($allowedKeys as $k) {
        if (empty($decoded[$k])) {
            continue;
        }
        $rawBlock = $decoded[$k];
        $items = [];
        if (is_array($rawBlock) && array_is_list($rawBlock)) {
            $items = $rawBlock;
        } elseif (is_array($rawBlock)) {
            // Legado: um único objeto por chave
            $items = [$rawBlock];
        } else {
            continue;
        }
        $list = [];
        foreach ($items as $b) {
            if (! is_array($b)) {
                continue;
            }
            $one = $normalizeItem($b);
            if ($one !== null) {
                $list[] = $one;
            }
        }
        if ($list !== []) {
            $clean[$k] = $list;
        }
    }

    return empty($clean) ? null : json_encode($clean, JSON_UNESCAPED_UNICODE);
};

Route::post('/funcionarios', function (Request $request) use ($normalizeFuncionarioFormacaoJson, $funcionariosTableHasColumn) {
    try {
    if (!Schema::hasTable('funcionarios')) {
        return response()->json(['error' => 'Módulo RH não configurado. Execute: php artisan migrate'], 503)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $userId = $request->header('X-Usuario-Id');
    $usuarioLogado = $userId ? DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first() : null;
    if (!$usuarioLogado) {
        return response()->json(['error' => 'Faça login novamente. Sessão expirada ou usuário não identificado.'], 401)
            ->header('Access-Control-Allow-Origin', '*');
    }

    $data = $request->all();
    $cpfLimpo = preg_replace('/\D/', '', $data['cpf'] ?? '');
    if (strlen($cpfLimpo) !== 11) {
        return response()->json(['error' => 'CPF inválido'], 422)->header('Access-Control-Allow-Origin', '*');
    }
    $cpfFormatado = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfLimpo);

    if (DB::table('funcionarios')->where('cpf', $cpfFormatado)->exists()) {
        return response()->json(['error' => 'CPF já cadastrado'], 422)->header('Access-Control-Allow-Origin', '*');
    }

    $possuiAcesso = !empty($data['possui_acesso']) && !in_array($data['possui_acesso'], [false, 'false', '0', 0, ''], true);
    $usuarioIdFornecido = !empty($data['usuario_id']) ? (int)$data['usuario_id'] : null;
    $rules = [
        'nome_completo' => 'required|string|max:255',
        'cargo' => 'required|string|max:255',
        'status' => 'required|in:ativo,inativo',
    ];
    if ($possuiAcesso && !$usuarioIdFornecido) {
        $rules['login_usuario'] = 'required|string|max:255';
        $rules['senha_usuario'] = 'required|string|min:6';
        $rules['perfil_usuario'] = 'required|string|in:ADMIN,GERENTE,FINANCEIRO,ASSISTENTE_ADMINISTRATIVO,ATENDENTE_CAIXA,FUNCIONARIO';
    }

    $validator = \Illuminate\Support\Facades\Validator::make($data, $rules);
    if ($validator->fails()) {
        $erros = $validator->errors()->all();
        $msg = count($erros) > 0 ? implode(' ', $erros) : 'Validação falhou';
        return response()->json(['error' => $msg, 'details' => $validator->errors()], 422)
            ->header('Access-Control-Allow-Origin', '*');
    }

    $usuarioId = null;
    if ($possuiAcesso) {
        if ($usuarioIdFornecido) {
            $usuario = DB::table('usuarios')->where('id', $usuarioIdFornecido)->where('ativo', 1)->first();
            if (!$usuario) {
                return response()->json(['error' => 'Usuário selecionado não encontrado ou inativo'], 422)->header('Access-Control-Allow-Origin', '*');
            }
            if (DB::table('funcionarios')->where('usuario_id', $usuarioIdFornecido)->exists()) {
                return response()->json(['error' => 'Esse usuário já está vinculado a outro funcionário'], 422)->header('Access-Control-Allow-Origin', '*');
            }
            $usuarioId = $usuarioIdFornecido;
        } else {
            $login = trim($data['login_usuario'] ?? '');
            if (DB::table('usuarios')->where('email', $login)->exists()) {
                return response()->json(['error' => 'E-mail/login já cadastrado para outro usuário'], 422)->header('Access-Control-Allow-Origin', '*');
            }
            $usuarioId = DB::table('usuarios')->insertGetId([
                'nome' => trim($data['nome_completo']),
                'email' => $login,
                'perfil' => $data['perfil_usuario'] ?? 'FUNCIONARIO',
                'senha_hash' => Hash::make($data['senha_usuario']),
                'ativo' => 1,
                'unidade_id' => !empty($data['unidade_id']) ? (int)$data['unidade_id'] : null,
            ]);
        }
    }

    $insert = [
        'nome_completo' => trim($data['nome_completo']),
        'cpf' => $cpfFormatado,
        'data_nascimento' => !empty($data['data_nascimento']) ? $data['data_nascimento'] : null,
        'sexo' => $data['sexo'] ?? null,
        'estado_civil' => $data['estado_civil'] ?? null,
        'cargo' => trim($data['cargo']),
        'unidade_id' => !empty($data['unidade_id']) ? (int)$data['unidade_id'] : null,
        'whatsapp' => $data['whatsapp'] ?? null,
        'email' => $data['email'] ?? null,
        'data_admissao' => !empty($data['data_admissao']) ? $data['data_admissao'] : null,
        'status' => $data['status'] ?? 'ativo',
        'possui_acesso' => $possuiAcesso ? 1 : 0,
        'usuario_id' => $usuarioId,
        'observacoes' => $data['observacoes'] ?? null,
    ];
    foreach (['banco', 'agencia', 'conta', 'conta_digito', 'pix'] as $colBancario) {
        if ($funcionariosTableHasColumn($colBancario)) {
            $insert[$colBancario] = $data[$colBancario] ?? null;
        }
    }
    if ($funcionariosTableHasColumn('escolaridade') && ($request->exists('escolaridade') || array_key_exists('escolaridade', $data))) {
        $insert['escolaridade'] = isset($data['escolaridade']) && trim((string) $data['escolaridade']) !== ''
            ? mb_substr(trim((string) $data['escolaridade']), 0, 80)
            : null;
    }
    if ($funcionariosTableHasColumn('formacao_json') && ($request->exists('formacao_json') || array_key_exists('formacao_json', $data))) {
        $insert['formacao_json'] = $normalizeFuncionarioFormacaoJson($data);
    }
    if ($funcionariosTableHasColumn('ctps') && ($request->exists('ctps') || array_key_exists('ctps', $data))) {
        $insert['ctps'] = isset($data['ctps']) && trim((string) $data['ctps']) !== ''
            ? mb_substr(trim((string) $data['ctps']), 0, 80)
            : null;
    }
    if ($request->hasFile('foto')) {
        $foto = $request->file('foto');
        $uploadDir = public_path('uploads/funcionarios');
        if (!File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }
        $nomeArquivo = time() . '_' . $foto->getClientOriginalName();
        $foto->move($uploadDir, $nomeArquivo);
        $insert['foto'] = 'uploads/funcionarios/' . $nomeArquivo;
    }
    $id = DB::table('funcionarios')->insertGetId($insert);
    $funcionario = DB::table('funcionarios')->leftJoin('unidades', 'funcionarios.unidade_id', '=', 'unidades.id')->select('funcionarios.*', 'unidades.nome as unidade_nome')->where('funcionarios.id', $id)->first();
    return response()->json($funcionario, 201)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('POST /funcionarios: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
        $msg = $e->getMessage();
        if (strpos($msg, 'Base table') !== false || strpos($msg, 'doesn\'t exist') !== false) {
            $msg = 'Tabela de funcionários não encontrada. Execute no servidor: php artisan migrate --force';
        }
        return response()->json(['error' => $msg], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::post('/funcionarios/{id}/atualizar', function (Request $request, $id) use ($normalizeFuncionarioFormacaoJson, $funcionariosTableHasColumn) {
    try {
    $userId = $request->header('X-Usuario-Id');
    if (!$userId || !DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first()) {
        return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
    }

    $existente = DB::table('funcionarios')->where('id', $id)->first();
    if (!$existente) {
        return response()->json(['error' => 'Funcionário não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
    }

    $data = $request->all();

    // Permite atualizar CPF (mantendo validação e unicidade)
    $cpfFormatado = null;
    if (array_key_exists('cpf', $data)) {
        $cpfLimpo = preg_replace('/\D/', '', (string) ($data['cpf'] ?? ''));
        if (strlen($cpfLimpo) !== 11) {
            return response()->json(['error' => 'CPF inválido'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        $cpfFormatado = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfLimpo);
        $exists = DB::table('funcionarios')
            ->where('cpf', $cpfFormatado)
            ->where('id', '!=', $id)
            ->exists();
        if ($exists) {
            return response()->json(['error' => 'CPF já cadastrado'], 422)->header('Access-Control-Allow-Origin', '*');
        }
    }
    // RH: acesso ao sistema (persistir vínculo usuário/email)
    $possuiAcesso = !empty($data['possui_acesso']) && !in_array($data['possui_acesso'], [false, 'false', '0', 0, ''], true);
    $usuarioIdFornecido = !empty($data['usuario_id']) ? (int)$data['usuario_id'] : null;

    $rules = [
        'nome_completo' => 'required|string|max:255',
        'cargo' => 'required|string|max:255',
        'status' => 'required|in:ativo,inativo',
    ];
    // Só exige login/senha novos quando vai criar usuário: sem usuario_id na requisição e sem vínculo já salvo no funcionário
    $precisaCriarUsuarioNovo = $possuiAcesso && ! $usuarioIdFornecido && empty($existente->usuario_id);
    if ($precisaCriarUsuarioNovo) {
        $rules['login_usuario'] = 'required|string|max:255';
        $rules['senha_usuario'] = 'required|string|min:6';
        $rules['perfil_usuario'] = 'required|string|in:ADMIN,GERENTE,FINANCEIRO,ASSISTENTE_ADMINISTRATIVO,ATENDENTE_CAIXA,FUNCIONARIO';
    }
    $validator = \Illuminate\Support\Facades\Validator::make($data, $rules);
    if ($validator->fails()) {
        $erros = $validator->errors()->all();
        $msg = count($erros) > 0 ? implode(' ', $erros) : 'Validação falhou';
        return response()->json(['error' => $msg, 'details' => $validator->errors()], 422)->header('Access-Control-Allow-Origin', '*');
    }

    $usuarioId = null;
    if ($possuiAcesso) {
        if ($usuarioIdFornecido) {
            $usuario = DB::table('usuarios')
                ->where('id', $usuarioIdFornecido)
                ->where('ativo', 1)
                ->first();
            if (!$usuario) {
                return response()->json(['error' => 'Usuário selecionado não encontrado ou inativo'], 422)->header('Access-Control-Allow-Origin', '*');
            }
            // Garante que o usuário não esteja vinculado a outro funcionário
            if (DB::table('funcionarios')->where('usuario_id', $usuarioIdFornecido)->where('id', '!=', $id)->exists()) {
                return response()->json(['error' => 'Esse usuário já está vinculado a outro funcionário'], 422)->header('Access-Control-Allow-Origin', '*');
            }
            $usuarioId = $usuarioIdFornecido;
        } elseif (! empty($existente->usuario_id)) {
            // Edição: mantém o usuário já vinculado (não exige reenviar senha nem usuario_id no form)
            $usuarioId = (int) $existente->usuario_id;
        } else {
            $login = trim($data['login_usuario'] ?? '');
            if (DB::table('usuarios')->where('email', $login)->exists()) {
                return response()->json(['error' => 'E-mail/login já cadastrado para outro usuário'], 422)->header('Access-Control-Allow-Origin', '*');
            }
            $usuarioId = DB::table('usuarios')->insertGetId([
                'nome' => trim($data['nome_completo']),
                'email' => $login,
                'perfil' => $data['perfil_usuario'] ?? 'FUNCIONARIO',
                'senha_hash' => Hash::make($data['senha_usuario']),
                'ativo' => 1,
                'unidade_id' => !empty($data['unidade_id']) ? (int)$data['unidade_id'] : null,
            ]);
        }
    }

    $update = [
        'nome_completo' => trim($data['nome_completo']),
        'data_nascimento' => !empty($data['data_nascimento']) ? $data['data_nascimento'] : null,
        'sexo' => $data['sexo'] ?? null,
        'estado_civil' => $data['estado_civil'] ?? null,
        'cargo' => trim($data['cargo']),
        'unidade_id' => isset($data['unidade_id']) && $data['unidade_id'] !== '' ? (int)$data['unidade_id'] : null,
        'whatsapp' => $data['whatsapp'] ?? null,
        'email' => $data['email'] ?? null,
        'data_admissao' => !empty($data['data_admissao']) ? $data['data_admissao'] : null,
        'status' => $data['status'] ?? 'ativo',
        'observacoes' => $data['observacoes'] ?? null,
        'possui_acesso' => $possuiAcesso ? 1 : 0,
        'usuario_id' => $usuarioId,
    ];
    if ($cpfFormatado !== null) {
        $update['cpf'] = $cpfFormatado;
    }
    foreach (['banco', 'agencia', 'conta', 'conta_digito', 'pix'] as $colBancario) {
        if ($funcionariosTableHasColumn($colBancario)) {
            $update[$colBancario] = $data[$colBancario] ?? null;
        }
    }
    if ($funcionariosTableHasColumn('escolaridade') && ($request->exists('escolaridade') || array_key_exists('escolaridade', $data))) {
        $update['escolaridade'] = isset($data['escolaridade']) && trim((string) $data['escolaridade']) !== ''
            ? mb_substr(trim((string) $data['escolaridade']), 0, 80)
            : null;
    }
    if ($funcionariosTableHasColumn('formacao_json') && ($request->exists('formacao_json') || array_key_exists('formacao_json', $data))) {
        $update['formacao_json'] = $normalizeFuncionarioFormacaoJson($data);
    }
    if ($funcionariosTableHasColumn('ctps') && ($request->exists('ctps') || array_key_exists('ctps', $data))) {
        $update['ctps'] = isset($data['ctps']) && trim((string) $data['ctps']) !== ''
            ? mb_substr(trim((string) $data['ctps']), 0, 80)
            : null;
    }
    if ($request->hasFile('foto')) {
        if ($existente->foto && file_exists(public_path($existente->foto))) {
            @unlink(public_path($existente->foto));
        }
        $foto = $request->file('foto');
        $uploadDir = public_path('uploads/funcionarios');
        if (!File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }
        $nomeArquivo = time() . '_' . $foto->getClientOriginalName();
        $foto->move($uploadDir, $nomeArquivo);
        $update['foto'] = 'uploads/funcionarios/' . $nomeArquivo;
    }
    if (isset($data['remove_foto']) && $data['remove_foto'] == '1') {
        if ($existente->foto && file_exists(public_path($existente->foto))) {
            @unlink(public_path($existente->foto));
        }
        $update['foto'] = null;
    }
    DB::table('funcionarios')->where('id', $id)->update($update);
    return response()->json(DB::table('funcionarios')->leftJoin('unidades', 'funcionarios.unidade_id', '=', 'unidades.id')->select('funcionarios.*', 'unidades.nome as unidade_nome')->where('funcionarios.id', $id)->first())
        ->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('POST /funcionarios/{id}/atualizar: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
        $msg = $e->getMessage();
        if (strpos($msg, 'Base table') !== false || strpos($msg, 'doesn\'t exist') !== false) {
            $msg = 'Tabela ou coluna não encontrada. Execute no servidor: php artisan migrate --force';
        } elseif (strpos($msg, 'Unknown column') !== false) {
            $msg = 'Banco de dados desatualizado (coluna ausente). Execute: php artisan migrate --force';
        }
        return response()->json(['error' => $msg], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::put('/funcionarios/{id}', function (Request $request, $id) use ($normalizeFuncionarioFormacaoJson, $funcionariosTableHasColumn) {
    $userId = $request->header('X-Usuario-Id');
    if (!$userId || !DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first()) {
        return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
    }

    $existente = DB::table('funcionarios')->where('id', $id)->first();
    if (!$existente) {
        return response()->json(['error' => 'Funcionário não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
    }

    $data = $request->all();
    $rules = ['nome_completo' => 'required|string|max:255', 'cargo' => 'required|string|max:255', 'status' => 'required|in:ativo,inativo'];
    $validator = \Illuminate\Support\Facades\Validator::make($data, $rules);
    if ($validator->fails()) {
        $erros = $validator->errors()->all();
        $msg = count($erros) > 0 ? implode(' ', $erros) : 'Validação falhou';
        return response()->json(['error' => $msg, 'details' => $validator->errors()], 422)->header('Access-Control-Allow-Origin', '*');
    }

    $update = [
        'nome_completo' => trim($data['nome_completo']),
        'data_nascimento' => !empty($data['data_nascimento']) ? $data['data_nascimento'] : null,
        'sexo' => $data['sexo'] ?? null,
        'estado_civil' => $data['estado_civil'] ?? null,
        'cargo' => trim($data['cargo']),
        'unidade_id' => isset($data['unidade_id']) && $data['unidade_id'] !== '' ? (int)$data['unidade_id'] : null,
        'whatsapp' => $data['whatsapp'] ?? null,
        'email' => $data['email'] ?? null,
        'data_admissao' => !empty($data['data_admissao']) ? $data['data_admissao'] : null,
        'status' => $data['status'] ?? 'ativo',
        'observacoes' => $data['observacoes'] ?? null,
    ];
    foreach (['banco', 'agencia', 'conta', 'conta_digito', 'pix'] as $colBancario) {
        if ($funcionariosTableHasColumn($colBancario)) {
            $update[$colBancario] = $data[$colBancario] ?? null;
        }
    }
    if ($funcionariosTableHasColumn('escolaridade') && ($request->exists('escolaridade') || array_key_exists('escolaridade', $data))) {
        $update['escolaridade'] = isset($data['escolaridade']) && trim((string) $data['escolaridade']) !== ''
            ? mb_substr(trim((string) $data['escolaridade']), 0, 80)
            : null;
    }
    if ($funcionariosTableHasColumn('formacao_json') && ($request->exists('formacao_json') || array_key_exists('formacao_json', $data))) {
        $update['formacao_json'] = $normalizeFuncionarioFormacaoJson($data);
    }
    if ($funcionariosTableHasColumn('ctps') && ($request->exists('ctps') || array_key_exists('ctps', $data))) {
        $update['ctps'] = isset($data['ctps']) && trim((string) $data['ctps']) !== ''
            ? mb_substr(trim((string) $data['ctps']), 0, 80)
            : null;
    }
    if ($request->hasFile('foto')) {
        if ($existente->foto && file_exists(public_path($existente->foto))) {
            unlink(public_path($existente->foto));
        }
        $foto = $request->file('foto');
        $uploadDir = public_path('uploads/funcionarios');
        if (!File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }
        $nomeArquivo = time() . '_' . $foto->getClientOriginalName();
        $foto->move($uploadDir, $nomeArquivo);
        $update['foto'] = 'uploads/funcionarios/' . $nomeArquivo;
    }
    if (isset($data['remove_foto']) && $data['remove_foto'] == '1') {
        if ($existente->foto && file_exists(public_path($existente->foto))) {
            unlink(public_path($existente->foto));
        }
        $update['foto'] = null;
    }
    DB::table('funcionarios')->where('id', $id)->update($update);
    return response()->json(DB::table('funcionarios')->leftJoin('unidades', 'funcionarios.unidade_id', '=', 'unidades.id')->select('funcionarios.*', 'unidades.nome as unidade_nome')->where('funcionarios.id', $id)->first())
        ->header('Access-Control-Allow-Origin', '*');
});

Route::put('/funcionarios/{id}/inativar', function (Request $request, $id) {
    $userId = $request->header('X-Usuario-Id');
    if (!$userId || !DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first()) {
        return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
    }
    $f = DB::table('funcionarios')->where('id', $id)->first();
    if (!$f) return response()->json(['error' => 'Funcionário não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
    DB::table('funcionarios')->where('id', $id)->update(['status' => 'inativo']);
    return response()->json(['message' => 'Funcionário inativado com sucesso'])->header('Access-Control-Allow-Origin', '*');
});

Route::delete('/funcionarios/{id}/excluir', function (Request $request, $id) {
    $userId = $request->header('X-Usuario-Id');
    $usuario = $userId ? DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first() : null;
    if (! $usuario || strtoupper((string) ($usuario->perfil ?? '')) !== 'ADMIN') {
        return response()->json(['error' => 'Apenas administradores podem excluir funcionários.'], 403)
            ->header('Access-Control-Allow-Origin', '*');
    }

    $f = DB::table('funcionarios')->where('id', $id)->first();
    if (!$f) {
        return response()->json(['error' => 'Funcionário não encontrado'], 404)
            ->header('Access-Control-Allow-Origin', '*');
    }

    // Remove foto do disco (se existir)
    if (!empty($f->foto) && is_string($f->foto) && file_exists(public_path($f->foto))) {
        @unlink(public_path($f->foto));
    }

    DB::table('funcionarios')->where('id', $id)->delete();

    \Log::warning('ADMIN excluiu funcionário', [
        'usuario_id' => (int) $userId,
        'funcionario_id' => (int) $id,
        'cpf' => (string) ($f->cpf ?? ''),
        'nome' => (string) ($f->nome_completo ?? ''),
        'ip' => $request->ip(),
    ]);

    return response()->json(['sucesso' => true])->header('Access-Control-Allow-Origin', '*');
});

// ============================================
// FECHAMENTOS DE CAIXA (Auditoria — persistência + PDF)
// ============================================

$fechamentoCaixaCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform');

Route::options('/fechamentos-caixa', $fechamentoCaixaCors);
Route::options('/fechamentos-caixa/relatorio-dashboard-pdf', $fechamentoCaixaCors);
Route::options('/fechamentos-caixa/{id}', $fechamentoCaixaCors);
Route::options('/fechamentos-caixa/{id}/pdf', $fechamentoCaixaCors);

$fechamentoCaixaAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');

    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

$podeAcessarFechamentoCaixa = function ($u) {
    if (!$u) {
        return false;
    }
    $p = strtoupper(trim($u->perfil ?? ''));
    $perfis = ['ADMIN', 'GERENTE', 'FINANCEIRO', 'ASSISTENTE_ADMINISTRATIVO', 'ATENDENTE_CAIXA', 'FUNCIONARIO'];
    if (in_array($p, $perfis, true)) {
        return true;
    }
    $pm = $u->permissoes_menu ?? null;
    if (is_string($pm)) {
        $dec = json_decode($pm, true);

        return is_array($dec) && in_array('fechamento', $dec, true);
    }

    return false;
};

Route::get('/fechamentos-caixa', function (Request $request) use ($fechamentoCaixaAuth, $podeAcessarFechamentoCaixa) {
    if (!Schema::hasTable('fechamentos_caixa')) {
        return response()->json(['error' => 'Tabela de fechamentos não disponível. Execute as migrations.'], 503)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $u = $fechamentoCaixaAuth($request);
    if (!$u) {
        return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
    }
    if (!$podeAcessarFechamentoCaixa($u)) {
        return response()->json(['error' => 'Sem permissão para auditoria de fechamento'], 403)->header('Access-Control-Allow-Origin', '*');
    }

    $limit = min(max((int) $request->query('limit', 200), 1), 3000);
    $q = DB::table('fechamentos_caixa')
        ->leftJoin('unidades', 'fechamentos_caixa.unidade_id', '=', 'unidades.id')
        ->leftJoin('usuarios as reg', 'fechamentos_caixa.registrado_por_usuario_id', '=', 'reg.id')
        ->select(
            'fechamentos_caixa.*',
            'unidades.nome as unidade_nome',
            'reg.nome as registrado_por_nome'
        );

    if ($request->filled('unidade_id')) {
        $q->where('fechamentos_caixa.unidade_id', (int) $request->query('unidade_id'));
    }
    if ($request->filled('de')) {
        $q->whereDate('fechamentos_caixa.data_fechamento', '>=', $request->query('de'));
    }
    if ($request->filled('ate')) {
        $q->whereDate('fechamentos_caixa.data_fechamento', '<=', $request->query('ate'));
    }

    $sort = $request->query('sort');
    if ($sort === 'data_desc') {
        $q->orderByDesc('fechamentos_caixa.data_fechamento')->orderByDesc('fechamentos_caixa.id');
    } else {
        $q->orderByDesc('fechamentos_caixa.created_at');
    }

    $rows = $q->limit($limit)->get();

    return response()->json($rows)->header('Access-Control-Allow-Origin', '*');
});

/**
 * Relatório PDF do dashboard de fechamentos (mesmos filtros: período, unidade API, operador, unidades nos cards).
 */
Route::get('/fechamentos-caixa/relatorio-dashboard-pdf', function (Request $request) use ($fechamentoCaixaAuth, $podeAcessarFechamentoCaixa) {
    if (!Schema::hasTable('fechamentos_caixa')) {
        return response()->json(['error' => 'Tabela de fechamentos não disponível. Execute as migrations.'], 503)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $u = $fechamentoCaixaAuth($request);
    if (!$u) {
        return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
    }
    if (!$podeAcessarFechamentoCaixa($u)) {
        return response()->json(['error' => 'Sem permissão para auditoria de fechamento'], 403)->header('Access-Control-Allow-Origin', '*');
    }

    $de = $request->query('de');
    $ate = $request->query('ate');
    if (!$de || !$ate) {
        return response()->json(['error' => 'Informe data inicial (de) e final (ate).'], 422)->header('Access-Control-Allow-Origin', '*');
    }

    $limit = min(max((int) $request->query('limit', 3000), 1), 3000);
    $q = DB::table('fechamentos_caixa')
        ->leftJoin('unidades', 'fechamentos_caixa.unidade_id', '=', 'unidades.id')
        ->leftJoin('usuarios as reg', 'fechamentos_caixa.registrado_por_usuario_id', '=', 'reg.id')
        ->select(
            'fechamentos_caixa.*',
            'unidades.nome as unidade_nome',
            'reg.nome as registrado_por_nome'
        )
        ->whereDate('fechamentos_caixa.data_fechamento', '>=', $de)
        ->whereDate('fechamentos_caixa.data_fechamento', '<=', $ate);

    if ($request->filled('unidade_id')) {
        $q->where('fechamentos_caixa.unidade_id', (int) $request->query('unidade_id'));
    }

    $q->orderByDesc('fechamentos_caixa.data_fechamento')->orderByDesc('fechamentos_caixa.id');
    $rows = $q->limit($limit)->get();

    $operadorFiltro = trim((string) $request->query('operador_nome', ''));
    if ($operadorFiltro !== '') {
        $rows = $rows->filter(function ($r) use ($operadorFiltro) {
            return trim((string) ($r->operador_nome ?? '')) === $operadorFiltro;
        })->values();
    }

    $cardsRaw = $request->query('unidades_cards');
    if ($request->has('unidades_cards')) {
        $cardsStr = trim((string) $cardsRaw);
        if ($cardsStr === '') {
            $rows = collect([]);
        } else {
            $allowed = array_filter(array_map('intval', explode(',', $cardsStr)));
            $allowedSet = array_flip($allowed);
            $rows = $rows->filter(function ($r) use ($allowedSet) {
                $uid = (int) ($r->unidade_id ?? 0);

                return $uid !== 0 && isset($allowedSet[$uid]);
            })->values();
        }
    }

    $h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $fmt = fn ($n) => 'R$ ' . number_format((float) $n, 2, ',', '.');

    $totPdvLinha = function ($row) {
        try {
            $linhas = json_decode($row->linhas_json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return 0.0;
        }
        if (!is_array($linhas)) {
            return 0.0;
        }
        $s = 0.0;
        foreach ($linhas as $L) {
            if (!is_array($L)) {
                continue;
            }
            $s += (float) ($L['sis'] ?? 0);
        }

        return round($s, 2);
    };

    $totMaqLinha = function ($row) {
        try {
            $linhas = json_decode($row->linhas_json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return 0.0;
        }
        if (!is_array($linhas)) {
            return 0.0;
        }
        $s = 0.0;
        foreach ($linhas as $L) {
            if (!is_array($L)) {
                continue;
            }
            $s += (float) ($L['maq'] ?? 0);
        }

        return round($s, 2);
    };

    $situation = function ($row) {
        $saldo = (float) ($row->saldo_liquido ?? 0);
        $tol = 0.009;
        $semFlag = (int) ($row->sem_quebra ?? 0) === 1;
        if ($semFlag || abs($saldo) < $tol) {
            return 'sem';
        }
        if ($saldo > $tol) {
            return 'sobra';
        }

        return 'quebra';
    };

    $n = $rows->count();
    $sumMaq = 0.0;
    $sumPdv = 0.0;
    $sumQuebra = 0.0;
    $sumSobra = 0.0;
    $nSem = 0;
    $nQuebraRegs = 0;
    $nSobraRegs = 0;

    $porUnidade = [];
    $porDiaPdv = [];
    $porDiaMaq = [];

    foreach ($rows as $r) {
        $pdv = $totPdvLinha($r);
        $maq = $totMaqLinha($r);
        $sumPdv += $pdv;
        $sumMaq += $maq;
        $sit = $situation($r);
        $saldo = (float) ($r->saldo_liquido ?? 0);
        if ($sit === 'sem') {
            $nSem++;
        } elseif ($sit === 'quebra') {
            $sumQuebra += abs($saldo);
            $nQuebraRegs++;
        } else {
            $sumSobra += $saldo;
            $nSobraRegs++;
        }

        $uid = (int) ($r->unidade_id ?? 0);
        $unome = (string) ($r->unidade_nome ?? '—');
        $key = $uid > 0 ? (string) $uid : '_null';
        if (!isset($porUnidade[$key])) {
            $porUnidade[$key] = ['nome' => $unome, 'n' => 0, 'pdv' => 0.0, 'maq' => 0.0, 'q' => 0.0, 's' => 0.0, 'sem' => 0];
        }
        $porUnidade[$key]['n']++;
        $porUnidade[$key]['pdv'] += $pdv;
        $porUnidade[$key]['maq'] += $maq;
        if ($sit === 'sem') {
            $porUnidade[$key]['sem']++;
        } elseif ($sit === 'quebra') {
            $porUnidade[$key]['q'] += abs($saldo);
        } else {
            $porUnidade[$key]['s'] += $saldo;
        }

        $dia = $r->data_fechamento ? \Carbon\Carbon::parse($r->data_fechamento)->format('Y-m-d') : '';
        if ($dia !== '') {
            if (!isset($porDiaPdv[$dia])) {
                $porDiaPdv[$dia] = ['pdv' => 0.0, 'n' => 0];
            }
            $porDiaPdv[$dia]['pdv'] += $pdv;
            $porDiaPdv[$dia]['n']++;
            if (!isset($porDiaMaq[$dia])) {
                $porDiaMaq[$dia] = 0.0;
            }
            $porDiaMaq[$dia] = round($porDiaMaq[$dia] + $maq, 2);
        }
    }

    $sumPdv = round($sumPdv, 2);
    $sumMaq = round($sumMaq, 2);
    $sumQuebra = round($sumQuebra, 2);
    $sumSobra = round($sumSobra, 2);
    $pctSem = $n > 0 ? (round(($nSem / $n) * 1000) / 10) : 0.0;

    uasort($porDiaPdv, function ($a, $b) {
        if ($a['pdv'] === $b['pdv']) {
            return $b['n'] <=> $a['n'];
        }

        return $b['pdv'] <=> $a['pdv'];
    });
    $top3Dias = array_slice($porDiaPdv, 0, 3, true);

    $deBr = \Carbon\Carbon::parse($de)->format('d/m/Y');
    $ateBr = \Carbon\Carbon::parse($ate)->format('d/m/Y');
    $emitido = \Carbon\Carbon::now()->format('d/m/Y H:i');

    $diasSemPt = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    $htmlTop3Dias = '';
    $rank = 1;
    foreach ($top3Dias as $diaIso => $info) {
        $cDia = \Carbon\Carbon::parse($diaIso);
        $diaComSemana = $h($diasSemPt[$cDia->dayOfWeek] . ' ' . $cDia->format('d/m/Y'));
        $valorPdv = $h($fmt($info['pdv']));
        $nRegsDia = $h((string) $info['n']);
        $ord = $h((string) $rank . 'º');
        $htmlTop3Dias .= '<td width="33.33%" style="padding:4px;vertical-align:top;">'
            . '<div style="border:1px solid #e0e0e0;border-radius:12px;padding:12px 10px;background:#f5f9fc;text-align:center;min-height:118px;">'
            . '<div style="font-size:17pt;font-weight:bold;color:#1565c0;line-height:1;">' . $ord . '</div>'
            . '<div style="font-size:8.5pt;color:#455a64;margin:8px 0 4px;">' . $diaComSemana . '</div>'
            . '<div style="font-size:12pt;font-weight:bold;color:#1a237e;">' . $valorPdv . '</div>'
            . '<div style="font-size:7pt;color:#607d8b;text-transform:uppercase;margin-top:2px;">em PDV</div>'
            . '<div style="font-size:8pt;color:#546e7a;margin-top:8px;">' . $nRegsDia . ' fechamento(s) neste dia</div>'
            . '</div></td>';
        $rank++;
    }
    while ($rank <= 3) {
        $htmlTop3Dias .= '<td width="33.33%" style="padding:4px;vertical-align:top;">'
            . '<div style="border:1px dashed #cfd8dc;border-radius:12px;padding:12px;text-align:center;color:#90a4ae;font-size:8pt;min-height:118px;">—</div></td>';
        $rank++;
    }

    $unitsRank = array_values($porUnidade);
    usort($unitsRank, function ($a, $b) {
        if (abs($a['pdv'] - $b['pdv']) < 0.0001) {
            return $b['n'] <=> $a['n'];
        }

        return $b['pdv'] <=> $a['pdv'];
    });
    $logoDataUri = '';
    foreach ([
        dirname(base_path()) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'imagens' . DIRECTORY_SEPARATOR . 'logosemfundo.png',
        base_path('public' . DIRECTORY_SEPARATOR . 'imagens' . DIRECTORY_SEPARATOR . 'logosemfundo.png'),
        dirname(base_path()) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'imagens' . DIRECTORY_SEPARATOR . 'logo.png',
        base_path('public' . DIRECTORY_SEPARATOR . 'imagens' . DIRECTORY_SEPARATOR . 'logo.png'),
        base_path('public' . DIRECTORY_SEPARATOR . 'logo.png'),
    ] as $_lp) {
        if (is_string($_lp) && is_readable($_lp)) {
            $raw = @file_get_contents($_lp);
            if ($raw !== false && strlen($raw) > 20) {
                $ext = strtolower((string) pathinfo($_lp, PATHINFO_EXTENSION));
                $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : 'image/png';
                $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode($raw);
                break;
            }
        }
    }

    $maxBarPdv = 0.01;
    foreach ($unitsRank as $_u) {
        $maxBarPdv = max($maxBarPdv, $_u['pdv']);
    }
    $htmlBarUnidadesBody = '';
    $unitRows = 0;
    foreach ($unitsRank as $uu) {
        if ($uu['pdv'] < 0.005 && (int) $uu['n'] === 0) {
            continue;
        }
        if ($unitRows >= 28) {
            break;
        }
        $pctRaw = $maxBarPdv > 0 ? (($uu['pdv'] / $maxBarPdv) * 100) : 0;
        $pctDisp = max(1.5, min(100, round($pctRaw, 1)));
        $restPct = max(0.5, round(100 - $pctDisp, 1));
        if ($pctDisp >= 99.5) {
            $pctDisp = 100;
            $restPct = 0;
        }
        $nome = $uu['nome'];
        if (function_exists('mb_substr')) {
            $nome = mb_substr($nome, 0, 30);
        } else {
            $nome = substr($nome, 0, 30);
        }
        $barCells = $restPct > 0
            ? '<td width="' . $h((string) $pctDisp) . '%" bgcolor="#1565c0" style="height:12px;font-size:1px;line-height:12px;">&#160;</td>'
                . '<td width="' . $h((string) $restPct) . '%" bgcolor="#eceff1" style="height:12px;font-size:1px;line-height:12px;">&#160;</td>'
            : '<td bgcolor="#1565c0" style="height:12px;font-size:1px;line-height:12px;width:100%;">&#160;</td>';
        $htmlBarUnidadesBody .= '<tr>'
            . '<td class="pdf-uni-name">' . $h($nome) . '</td>'
            . '<td class="pdf-uni-bar"><table width="100%" cellpadding="0" cellspacing="0" style="table-layout:fixed;"><tr>' . $barCells . '</tr></table></td>'
            . '<td class="pdf-uni-val">' . $h($fmt($uu['pdv'])) . '</td>'
            . '</tr>';
        $unitRows++;
    }
    if ($htmlBarUnidadesBody === '') {
        $htmlBarUnidadesBody = '<tr><td colspan="3" style="padding:10px;color:#90a4ae;font-size:9pt;">Sem vendas PDV por unidade no filtro.</td></tr>';
    }
    $htmlBarUnidades = '<div class="pdf-chart-title">Vendas PDV por unidade (R$)</div>'
        . '<table class="pdf-uni-tbl" width="100%" cellpadding="0" cellspacing="0"><colgroup><col style="width:26%" /><col style="width:48%" /><col style="width:26%" /></colgroup>'
        . $htmlBarUnidadesBody . '</table>';

    $linePts = [];
    $c0 = \Carbon\Carbon::parse($de)->startOfDay();
    $c1 = \Carbon\Carbon::parse($ate)->startOfDay();
    for ($c = $c0->copy(); $c->lte($c1); $c->addDay()) {
        $k = $c->format('Y-m-d');
        $linePts[] = ['d' => $k, 'v' => (float) ($porDiaMaq[$k] ?? 0)];
    }

    $lineDisp = $linePts;
    $nL = count($lineDisp);
    $maxColsMaq = 16;
    if ($nL > $maxColsMaq) {
        $step = (int) ceil($nL / $maxColsMaq);
        $tmp = [];
        for ($i = 0; $i < $nL; $i += $step) {
            $tmp[] = $lineDisp[$i];
        }
        if ($tmp !== [] && ($tmp[count($tmp) - 1]['d'] ?? '') !== ($lineDisp[$nL - 1]['d'] ?? '')) {
            $tmp[] = $lineDisp[$nL - 1];
        }
        $lineDisp = $tmp;
    }
    $maxMLine = 0.01;
    foreach ($lineDisp as $p) {
        $maxMLine = max($maxMLine, $p['v']);
    }
    $barH = 100;
    $nD = count($lineDisp);
    $htmlLineMaq = '<div class="pdf-chart-title">Evolução diária — maquinha (R$)</div>';
    $htmlLineMaq .= '<p class="pdf-chart-desc">Barras de baixo para cima; linha escura = zero. <strong>Teto:</strong> ' . $h($fmt($maxMLine)) . '.</p>';
    $htmlLineMaq .= '<table class="pdf-maq-outer" width="100%" cellpadding="0" cellspacing="0">';
    if ($nD === 0) {
        $htmlLineMaq .= '<tr><td style="padding:14px;color:#90a4ae;font-size:9pt;">Sem período</td></tr>';
    } else {
        $htmlLineMaq .= '<tr style="background:#eceff1;"><td colspan="' . (string) $nD . '" class="pdf-maq-axis">'
            . '<strong>Escala</strong> · 0 · ' . $h($fmt($maxMLine / 2)) . ' · ' . $h($fmt($maxMLine))
            . '</td></tr>';
        $wcolPct = round(100 / max(1, $nD), 3);
        $htmlLineMaq .= '<tr>';
        $ixMaq = 0;
        foreach ($lineDisp as $p) {
            $vh = $maxMLine > 0 ? max(3, (int) round($p['v'] / $maxMLine * $barH)) : 3;
            if ($vh > $barH) {
                $vh = $barH;
            }
            $spacerH = max(0, $barH - $vh);
            $dObj = \Carbon\Carbon::parse($p['d']);
            $dow = $diasSemPt[$dObj->dayOfWeek];
            $dm = $dObj->format('d/m');
            $valBlock = '<div class="pdf-maq-val">' . $h($fmt($p['v'])) . '</div>';
            $leftBd = $ixMaq > 0 ? 'border-left:1px solid #e0e0e0;' : '';
            $ixMaq++;
            $htmlLineMaq .= '<td class="pdf-maq-cell" style="width:' . $h((string) $wcolPct) . '%;' . $leftBd . '">'
                . $valBlock
                . '<table class="pdf-maq-barwrap" cellpadding="0" cellspacing="0" align="center">'
                . '<tr><td class="pdf-maq-spacer" style="height:' . (string) $spacerH . 'px;">&#160;</td></tr>'
                . '<tr><td class="pdf-maq-bar" style="height:' . (string) $vh . 'px;">&#160;</td></tr>'
                . '</table>'
                . '<div class="pdf-maq-dow">' . $h($dow) . '</div>'
                . '<div class="pdf-maq-dm">' . $h($dm) . '</div>'
                . '</td>';
        }
        $htmlLineMaq .= '</tr>';
    }
    $htmlLineMaq .= '</table>';

    $totalPie = $nSem + $nQuebraRegs + $nSobraRegs;
    $pctSemPie = $totalPie > 0 ? round(($nSem / $totalPie) * 1000) / 10 : 0;
    $pctQPie = $totalPie > 0 ? round(($nQuebraRegs / $totalPie) * 1000) / 10 : 0;
    $pctSPie = $totalPie > 0 ? round(($nSobraRegs / $totalPie) * 1000) / 10 : 0;
    $htmlSituacao = '<div class="pdf-chart-title">Situação (registros)</div>';
    if ($totalPie < 1) {
        $htmlSituacao .= '<p style="color:#90a4ae;font-size:9pt;margin:8px 0;">Sem dados.</p>';
    } else {
        $htmlSituacao .= '<table width="100%" cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-size:8pt;">'
            . '<tr><td style="width:14px;background:#546e7a;">&#160;</td><td style="padding-left:8px;border-bottom:1px solid #eee;">Sem dif. líquida</td><td align="right" style="border-bottom:1px solid #eee;"><strong>' . $h((string) $nSem) . '</strong> (' . $h((string) $pctSemPie) . '%)</td></tr>'
            . '<tr><td style="background:#c62828;">&#160;</td><td style="padding-left:8px;border-bottom:1px solid #eee;">Quebra</td><td align="right" style="border-bottom:1px solid #eee;"><strong>' . $h((string) $nQuebraRegs) . '</strong> (' . $h((string) $pctQPie) . '%)</td></tr>'
            . '<tr><td style="background:#2e7d32;">&#160;</td><td style="padding-left:8px;">Sobra</td><td align="right"><strong>' . $h((string) $nSobraRegs) . '</strong> (' . $h((string) $pctSPie) . '%)</td></tr>'
            . '</table>';
    }

    $logoImgHtml = $logoDataUri !== ''
        ? '<img src="' . $logoDataUri . '" alt="" style="max-height:54px;max-width:130px;display:block;" />'
        : '<div style="width:64px;height:50px;border:1px solid #cfd8dc;border-radius:8px;text-align:center;line-height:50px;font-size:7pt;color:#78909c;">Logo</div>';

    $pctSemTxt = $h($pctSem . '% (' . $nSem . ' reg.)');
    $cardRegs = $h((string) $n);
    $cardMaq = $h($fmt($sumMaq));
    $cardPdv = $h($fmt($sumPdv));
    $cardQ = $h($fmt($sumQuebra));
    $cardS = $h($fmt($sumSobra));

    $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"/><style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; margin: 12px 14px; }
        .pdf-header { width: 100%; border-collapse: collapse; margin-bottom: 12px; border-bottom: 2px solid #1565c0; padding-bottom: 10px; }
        .pdf-header td { vertical-align: middle; }
        .pdf-brand { font-size: 15pt; font-weight: bold; color: #0d47a1; letter-spacing: 0.02em; }
        .pdf-title { font-size: 11pt; color: #1565c0; margin-top: 3px; }
        .pdf-meta { font-size: 7.5pt; color: #546e7a; text-align: right; line-height: 1.35; }
        .sub { text-align: center; font-size: 8pt; color: #666; margin-bottom: 10px; }
        .dash-cards { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 0 10px; }
        .dash-card { border: 1px solid #e0e0e0; border-radius: 10px; padding: 8px 10px; background: #eef4f9; vertical-align: top; }
        .dash-card.alert { border-color: #ffcdd2; background: #ffebee; }
        .dash-card.ok { border-color: #c8e6c9; background: #e8f5e9; }
        .dash-card-label { font-size: 7pt; color: #607d8b; font-weight: bold; text-transform: uppercase; letter-spacing: 0.03em; }
        .dash-card-value { font-size: 12pt; font-weight: bold; color: #1a237e; margin-top: 3px; }
        .chart-wrap { border: 1px solid #e0e0e0; border-radius: 10px; padding: 6px 5px 5px; background: #fff; text-align: center; max-width: 100%; overflow: hidden; box-sizing: border-box; }
        .pdf-chart-title { font-size: 9pt; font-weight: bold; color: #37474f; margin: 2px 0 4px; text-align: left; }
        .pdf-chart-desc { font-size: 7pt; color: #546e7a; margin: 0 0 6px; line-height: 1.35; text-align: left; }
        .pdf-uni-tbl { table-layout: fixed; width: 100%; border-collapse: collapse; }
        .pdf-uni-name { padding: 3px 6px 3px 0; vertical-align: middle; border-bottom: 1px solid #eceff1; font-size: 7pt; color: #37474f; overflow: hidden; word-wrap: break-word; }
        .pdf-uni-bar { padding: 3px 2px; vertical-align: middle; border-bottom: 1px solid #eceff1; overflow: hidden; }
        .pdf-uni-val { padding: 3px 0 3px 4px; vertical-align: middle; border-bottom: 1px solid #eceff1; font-size: 7pt; font-weight: bold; color: #1a237e; text-align: right; word-wrap: break-word; overflow: hidden; }
        .pdf-maq-outer { table-layout: fixed; width: 100%; border-collapse: collapse; border: 1px solid #b0bec5; border-radius: 8px; background: #fafafa; }
        .pdf-maq-axis { padding: 4px 6px; font-size: 6.5pt; color: #37474f; text-align: center; border-bottom: 1px solid #cfd8dc; word-wrap: break-word; }
        .pdf-maq-cell { padding: 3px 1px 5px; vertical-align: top; text-align: center; background: #ffffff; overflow: hidden; box-sizing: border-box; }
        .pdf-maq-val { font-size: 5.5pt; font-weight: bold; color: #0d47a1; line-height: 1.1; padding: 0 1px 2px; min-height: 14px; word-wrap: break-word; overflow: hidden; }
        .pdf-maq-barwrap { width: 10px; margin: 0 auto; border-collapse: collapse; border-bottom: 2px solid #37474f; table-layout: fixed; }
        .pdf-maq-spacer { font-size: 1px; line-height: 1px; background: #f1f5f9; }
        .pdf-maq-bar { font-size: 1px; line-height: 1px; background: #1565c0; border: 1px solid #0d47a1; border-bottom: none; border-radius: 2px 2px 0 0; }
        .pdf-maq-dow { font-size: 6pt; color: #263238; margin-top: 4px; font-weight: bold; line-height: 1.1; word-wrap: break-word; }
        .pdf-maq-dm { font-size: 5.5pt; color: #546e7a; line-height: 1.1; }
        .charts-row { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 0 12px; }
        .charts-row td { vertical-align: top; }
        h2 { font-size: 10.5pt; margin: 12px 0 6px; color: #37474f; border-bottom: 1px solid #e3f2fd; padding-bottom: 3px; }
        .rod { margin-top: 12px; font-size: 7pt; color: #555; border-top: 1px solid #ddd; padding-top: 6px; }
    </style></head><body>
    <table class="pdf-header">
        <tr>
            <td style="width: 120px; text-align: left;">' . $logoImgHtml . '</td>
            <td style="padding-left: 10px;">
                <div class="pdf-brand">Grupo Sabor Paraense</div>
                <div class="pdf-title">Auditoria de fechamento de caixa</div>
            </td>
            <td class="pdf-meta">Emitido em ' . $h($emitido) . '<br/>Período: ' . $h($deBr) . ' a ' . $h($ateBr) . '</td>
        </tr>
    </table>

    <table class="dash-cards"><tr>
        <td class="dash-card"><div class="dash-card-label">Fechamentos no filtro</div><div class="dash-card-value">' . $cardRegs . '</div></td>
        <td class="dash-card"><div class="dash-card-label">Total maquinha (R$)</div><div class="dash-card-value">' . $cardMaq . '</div></td>
        <td class="dash-card"><div class="dash-card-label">Total PDV (R$)</div><div class="dash-card-value">' . $cardPdv . '</div></td>
    </tr><tr>
        <td class="dash-card alert"><div class="dash-card-label">Soma quebras (R$)</div><div class="dash-card-value">' . $cardQ . '</div></td>
        <td class="dash-card ok"><div class="dash-card-label">Soma sobras (R$)</div><div class="dash-card-value">' . $cardS . '</div></td>
        <td class="dash-card"><div class="dash-card-label">Sem diferença líquida</div><div class="dash-card-value">' . $pctSemTxt . '</div></td>
    </tr></table>

    <table class="charts-row" width="100%">
        <tr><td colspan="2" style="padding-bottom: 6px;"><div class="chart-wrap">' . $htmlBarUnidades . '</div></td></tr>
        <tr><td colspan="2" style="padding-bottom: 6px;"><div class="chart-wrap">' . $htmlLineMaq . '</div></td></tr>
        <tr><td colspan="2"><div class="chart-wrap" style="max-width: 520px; margin: 0 auto;">' . $htmlSituacao . '</div></td></tr>
    </table>

    <h2>Ranking — 3 dias com maior venda (PDV)</h2>
    <table width="100%" style="border-collapse:separate;border-spacing:4px;margin:0 0 10px;"><tr>' . $htmlTop3Dias . '</tr></table>

    <div class="rod">Grupo Sabor Paraense — relatório gerado pelo sistema.</div>
    </body></html>';

    try {
        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $pdfOutput = $dompdf->output();
        $fn = 'dashboard-fechamentos-caixa-' . preg_replace('/[^0-9-]/', '', $de) . '-' . preg_replace('/[^0-9-]/', '', $ate) . '.pdf';

        return response($pdfOutput, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $fn . '"')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform')
            ->header('Content-Length', (string) strlen($pdfOutput));
    } catch (\Exception $e) {
        \Log::error('GET /fechamentos-caixa/relatorio-dashboard-pdf: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao gerar PDF'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::post('/fechamentos-caixa', function (Request $request) use ($fechamentoCaixaAuth, $podeAcessarFechamentoCaixa) {
    if (!Schema::hasTable('fechamentos_caixa')) {
        return response()->json(['error' => 'Tabela de fechamentos não disponível. Execute as migrations.'], 503)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $u = $fechamentoCaixaAuth($request);
    if (!$u) {
        return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
    }
    if (!$podeAcessarFechamentoCaixa($u)) {
        return response()->json(['error' => 'Sem permissão para registrar fechamento'], 403)->header('Access-Control-Allow-Origin', '*');
    }

    $d = $request->all();
    $rules = [
        'data_fechamento' => 'required|date',
        'hora_fechamento' => 'nullable|string|max:16',
        'unidade_id' => 'nullable|integer|exists:unidades,id',
        'operador_nome' => 'nullable|string|max:500',
        'operador_usuario_id' => 'nullable|integer|exists:usuarios,id',
        'sistema_pdv' => 'nullable|string|max:200',
        'maquinha' => 'nullable|string|max:120',
        'observacoes' => 'nullable|string|max:5000',
        'linhas' => 'required|array|min:1',
        'linhas.*.key' => 'nullable|string|max:64',
        'linhas.*.label' => 'nullable|string|max:120',
        'linhas.*.esp' => 'nullable|numeric',
        'linhas.*.sis' => 'nullable|numeric',
        'linhas.*.maq' => 'nullable|numeric',
        'linhas.*.informado' => 'nullable|numeric',
        'linhas.*.diff' => 'nullable|numeric',
        'total_referencia' => 'required|numeric',
        'total_informado' => 'required|numeric',
        'saldo_liquido' => 'required|numeric',
        'sem_quebra' => 'required|boolean',
    ];
    $v = \Illuminate\Support\Facades\Validator::make($d, $rules);
    if ($v->fails()) {
        return response()->json(['error' => implode(' ', $v->errors()->all()), 'details' => $v->errors()], 422)
            ->header('Access-Control-Allow-Origin', '*');
    }

    try {
        $linhasJson = json_encode(array_values($d['linhas']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Formato de linhas inválido'], 422)->header('Access-Control-Allow-Origin', '*');
    }

    $id = DB::table('fechamentos_caixa')->insertGetId([
        'registrado_por_usuario_id' => (int) $u->id,
        'unidade_id' => isset($d['unidade_id']) && $d['unidade_id'] !== '' ? (int) $d['unidade_id'] : null,
        'data_fechamento' => $d['data_fechamento'],
        'hora_fechamento' => $d['hora_fechamento'] ?? null,
        'operador_nome' => $d['operador_nome'] ?? null,
        'operador_usuario_id' => isset($d['operador_usuario_id']) && $d['operador_usuario_id'] !== '' ? (int) $d['operador_usuario_id'] : null,
        'sistema_pdv' => $d['sistema_pdv'] ?? null,
        'maquinha' => $d['maquinha'] ?? null,
        'observacoes' => $d['observacoes'] ?? null,
        'linhas_json' => $linhasJson,
        'total_referencia' => round((float) $d['total_referencia'], 2),
        'total_informado' => round((float) $d['total_informado'], 2),
        'saldo_liquido' => round((float) $d['saldo_liquido'], 2),
        'sem_quebra' => filter_var($d['sem_quebra'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('fechamentos_caixa')
        ->leftJoin('unidades', 'fechamentos_caixa.unidade_id', '=', 'unidades.id')
        ->leftJoin('usuarios as reg', 'fechamentos_caixa.registrado_por_usuario_id', '=', 'reg.id')
        ->select('fechamentos_caixa.*', 'unidades.nome as unidade_nome', 'reg.nome as registrado_por_nome')
        ->where('fechamentos_caixa.id', $id)
        ->first();

    return response()->json($row, 201)->header('Access-Control-Allow-Origin', '*');
});

Route::get('/fechamentos-caixa/{id}', function (Request $request, $id) use ($fechamentoCaixaAuth, $podeAcessarFechamentoCaixa) {
    if (!Schema::hasTable('fechamentos_caixa')) {
        return response()->json(['error' => 'Tabela não disponível'], 503)->header('Access-Control-Allow-Origin', '*');
    }
    $u = $fechamentoCaixaAuth($request);
    if (!$u) {
        return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
    }
    if (!$podeAcessarFechamentoCaixa($u)) {
        return response()->json(['error' => 'Sem permissão'], 403)->header('Access-Control-Allow-Origin', '*');
    }
    $row = DB::table('fechamentos_caixa')
        ->leftJoin('unidades', 'fechamentos_caixa.unidade_id', '=', 'unidades.id')
        ->leftJoin('usuarios as reg', 'fechamentos_caixa.registrado_por_usuario_id', '=', 'reg.id')
        ->select('fechamentos_caixa.*', 'unidades.nome as unidade_nome', 'reg.nome as registrado_por_nome')
        ->where('fechamentos_caixa.id', (int) $id)
        ->first();
    if (!$row) {
        return response()->json(['error' => 'Registro não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
    }

    return response()->json($row)->header('Access-Control-Allow-Origin', '*');
});

Route::put('/fechamentos-caixa/{id}', function (Request $request, $id) use ($fechamentoCaixaAuth, $podeAcessarFechamentoCaixa) {
    if (!Schema::hasTable('fechamentos_caixa')) {
        return response()->json(['error' => 'Tabela não disponível'], 503)->header('Access-Control-Allow-Origin', '*');
    }
    $u = $fechamentoCaixaAuth($request);
    if (!$u) {
        return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
    }
    if (!$podeAcessarFechamentoCaixa($u)) {
        return response()->json(['error' => 'Sem permissão'], 403)->header('Access-Control-Allow-Origin', '*');
    }
    $idInt = (int) $id;
    if (!DB::table('fechamentos_caixa')->where('id', $idInt)->exists()) {
        return response()->json(['error' => 'Registro não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
    }

    $d = $request->all();
    $rules = [
        'data_fechamento' => 'required|date',
        'hora_fechamento' => 'nullable|string|max:16',
        'unidade_id' => 'nullable|integer|exists:unidades,id',
        'operador_nome' => 'nullable|string|max:500',
        'operador_usuario_id' => 'nullable|integer|exists:usuarios,id',
        'sistema_pdv' => 'nullable|string|max:200',
        'maquinha' => 'nullable|string|max:120',
        'observacoes' => 'nullable|string|max:5000',
        'linhas' => 'required|array|min:1',
        'linhas.*.key' => 'nullable|string|max:64',
        'linhas.*.label' => 'nullable|string|max:120',
        'linhas.*.esp' => 'nullable|numeric',
        'linhas.*.sis' => 'nullable|numeric',
        'linhas.*.maq' => 'nullable|numeric',
        'linhas.*.informado' => 'nullable|numeric',
        'linhas.*.diff' => 'nullable|numeric',
        'total_referencia' => 'required|numeric',
        'total_informado' => 'required|numeric',
        'saldo_liquido' => 'required|numeric',
        'sem_quebra' => 'required|boolean',
    ];
    $v = \Illuminate\Support\Facades\Validator::make($d, $rules);
    if ($v->fails()) {
        return response()->json(['error' => implode(' ', $v->errors()->all()), 'details' => $v->errors()], 422)
            ->header('Access-Control-Allow-Origin', '*');
    }

    try {
        $linhasJson = json_encode(array_values($d['linhas']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Formato de linhas inválido'], 422)->header('Access-Control-Allow-Origin', '*');
    }

    DB::table('fechamentos_caixa')->where('id', $idInt)->update([
        'unidade_id' => isset($d['unidade_id']) && $d['unidade_id'] !== '' ? (int) $d['unidade_id'] : null,
        'data_fechamento' => $d['data_fechamento'],
        'hora_fechamento' => $d['hora_fechamento'] ?? null,
        'operador_nome' => $d['operador_nome'] ?? null,
        'operador_usuario_id' => isset($d['operador_usuario_id']) && $d['operador_usuario_id'] !== '' ? (int) $d['operador_usuario_id'] : null,
        'sistema_pdv' => $d['sistema_pdv'] ?? null,
        'maquinha' => $d['maquinha'] ?? null,
        'observacoes' => $d['observacoes'] ?? null,
        'linhas_json' => $linhasJson,
        'total_referencia' => round((float) $d['total_referencia'], 2),
        'total_informado' => round((float) $d['total_informado'], 2),
        'saldo_liquido' => round((float) $d['saldo_liquido'], 2),
        'sem_quebra' => filter_var($d['sem_quebra'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        'updated_at' => now(),
    ]);

    $row = DB::table('fechamentos_caixa')
        ->leftJoin('unidades', 'fechamentos_caixa.unidade_id', '=', 'unidades.id')
        ->leftJoin('usuarios as reg', 'fechamentos_caixa.registrado_por_usuario_id', '=', 'reg.id')
        ->select('fechamentos_caixa.*', 'unidades.nome as unidade_nome', 'reg.nome as registrado_por_nome')
        ->where('fechamentos_caixa.id', $idInt)
        ->first();

    return response()->json($row)->header('Access-Control-Allow-Origin', '*');
});

Route::delete('/fechamentos-caixa/{id}', function (Request $request, $id) use ($fechamentoCaixaAuth, $podeAcessarFechamentoCaixa) {
    if (!Schema::hasTable('fechamentos_caixa')) {
        return response()->json(['error' => 'Tabela não disponível'], 503)->header('Access-Control-Allow-Origin', '*');
    }
    $u = $fechamentoCaixaAuth($request);
    if (!$u) {
        return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
    }
    if (!$podeAcessarFechamentoCaixa($u)) {
        return response()->json(['error' => 'Sem permissão'], 403)->header('Access-Control-Allow-Origin', '*');
    }
    $idInt = (int) $id;
    $n = DB::table('fechamentos_caixa')->where('id', $idInt)->delete();
    if ($n === 0) {
        return response()->json(['error' => 'Registro não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
    }

    return response()->json(['message' => 'Registro excluído com sucesso.'])->header('Access-Control-Allow-Origin', '*');
});

Route::get('/fechamentos-caixa/{id}/pdf', function (Request $request, $id) use ($fechamentoCaixaAuth, $podeAcessarFechamentoCaixa) {
    if (!Schema::hasTable('fechamentos_caixa')) {
        return response()->json(['error' => 'Tabela não disponível'], 503)->header('Access-Control-Allow-Origin', '*');
    }
    $u = $fechamentoCaixaAuth($request);
    if (!$u) {
        return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
    }
    if (!$podeAcessarFechamentoCaixa($u)) {
        return response()->json(['error' => 'Sem permissão'], 403)->header('Access-Control-Allow-Origin', '*');
    }

    $row = DB::table('fechamentos_caixa')
        ->leftJoin('unidades', 'fechamentos_caixa.unidade_id', '=', 'unidades.id')
        ->leftJoin('usuarios as reg', 'fechamentos_caixa.registrado_por_usuario_id', '=', 'reg.id')
        ->select('fechamentos_caixa.*', 'unidades.nome as unidade_nome', 'reg.nome as registrado_por_nome')
        ->where('fechamentos_caixa.id', $id)
        ->first();

    if (!$row) {
        return response()->json(['error' => 'Registro não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
    }

    $h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $fmt = fn ($n) => 'R$ ' . number_format((float) $n, 2, ',', '.');

    try {
        $linhas = json_decode($row->linhas_json, true, 512, JSON_THROW_ON_ERROR);
    } catch (\Throwable $e) {
        $linhas = [];
    }
    if (!is_array($linhas)) {
        $linhas = [];
    }

    $sumPdv = 0.0;
    $sumMaq = 0.0;
    $sumDiff = 0.0;
    $rowsHtml = '';
    foreach ($linhas as $L) {
        if (!is_array($L)) {
            continue;
        }
        $lab = $h($L['label'] ?? $L['key'] ?? '—');
        $vSis = (float) ($L['sis'] ?? 0);
        $vMaq = (float) ($L['maq'] ?? 0);
        $vDiff = round($vMaq - $vSis, 2);
        $sumPdv += $vSis;
        $sumMaq += $vMaq;
        $sumDiff += $vDiff;
        $rowsHtml .= '<tr>'
            . '<td>' . $lab . '</td>'
            . '<td style="text-align:right">' . $h($fmt($vSis)) . '</td>'
            . '<td style="text-align:right">' . $h($fmt($vMaq)) . '</td>'
            . '<td style="text-align:right">' . $h($fmt($vDiff)) . '</td>'
            . '</tr>';
    }
    $sumDiff = round($sumDiff, 2);

    $dataBr = $row->data_fechamento ? \Carbon\Carbon::parse($row->data_fechamento)->format('d/m/Y') : '—';
    $hora = $h($row->hora_fechamento ?? '—');
    $maqMap = [
        'stone' => 'Stone',
        'cielo' => 'Cielo',
        'rede' => 'Rede',
        'pagbank' => 'PagBank / PagSeguro',
        'mercado_pago' => 'Mercado Pago',
        'sumup' => 'SumUp',
        'nao_utilizada' => 'Não utilizada neste fechamento',
        'outra' => 'Outra',
    ];
    $mk = strtolower(trim((string) ($row->maquinha ?? '')));
    $maqLegivel = $mk !== '' ? ($maqMap[$mk] ?? $row->maquinha) : '—';
    $criado = $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i') : '—';

    $saldoL = (float) ($row->saldo_liquido ?? 0);
    if (abs($saldoL) < 0.01) {
        $fechRot = 'Sem quebra (compensado entre formas)';
        $fechVal = $fmt(0);
    } elseif ($saldoL > 0) {
        $fechRot = 'Sobras no fechamento';
        $fechVal = $fmt($saldoL);
    } else {
        $fechRot = 'Quebra de caixa';
        $fechVal = $fmt(abs($saldoL));
    }

    $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"/><style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #222; margin: 20px; }
        h1 { font-size: 15pt; text-align: center; margin: 0 0 6px; color: #1565c0; }
        .sub { text-align: center; font-size: 9pt; color: #666; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #f0f0f0; font-size: 9pt; }
        .meta td { border: none; padding: 3px 0; font-size: 9pt; }
        .meta th { width: 28%; border: none; background: transparent; text-align: left; font-weight: bold; }
        .tot { margin-top: 10px; font-size: 10pt; }
        .rod { margin-top: 16px; font-size: 8pt; color: #555; border-top: 1px solid #ddd; padding-top: 8px; }
    </style></head><body>
    <h1>Auditoria — fechamento de caixa</h1>
    <div class="sub">Registro nº ' . $h($row->id) . ' · Emitido em ' . $h($criado) . '</div>
    <table class="meta">
        <tr><th>Data do fechamento</th><td>' . $h($dataBr) . '</td></tr>
        <tr><th>Hora</th><td>' . $hora . '</td></tr>
        <tr><th>Unidade</th><td>' . $h($row->unidade_nome ?? '—') . '</td></tr>
        <tr><th>Operador do caixa</th><td>' . $h($row->operador_nome ?? '—') . '</td></tr>
        <tr><th>Sistema (PDV)</th><td>' . $h($row->sistema_pdv ?? '—') . '</td></tr>
        <tr><th>Maquinha</th><td>' . $h($maqLegivel) . '</td></tr>
        <tr><th>Registrado por</th><td>' . $h($row->registrado_por_nome ?? '—') . '</td></tr>
        <tr><th>Fechamento</th><td>' . $h($fechRot) . '</td></tr>
        <tr><th>Valor (quebra/sobra)</th><td>' . $h($fechVal) . '</td></tr>
    </table>';
    if (trim((string) ($row->observacoes ?? '')) !== '') {
        $html .= '<p style="font-size:9pt;margin:8px 0;"><strong>Observações:</strong> ' . nl2br($h($row->observacoes)) . '</p>';
    }
    $html .= '<table><thead><tr>
        <th>Forma</th><th>PDV</th><th>Maquinha</th><th>Diferença (maq.−PDV)</th>
    </tr></thead><tbody>' . $rowsHtml . '</tbody>'
        . '<tfoot><tr>'
        . '<th style="text-align:right">Totais / Σ dif.</th>'
        . '<th style="text-align:right">' . $h($fmt($sumPdv)) . '</th>'
        . '<th style="text-align:right">' . $h($fmt($sumMaq)) . '</th>'
        . '<th style="text-align:right">' . $h($fmt($sumDiff)) . '</th>'
        . '</tr></tfoot></table>
    <div class="tot">
        <strong>Total PDV:</strong> ' . $h($fmt($sumPdv)) . ' &nbsp;|&nbsp;
        <strong>Total maquinas:</strong> ' . $h($fmt($sumMaq)) . ' &nbsp;|&nbsp;
        <strong>Σ diferenças (saldo líquido):</strong> ' . $h($fmt($sumDiff)) . '
    </div>
    <div class="rod">Grupo Sabor Paraense</div>
    </body></html>';

    try {
        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfOutput = $dompdf->output();
        $fn = 'fechamento-caixa-' . $id . '.pdf';

        return response($pdfOutput, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $fn . '"')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform')
            ->header('Content-Length', (string) strlen($pdfOutput));
    } catch (\Exception $e) {
        \Log::error('GET /fechamentos-caixa/pdf: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao gerar PDF'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

// ============================================
// PROVENTOS (Módulo Financeiro)
// ============================================

$proventosCors = fn() => response()->json([])->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'GET, POST, PUT, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform');
Route::options('/proventos', $proventosCors);
Route::options('/proventos/meus', $proventosCors);
Route::options('/proventos/{id}', $proventosCors);
Route::options('/proventos/{id}/autorizar', $proventosCors);
Route::options('/proventos/{id}/enviar-codigo', $proventosCors);
Route::options('/proventos/{id}/confirmar-assinatura', $proventosCors);
Route::options('/proventos/{id}/finalizar', $proventosCors);
Route::options('/proventos/{id}/cancelar', $proventosCors);
Route::options('/proventos/{id}/logs', $proventosCors);
Route::options('/proventos/{id}/recibo.pdf', $proventosCors);

$proventosAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');
    $u = $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
    return $u;
};
$mergeDeviceExtras = function (Request $req, $extras) {
    $base = is_array($extras) ? $extras : (is_string($extras) ? (json_decode($extras, true) ?: []) : []);
    if (!is_array($base)) $base = [];
    if ($m = $req->header('X-Device-Model')) $base['device_model'] = $m;
    if ($p = $req->header('X-Device-Platform')) $base['device_platform'] = $p;
    return array_filter($base) ?: null;
};
/** Evita SQL error se a coluna pix ainda não existir em funcionarios (migration pendente em produção). */
$proventoSelectFuncionarioPix = function () {
    if (!Schema::hasTable('funcionarios')) {
        return DB::raw('NULL as funcionario_pix');
    }
    $cols = Schema::getColumnListing('funcionarios');
    return in_array('pix', $cols, true)
        ? 'funcionarios.pix as funcionario_pix'
        : DB::raw('NULL as funcionario_pix');
};
$proventoSelectUnidadeCnpj = function () {
    if (Schema::hasTable('unidades') && Schema::hasColumn('unidades', 'cnpj')) {
        return 'unidades.cnpj as unidade_cnpj';
    }
    return DB::raw('NULL as unidade_cnpj');
};
$proventosLog = function ($proventoId, $usuarioId, $funcionarioId, $acao, $statusAnt, $statusNovo, $desc = null, $ip = null, $ua = null, $extras = null) {
    if (!Schema::hasTable('proventos_logs')) return;
    DB::table('proventos_logs')->insert([
        'provento_id' => $proventoId,
        'usuario_id' => $usuarioId,
        'funcionario_id' => $funcionarioId,
        'acao' => $acao,
        'status_anterior' => $statusAnt,
        'status_novo' => $statusNovo,
        'descricao' => $desc,
        'ip' => $ip,
        'user_agent' => $ua,
        'dados_extras' => $extras ? json_encode($extras) : null,
        'created_at' => now(),
    ]);
};

$podeCriarProvento = function ($perfil) {
    $p = strtoupper(trim($perfil ?? ''));
    return in_array($p, ['ADMIN', 'GERENTE', 'FINANCEIRO', 'ASSISTENTE_ADMINISTRATIVO']);
};
$podeAutorizarOuFinalizar = function ($perfil) {
    $p = strtoupper(trim($perfil ?? ''));
    return in_array($p, ['ADMIN', 'GERENTE', 'FINANCEIRO', 'ASSISTENTE_ADMINISTRATIVO']);
};

/** Converte created_at (horário Brasil) para UTC ISO 8601 para exibição correta no frontend */
$formatLogCreatedAt = function ($items) {
    $tz = config('app.timezone', 'America/Sao_Paulo');
    foreach ($items as $item) {
        if (!empty($item->created_at)) {
            $item->created_at = \Carbon\Carbon::parse($item->created_at, $tz)->utc()->format('Y-m-d\TH:i:s.000\Z');
        }
    }
    return $items;
};

Route::get('/proventos', function (Request $request) use ($proventosAuth, $podeCriarProvento, $proventoSelectFuncionarioPix, $proventoSelectUnidadeCnpj) {
    try {
        if (!Schema::hasTable('proventos')) return response()->json([])->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        $perfil = strtoupper(trim($u->perfil ?? ''));

        $q = DB::table('proventos')
            ->leftJoin('funcionarios', 'proventos.funcionario_id', '=', 'funcionarios.id')
            ->leftJoin('unidades', 'proventos.unidade_id', '=', 'unidades.id')
            ->leftJoin('usuarios as criador', 'proventos.criado_por', '=', 'criador.id')
            ->leftJoin('usuarios as autorizador', 'proventos.autorizado_por', '=', 'autorizador.id')
            ->select('proventos.*', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.cpf as funcionario_cpf', $proventoSelectFuncionarioPix(),
                'unidades.nome as unidade_nome', $proventoSelectUnidadeCnpj(), 'criador.nome as criado_por_nome', 'autorizador.nome as autorizado_por_nome');

        if ($nome = trim($request->query('nome', ''))) {
            $q->where('funcionarios.nome_completo', 'like', '%' . $nome . '%');
        }
        if ($funcionarioFiltroId = $request->query('funcionario_id')) {
            $q->where('proventos.funcionario_id', (int) $funcionarioFiltroId);
        }
        if ($cpf = preg_replace('/\D/', '', trim($request->query('cpf', '')))) $q->whereRaw('REPLACE(REPLACE(REPLACE(funcionarios.cpf, ".", ""), "-", ""), " ", "") LIKE ?', ['%' . $cpf . '%']);
        if ($tipo = trim($request->query('tipo', ''))) $q->where('proventos.tipo', $tipo);
        if ($verba = trim($request->query('verba', ''))) $q->where('proventos.verba', 'like', '%' . $verba . '%');
        if ($unidadeId = $request->query('unidade_id')) $q->where('proventos.unidade_id', $unidadeId);
        if ($status = trim($request->query('status', ''))) $q->where('proventos.status', $status);
        if ($criadoPor = $request->query('criado_por')) $q->where('proventos.criado_por', $criadoPor);
        if ($autorizadoPor = $request->query('autorizado_por')) $q->where('proventos.autorizado_por', $autorizadoPor);
        if ($dataInicio = $request->query('data_inicio')) $q->whereDate('proventos.data_provento', '>=', $dataInicio);
        if ($dataFim = $request->query('data_fim')) $q->whereDate('proventos.data_provento', '<=', $dataFim);

        // Sem permissão para lançar: vê apenas os próprios proventos
        if (!$podeCriarProvento($perfil)) {
            $funcId = DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
            if ($funcId) {
                $q->where('proventos.funcionario_id', $funcId);
            } else {
                return response()->json([])->header('Access-Control-Allow-Origin', '*');
            }
        }

        $lista = $q->orderByDesc('proventos.id')->get();
        if (!$podeCriarProvento($perfil)) {
            foreach ($lista as $row) {
                $row->observacao_interna = null;
            }
        }
        return response()->json($lista)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /proventos: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao listar proventos'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

// ============================================
// RECIBO AJUDA DE CUSTO (Módulo Financeiro)
// ============================================

$recibosAjudaCors = fn() => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform');
Route::options('/recibos-ajuda', $recibosAjudaCors);
Route::options('/recibos-ajuda/{id}', $recibosAjudaCors);
Route::options('/recibos-ajuda/{id}/pdf', $recibosAjudaCors);

$podeCriarReciboAjuda = function ($perfil) {
    $p = strtoupper(trim($perfil ?? ''));
    return in_array($p, ['ADMIN', 'GERENTE', 'FINANCEIRO', 'ASSISTENTE_ADMINISTRATIVO']);
};

$reciboAjudaParseDateTime = function ($raw) {
    $v = is_string($raw) ? trim($raw) : '';
    if ($v === '') return null;
    try {
        // Aceita ISO (2026-04-14T12:34:56Z) e também "Y-m-d H:i:s"
        return \Carbon\Carbon::parse($v)->format('Y-m-d H:i:s');
    } catch (\Exception $e) {
        return null;
    }
};

$reciboAjudaParseDate = function ($raw) {
    $v = is_string($raw) ? trim($raw) : '';
    if ($v === '') return null;
    try {
        return \Carbon\Carbon::parse($v)->format('Y-m-d');
    } catch (\Exception $e) {
        return null;
    }
};

$reciboAjudaHmacKey = function () {
    $k = (string) config('app.key', '');
    if (str_starts_with($k, 'base64:')) {
        $b64 = substr($k, 7);
        $dec = base64_decode($b64, true);
        if ($dec !== false) return $dec;
    }
    return $k;
};
$reciboAjudaMakeHash = function ($payload) use ($reciboAjudaHmacKey) {
    $key = $reciboAjudaHmacKey();
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return hash_hmac('sha256', $json ?: '', $key ?: 'sas-recibo-ajuda');
};

$reciboAjudaFinalidadeLabels = [
    'auxilio_combustivel' => 'Auxílio Combustível',
    'ajuda_custo' => 'Auxílio Alimentação (Cartão iFood)',
    'transporte' => 'Transporte',
    'alimentacao' => 'Alimentação(Cartão iFood)',
    // compatibilidade (registros antigos podem ter sido salvos com esta chave)
    'auxilio_alimentacao_ifood' => 'Auxílio Alimentação (Cartão iFood)',
    'outro' => 'Outro',
];

$reciboAjudaParseFinalidades = function ($raw) {
    // Aceita:
    // - legado: string (ex.: "transporte")
    // - novo: array (ex.: ["transporte","alimentacao"])
    // - novo com valores: array (ex.: [{"k":"transporte","v":10.5},{"k":"alimentacao","v":20}])
    // - novo armazenado: JSON string (ex.: '["transporte","alimentacao"]')
    if (is_array($raw)) {
        $arr = $raw;
    } else {
        $s = is_string($raw) ? trim($raw) : '';
        if ($s === '') return [];
        $arr = null;
        if (str_starts_with($s, '[') && str_ends_with($s, ']')) {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) $arr = $decoded;
        }
        if (!is_array($arr)) $arr = [$s];
    }
    $out = [];
    foreach ($arr as $v) {
        if (is_array($v)) {
            $k = trim((string) ($v['k'] ?? $v['key'] ?? $v['codigo'] ?? $v['finalidade'] ?? ''));
            if ($k === '') {
                continue;
            }
            $out[$k] = true;
            continue;
        }
        $t = trim((string) ($v ?? ''));
        if ($t === '') {
            continue;
        }
        $out[$t] = true;
    }
    return array_keys($out);
};

$reciboAjudaFormatFinalidades = function ($raw) use ($reciboAjudaFinalidadeLabels) {
    $items = [];
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $s = is_string($raw) ? trim($raw) : '';
        if ($s === '') {
            return '';
        }
        if (str_starts_with($s, '[') && str_ends_with($s, ']')) {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }
        if (!$items) {
            $items = [$s];
        }
    }

    $parts = [];
    foreach ($items as $v) {
        if (is_array($v)) {
            $k = trim((string) ($v['k'] ?? $v['key'] ?? $v['codigo'] ?? $v['finalidade'] ?? ''));
            if ($k === '') {
                continue;
            }
            $lbl = $reciboAjudaFinalidadeLabels[$k] ?? $k;
            $vv = $v['v'] ?? $v['valor'] ?? $v['value'] ?? null;
            $n = is_numeric($vv) ? (float) $vv : null;
            if ($n !== null && $n > 0) {
                $parts[] = $lbl . ': R$ ' . number_format($n, 2, ',', '.');
            } else {
                $parts[] = $lbl;
            }
            continue;
        }
        $t = trim((string) ($v ?? ''));
        if ($t === '') {
            continue;
        }
        $parts[] = $reciboAjudaFinalidadeLabels[$t] ?? $t;
    }
    return implode(', ', $parts);
};

$reciboAjudaValorFromFinalidades = function ($raw) {
    if (!is_array($raw)) {
        return null;
    }
    $sum = 0.0;
    $has = false;
    foreach ($raw as $v) {
        if (!is_array($v)) {
            continue;
        }
        $vv = $v['v'] ?? $v['valor'] ?? $v['value'] ?? null;
        if (!is_numeric($vv)) {
            continue;
        }
        $has = true;
        $sum += (float) $vv;
    }
    return $has ? $sum : null;
};

Route::get('/recibos-ajuda', function (Request $request) use ($proventosAuth, $podeCriarReciboAjuda, $proventoSelectUnidadeCnpj) {
    try {
        if (!Schema::hasTable('recibos_ajuda_custo')) return response()->json([])->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        $perfil = strtoupper(trim($u->perfil ?? ''));

        $q = DB::table('recibos_ajuda_custo as r')
            ->leftJoin('funcionarios', 'r.funcionario_id', '=', 'funcionarios.id')
            ->leftJoin('unidades', 'r.unidade_id', '=', 'unidades.id')
            ->leftJoin('usuarios as criador', 'r.criado_por', '=', 'criador.id')
            ->select(
                'r.*',
                'funcionarios.nome_completo as funcionario_nome',
                'funcionarios.cpf as funcionario_cpf',
                'unidades.nome as unidade_nome',
                $proventoSelectUnidadeCnpj(),
                'criador.nome as criado_por_nome'
            );

        // Se não pode criar (ex.: FUNCIONARIO): vê apenas os próprios recibos
        if (!$podeCriarReciboAjuda($perfil)) {
            $funcId = DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
            if ($funcId) $q->where('r.funcionario_id', $funcId);
            else return response()->json([])->header('Access-Control-Allow-Origin', '*');
        }

        $lista = $q->orderByDesc('r.id')->get();
        foreach ($lista as $row) {
            $raw = $row->finalidade ?? null;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $row->finalidade = is_array($decoded) ? $decoded : $raw;
            }
        }
        return response()->json($lista)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /recibos-ajuda: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao listar recibos'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/recibos-ajuda/{id}', function (Request $request, $id) use ($proventosAuth, $podeCriarReciboAjuda, $proventoSelectUnidadeCnpj) {
    try {
        if (!Schema::hasTable('recibos_ajuda_custo')) return response()->json(['error' => 'Módulo não configurado'], 404)->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        $perfil = strtoupper(trim($u->perfil ?? ''));

        $q = DB::table('recibos_ajuda_custo as r')
            ->leftJoin('funcionarios', 'r.funcionario_id', '=', 'funcionarios.id')
            ->leftJoin('unidades', 'r.unidade_id', '=', 'unidades.id')
            ->leftJoin('usuarios as criador', 'r.criado_por', '=', 'criador.id')
            ->where('r.id', $id)
            ->select(
                'r.*',
                'funcionarios.nome_completo as funcionario_nome',
                'funcionarios.cpf as funcionario_cpf',
                'unidades.nome as unidade_nome',
                $proventoSelectUnidadeCnpj(),
                'criador.nome as criado_por_nome'
            );

        if (!$podeCriarReciboAjuda($perfil)) {
            $funcId = DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
            if (!$funcId) return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
            $q->where('r.funcionario_id', $funcId);
        }

        $row = $q->first();
        if (!$row) return response()->json(['error' => 'Recibo não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        $raw = $row->finalidade ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $row->finalidade = is_array($decoded) ? $decoded : $raw;
        }
        return response()->json($row)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /recibos-ajuda/{id}: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao buscar recibo'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::post('/recibos-ajuda', function (Request $request) use ($proventosAuth, $podeCriarReciboAjuda, $proventoSelectUnidadeCnpj, $reciboAjudaParseDateTime, $reciboAjudaParseDate, $reciboAjudaMakeHash, $reciboAjudaParseFinalidades, $reciboAjudaValorFromFinalidades) {
    try {
        if (!Schema::hasTable('recibos_ajuda_custo')) return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');

        $perfil = strtoupper(trim($u->perfil ?? ''));
        // Quem não pode criar só pode criar para si mesmo (funcionario_id derivado do usuário)
        $body = $request->json()->all() ?: [];
        $funcionarioId = (int) ($body['funcionario_id'] ?? 0);
        if (!$podeCriarReciboAjuda($perfil)) {
            $funcId = (int) DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
            if (!$funcId) return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
            $funcionarioId = $funcId;
        }

        $finalidadeRaw = $body['finalidade'] ?? null;
        $finalidades = $reciboAjudaParseFinalidades($finalidadeRaw);
        $competencia = trim((string) ($body['competencia'] ?? ''));
        $valor = (float) ($body['valor'] ?? 0);
        $assinaturaTipo = strtolower(trim((string) ($body['assinatura_tipo'] ?? 'desenho')));
        if (!in_array($assinaturaTipo, ['desenho', 'codigo'])) $assinaturaTipo = 'desenho';

        if (!$funcionarioId) return response()->json(['error' => 'Funcionário obrigatório'], 422)->header('Access-Control-Allow-Origin', '*');
        if (!$finalidades) return response()->json(['error' => 'Finalidade obrigatória'], 422)->header('Access-Control-Allow-Origin', '*');

        $finalidadeStoreArr = null;
        if (is_array($finalidadeRaw) && $finalidadeRaw) {
            $temObj = false;
            foreach ($finalidadeRaw as $it) {
                if (is_array($it)) {
                    $temObj = true;
                    break;
                }
            }
            if ($temObj) {
                $norm = [];
                foreach ($finalidadeRaw as $it) {
                    if (!is_array($it)) {
                        continue;
                    }
                    $k = trim((string) ($it['k'] ?? $it['key'] ?? $it['codigo'] ?? $it['finalidade'] ?? ''));
                    if ($k === '') {
                        continue;
                    }
                    $vv = $it['v'] ?? $it['valor'] ?? $it['value'] ?? null;
                    if (!is_numeric($vv) || (float) $vv <= 0) {
                        return response()->json(['error' => 'Valor inválido para finalidade: ' . $k], 422)->header('Access-Control-Allow-Origin', '*');
                    }
                    $norm[] = ['k' => $k, 'v' => (float) $vv];
                }
                if (!$norm) {
                    return response()->json(['error' => 'Finalidade obrigatória'], 422)->header('Access-Control-Allow-Origin', '*');
                }
                $finalidadeStoreArr = $norm;
                $sum = $reciboAjudaValorFromFinalidades($norm);
                if ($sum !== null) {
                    $valor = (float) $sum;
                }
            }
        }

        if ($valor <= 0) return response()->json(['error' => 'Valor inválido'], 422)->header('Access-Control-Allow-Origin', '*');

        $finalidadeStore = $finalidadeStoreArr !== null
            ? json_encode(array_values($finalidadeStoreArr), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : json_encode(array_values($finalidades), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $insert = [
            'funcionario_id' => $funcionarioId,
            'unidade_id' => $body['unidade_id'] ?? null,
            'competencia' => $competencia ?: null,
            'data_pagamento' => $reciboAjudaParseDate($body['data_pagamento'] ?? null),
            'data_geracao' => $reciboAjudaParseDateTime($body['data_geracao'] ?? null) ?? now()->format('Y-m-d H:i:s'),
            'finalidade' => $finalidadeStore,
            'valor' => $valor,
            'assinatura_tipo' => $assinaturaTipo,
            'assinatura_hash' => null,
            'confirmado_em' => $assinaturaTipo === 'desenho' ? $reciboAjudaParseDateTime($body['confirmado_em'] ?? null) : null,
            'ip_publico' => !empty($body['ip_publico']) ? $body['ip_publico'] : null,
            'geo' => !empty($body['geo']) ? $body['geo'] : null,
            'assinatura_data_url' => $assinaturaTipo === 'desenho' && !empty($body['assinatura_data_url']) ? $body['assinatura_data_url'] : null,
            'foto_data_url' => !empty($body['foto_data_url']) ? $body['foto_data_url'] : null,
            'criado_por' => $u->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('recibos_ajuda_custo', 'descricao')) {
            $dDesc = trim((string) ($body['descricao'] ?? ''));
            $insert['descricao'] = $dDesc === '' ? null : mb_substr($dDesc, 0, 4000);
        }

        $id = DB::table('recibos_ajuda_custo')->insertGetId($insert);

        // Se modo código: gera hash verificável no servidor e salva
        if ($assinaturaTipo === 'codigo' && Schema::hasColumn('recibos_ajuda_custo', 'assinatura_hash')) {
            $finalHash = json_decode($insert['finalidade'] ?? '[]', true);
            if (!is_array($finalHash)) {
                $finalHash = array_values($finalidades);
            }
            $payload = [
                'id' => (int) $id,
                'funcionario_id' => (int) $funcionarioId,
                'unidade_id' => $insert['unidade_id'] ? (int) $insert['unidade_id'] : null,
                'competencia' => $insert['competencia'],
                'data_pagamento' => $insert['data_pagamento'],
                'data_geracao' => $insert['data_geracao'],
                'finalidade' => $finalHash,
                'valor' => (string) $insert['valor'],
                'ip_publico' => $insert['ip_publico'],
                'geo' => $insert['geo'],
            ];
            if (Schema::hasColumn('recibos_ajuda_custo', 'descricao')) {
                $payload['descricao'] = $insert['descricao'] ?? null;
            }
            $hash = $reciboAjudaMakeHash($payload);
            DB::table('recibos_ajuda_custo')->where('id', $id)->update(['assinatura_hash' => $hash]);
        }
        $row = DB::table('recibos_ajuda_custo as r')
            ->leftJoin('funcionarios', 'r.funcionario_id', '=', 'funcionarios.id')
            ->leftJoin('unidades', 'r.unidade_id', '=', 'unidades.id')
            ->where('r.id', $id)
            ->select('r.*', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.cpf as funcionario_cpf', 'unidades.nome as unidade_nome', $proventoSelectUnidadeCnpj())
            ->first();
        if ($row) {
            $raw = $row->finalidade ?? null;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $row->finalidade = is_array($decoded) ? $decoded : $raw;
            }
        }
        return response()->json($row)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('POST /recibos-ajuda: ' . $e->getMessage());
        $debug = trim((string) $request->header('X-Debug', '')) === '1';
        return response()->json([
            'error' => 'Erro ao salvar recibo',
            'details' => $debug ? $e->getMessage() : null,
        ], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::put('/recibos-ajuda/{id}', function (Request $request, $id) use ($proventosAuth, $podeCriarReciboAjuda, $proventoSelectUnidadeCnpj, $reciboAjudaParseDateTime, $reciboAjudaParseDate, $reciboAjudaMakeHash, $reciboAjudaParseFinalidades, $reciboAjudaValorFromFinalidades) {
    try {
        if (!Schema::hasTable('recibos_ajuda_custo')) return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        $perfil = strtoupper(trim($u->perfil ?? ''));

        $r = DB::table('recibos_ajuda_custo')->where('id', $id)->first();
        if (!$r) return response()->json(['error' => 'Recibo não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');

        if (!$podeCriarReciboAjuda($perfil)) {
            $funcId = (int) DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
            if (!$funcId || (int) $r->funcionario_id !== $funcId) return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $body = $request->json()->all() ?: [];
        $assinaturaTipo = array_key_exists('assinatura_tipo', $body) ? strtolower(trim((string) ($body['assinatura_tipo'] ?? ''))) : ($r->assinatura_tipo ?? 'desenho');
        if (!in_array($assinaturaTipo, ['desenho', 'codigo'])) $assinaturaTipo = 'desenho';
        $finalidadeRawUp = array_key_exists('finalidade', $body) ? ($body['finalidade'] ?? null) : null;
        $finalidadesUp = array_key_exists('finalidade', $body) ? $reciboAjudaParseFinalidades($finalidadeRawUp) : null;
        if (array_key_exists('finalidade', $body) && !$finalidadesUp) {
            return response()->json(['error' => 'Finalidade obrigatória'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        $up = [
            'unidade_id' => $body['unidade_id'] ?? $r->unidade_id,
            'competencia' => array_key_exists('competencia', $body) ? (trim((string) $body['competencia']) ?: null) : $r->competencia,
            'data_pagamento' => array_key_exists('data_pagamento', $body) ? $reciboAjudaParseDate($body['data_pagamento'] ?? null) : $r->data_pagamento,
            'data_geracao' => array_key_exists('data_geracao', $body) ? ($reciboAjudaParseDateTime($body['data_geracao'] ?? null) ?? $r->data_geracao) : $r->data_geracao,
            'assinatura_tipo' => $assinaturaTipo,
            'finalidade' => $r->finalidade,
            'valor' => $r->valor,
            'confirmado_em' => array_key_exists('confirmado_em', $body) ? ($assinaturaTipo === 'desenho' ? $reciboAjudaParseDateTime($body['confirmado_em'] ?? null) : null) : $r->confirmado_em,
            'ip_publico' => array_key_exists('ip_publico', $body) ? ($body['ip_publico'] ?: null) : $r->ip_publico,
            'geo' => array_key_exists('geo', $body) ? ($body['geo'] ?: null) : $r->geo,
            'assinatura_data_url' => array_key_exists('assinatura_data_url', $body) ? ($assinaturaTipo === 'desenho' ? ($body['assinatura_data_url'] ?: null) : null) : $r->assinatura_data_url,
            'foto_data_url' => array_key_exists('foto_data_url', $body) ? ($body['foto_data_url'] ?: null) : $r->foto_data_url,
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('recibos_ajuda_custo', 'descricao')) {
            if (array_key_exists('descricao', $body)) {
                $dDescUp = trim((string) ($body['descricao'] ?? ''));
                $up['descricao'] = $dDescUp === '' ? null : mb_substr($dDescUp, 0, 4000);
            } else {
                $up['descricao'] = $r->descricao ?? null;
            }
        }
        if (array_key_exists('finalidade', $body)) {
            $finalidadeStoreArr = null;
            if (is_array($finalidadeRawUp) && $finalidadeRawUp) {
                $temObj = false;
                foreach ($finalidadeRawUp as $it) {
                    if (is_array($it)) {
                        $temObj = true;
                        break;
                    }
                }
                if ($temObj) {
                    $norm = [];
                    foreach ($finalidadeRawUp as $it) {
                        if (!is_array($it)) {
                            continue;
                        }
                        $k = trim((string) ($it['k'] ?? $it['key'] ?? $it['codigo'] ?? $it['finalidade'] ?? ''));
                        if ($k === '') {
                            continue;
                        }
                        $vv = $it['v'] ?? $it['valor'] ?? $it['value'] ?? null;
                        if (!is_numeric($vv) || (float) $vv <= 0) {
                            return response()->json(['error' => 'Valor inválido para finalidade: ' . $k], 422)->header('Access-Control-Allow-Origin', '*');
                        }
                        $norm[] = ['k' => $k, 'v' => (float) $vv];
                    }
                    if (!$norm) {
                        return response()->json(['error' => 'Finalidade obrigatória'], 422)->header('Access-Control-Allow-Origin', '*');
                    }
                    $finalidadeStoreArr = $norm;
                    $sum = $reciboAjudaValorFromFinalidades($norm);
                    if ($sum !== null) {
                        $up['valor'] = (float) $sum;
                    }
                    $up['finalidade'] = json_encode(array_values($finalidadeStoreArr), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $up['finalidade'] = json_encode(array_values($finalidadesUp), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            } else {
                $up['finalidade'] = json_encode(array_values($finalidadesUp), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        if (array_key_exists('valor', $body)) {
            $up['valor'] = (float) $body['valor'];
        }

        DB::table('recibos_ajuda_custo')->where('id', $id)->update($up);

        if ($assinaturaTipo === 'codigo' && Schema::hasColumn('recibos_ajuda_custo', 'assinatura_hash')) {
            $novo = DB::table('recibos_ajuda_custo')->where('id', $id)->first();
            $finalHash = json_decode($novo->finalidade ?? '[]', true);
            if (!is_array($finalHash)) {
                $finalHash = $reciboAjudaParseFinalidades($novo->finalidade ?? null);
            }
            $payload = [
                'id' => (int) $id,
                'funcionario_id' => (int) ($novo->funcionario_id ?? $r->funcionario_id),
                'unidade_id' => $novo->unidade_id ? (int) $novo->unidade_id : null,
                'competencia' => $novo->competencia,
                'data_pagamento' => $novo->data_pagamento,
                'data_geracao' => $novo->data_geracao,
                'finalidade' => $finalHash,
                'valor' => (string) $novo->valor,
                'ip_publico' => $novo->ip_publico,
                'geo' => $novo->geo,
            ];
            if (Schema::hasColumn('recibos_ajuda_custo', 'descricao')) {
                $payload['descricao'] = $novo->descricao ?? null;
            }
            $hash = $reciboAjudaMakeHash($payload);
            DB::table('recibos_ajuda_custo')->where('id', $id)->update(['assinatura_hash' => $hash]);
        }

        $row = DB::table('recibos_ajuda_custo as r')
            ->leftJoin('funcionarios', 'r.funcionario_id', '=', 'funcionarios.id')
            ->leftJoin('unidades', 'r.unidade_id', '=', 'unidades.id')
            ->where('r.id', $id)
            ->select('r.*', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.cpf as funcionario_cpf', 'unidades.nome as unidade_nome', $proventoSelectUnidadeCnpj())
            ->first();
        if ($row) {
            $raw = $row->finalidade ?? null;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $row->finalidade = is_array($decoded) ? $decoded : $raw;
            }
        }
        return response()->json($row)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('PUT /recibos-ajuda/{id}: ' . $e->getMessage());
        $debug = trim((string) $request->header('X-Debug', '')) === '1';
        return response()->json([
            'error' => 'Erro ao atualizar recibo',
            'details' => $debug ? $e->getMessage() : null,
        ], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::delete('/recibos-ajuda/{id}', function (Request $request, $id) use ($proventosAuth, $podeCriarReciboAjuda) {
    try {
        if (!Schema::hasTable('recibos_ajuda_custo')) return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        $perfil = strtoupper(trim($u->perfil ?? ''));

        $r = DB::table('recibos_ajuda_custo')->where('id', $id)->first();
        if (!$r) return response()->json(['error' => 'Recibo não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');

        if (!$podeCriarReciboAjuda($perfil)) {
            $funcId = (int) DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
            if (!$funcId || (int) $r->funcionario_id !== $funcId) return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        DB::table('recibos_ajuda_custo')->where('id', $id)->delete();
        return response()->json(['ok' => true])->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('DELETE /recibos-ajuda/{id}: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao deletar recibo'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/recibos-ajuda/{id}/pdf', function (Request $request, $id) use ($proventosAuth, $podeCriarReciboAjuda, $proventoSelectUnidadeCnpj, $reciboAjudaFormatFinalidades) {
    try {
        if (!Schema::hasTable('recibos_ajuda_custo')) return response()->json(['error' => 'Módulo não configurado'], 404)->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        $perfil = strtoupper(trim($u->perfil ?? ''));

        $q = DB::table('recibos_ajuda_custo as r')
            ->leftJoin('funcionarios', 'r.funcionario_id', '=', 'funcionarios.id')
            ->leftJoin('unidades', 'r.unidade_id', '=', 'unidades.id')
            ->leftJoin('usuarios as criador', 'r.criado_por', '=', 'criador.id')
            ->where('r.id', $id)
            ->select(
                'r.*',
                'funcionarios.nome_completo as funcionario_nome',
                'funcionarios.cpf as funcionario_cpf',
                'unidades.nome as unidade_nome',
                $proventoSelectUnidadeCnpj(),
                'criador.nome as criado_por_nome'
            );
        if (!$podeCriarReciboAjuda($perfil)) {
            $funcId = (int) DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
            if (!$funcId) return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
            $q->where('r.funcionario_id', $funcId);
        }
        $r = $q->first();
        if (!$r) return response()->json(['error' => 'Recibo não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');

        $empresa = '';
        $cnpj = $r->unidade_cnpj ? $r->unidade_cnpj : '';
        $func = $r->funcionario_nome ?: '';
        $cpf = $r->funcionario_cpf ?: '';
        $un = $r->unidade_nome ?: '';
        $competencia = $r->competencia ?: '';
        $dataPagamento = !empty($r->data_pagamento) ? \Carbon\Carbon::parse($r->data_pagamento)->format('d/m/Y') : '';
        $dataGeracao = !empty($r->data_geracao) ? \Carbon\Carbon::parse($r->data_geracao)->format('d/m/Y H:i') : '';
        $finalidade = $reciboAjudaFormatFinalidades($r->finalidade ?? null);
        $descricaoPdf = trim((string) ($r->descricao ?? ''));
        $valor = number_format((float) ($r->valor ?? 0), 2, ',', '.');
        $assinaturaTipo = $r->assinatura_tipo ?? 'desenho';
        $assinaturaHash = $r->assinatura_hash ?? '';
        $evid = [];
        if (!empty($r->confirmado_em)) $evid[] = 'Confirmado em: ' . \Carbon\Carbon::parse($r->confirmado_em)->format('d/m/Y H:i');
        if (!empty($r->ip_publico)) $evid[] = 'IP: ' . $r->ip_publico;
        if (!empty($r->geo)) $evid[] = 'Localização: ' . $r->geo;
        $evidTxt = implode(' • ', $evid);

        $assinaturaImg = !empty($r->assinatura_data_url) ? '<img src="' . $r->assinatura_data_url . '" style="max-width:520px;width:100%;height:auto;display:block;margin-top:8px;" />' : '';
        $fotoImg = !empty($r->foto_data_url) ? '<div style="margin-top:10px;"><div style="font-size:12px;color:#555;margin-bottom:4px;">Foto (evidência)</div><img src="' . $r->foto_data_url . '" style="max-width:520px;width:100%;height:auto;display:block;border:1px solid #ddd;border-radius:10px;" /></div>' : '';

        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8" />'
            . '<style>body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:24px}.top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;border-bottom:1px solid #ddd;padding-bottom:12px;margin-bottom:18px}.brand{font-weight:700;font-size:18px}.meta{font-size:12px;color:#444;text-align:right}h1{font-size:18px;margin:0 0 10px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 18px;margin-top:8px}.field{font-size:13px}.lbl{color:#555;font-size:12px}.val{font-weight:600}.box{border:1px solid #ddd;border-radius:10px;padding:12px}.text{margin-top:14px;font-size:13px;line-height:1.45}.sign{margin-top:10px}</style></head><body>';

        $html .= '<div class="top"><div><div class="brand">' . e($un ?: '-') . '</div>'
            . '<div style="font-size:12px;color:#444;margin-top:4px;">CNPJ: ' . e($cnpj ?: '-') . '</div></div>'
            . '<div class="meta"><div><strong>Gerado em:</strong> ' . e($dataGeracao ?: now()->format('d/m/Y H:i')) . '</div>'
            . '<div><strong>Competência:</strong> ' . e($competencia ?: '-') . '</div></div></div>';

        $html .= '<h1>Recibo de ajuda de custo</h1><div class="box"><div class="grid">'
            . '<div class="field"><div class="lbl">Funcionário</div><div class="val">' . e($func ?: '-') . '</div></div>'
            . '<div class="field"><div class="lbl">CPF</div><div class="val">' . e($cpf ?: '-') . '</div></div>'
            . '<div class="field"><div class="lbl">Valor</div><div class="val">R$ ' . e($valor) . '</div></div>'
            . '<div class="field" style="grid-column:1 / -1;"><div class="lbl">Finalidade</div><div class="val">' . e($finalidade ?: '-') . '</div></div>'
            . ($descricaoPdf !== '' ? '<div class="field" style="grid-column:1 / -1;"><div class="lbl">Descrição</div><div class="val">' . nl2br(e($descricaoPdf)) . '</div></div>' : '')
            . '<div class="field"><div class="lbl">Data de pagamento</div><div class="val">' . e($dataPagamento ?: '-') . '</div></div>'
            . '</div><div class="text"><strong>Declaro que recebi o valor acima e confirmo as informações.</strong><br />'
            . 'Declaro, para os devidos fins, que recebi da empresa acima identificada o valor informado a título de <strong>ajuda de custo</strong>, referente à competência indicada.</div>';

        if ($assinaturaTipo === 'codigo' && $assinaturaHash) {
            $html .= '<div style="margin-top:12px;font-size:12px;color:#444;"><strong>Código de verificação:</strong> ' . e($assinaturaHash) . '</div>';
        }

        if (!empty($evidTxt)) {
            $html .= '<div style="margin-top:12px;font-size:12px;color:#444;"><strong>Evidências:</strong> ' . e($evidTxt) . '</div>';
        }
        $assinaturaBlock = $assinaturaTipo === 'codigo'
            ? '<div style="height:130px;"></div>'
              . '<div style="font-size:12px;color:#333;margin-bottom:6px;">Assinatura do funcionário</div>'
              . '<div style="border-top:1px solid #111;"></div>'
            : '<div style="font-size:12px;color:#333;margin-bottom:6px;">Assinatura do funcionário</div>' . $assinaturaImg;

        $html .= '</div><div class="sign">' . $assinaturaBlock . $fotoImg . '</div></body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->setIsRemoteEnabled(true);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfOutput = $dompdf->output();
        $fn = 'recibo-ajuda-' . $id . '.pdf';

        return response($pdfOutput, 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $fn . '"')
            ->header('Content-Length', (string) strlen($pdfOutput));
    } catch (\Exception $e) {
        \Log::error('GET /recibos-ajuda/{id}/pdf: ' . $e->getMessage());
        $debug = trim((string) $request->header('X-Debug', '')) === '1';
        return response()->json([
            'error' => 'Erro ao gerar PDF',
            'details' => $debug ? $e->getMessage() : null,
        ], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

// ============================================
// DESPESAS FIXAS (Financeiro)
// ============================================

$despesasFixasCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Debug, X-Device-Model, X-Device-Platform');

Route::options('/despesas-fixas/categorias', $despesasFixasCors);
Route::options('/despesas-fixas/categorias/{id}', $despesasFixasCors);
Route::options('/despesas-fixas', $despesasFixasCors);
Route::options('/despesas-fixas/{id}', $despesasFixasCors);

$despFixasPodeGerir = function ($perfil) {
    $p = strtoupper(trim($perfil ?? ''));

    return in_array($p, ['ADMIN', 'GERENTE', 'FINANCEIRO', 'ASSISTENTE_ADMINISTRATIVO'], true);
};

$despFixasParseUnidadeIds = function ($raw) {
    if ($raw === null || $raw === '') {
        return [];
    }
    if (is_array($raw)) {
        return array_values(array_unique(array_filter(array_map('intval', $raw))));
    }
    if (is_string($raw)) {
        $d = json_decode($raw, true);

        return is_array($d) ? array_values(array_unique(array_filter(array_map('intval', $d)))) : [];
    }

    return [];
};

$despFixasLabelUnidades = function ($aplicaTodas, $ids) {
    if ($aplicaTodas) {
        return 'Todas as unidades';
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids ?: []))));
    if ($ids === []) {
        return '—';
    }
    if (!Schema::hasTable('unidades')) {
        return implode(', ', $ids);
    }
    $rows = DB::table('unidades')->whereIn('id', $ids)->pluck('nome', 'id');
    $parts = [];
    foreach ($ids as $i) {
        if ($rows->has($i)) {
            $parts[] = (string) $rows[$i];
        }
    }

    return $parts !== [] ? implode(', ', $parts) : '—';
};

$despFixasHidratarLinha = function ($row) use ($despFixasLabelUnidades) {
    $raw = $row->unidade_ids ?? null;
    if (is_string($raw)) {
        $uids = json_decode($raw, true);
    } elseif (is_array($raw)) {
        $uids = $raw;
    } else {
        $uids = [];
    }
    if (!is_array($uids)) {
        $uids = [];
    }
    $row->unidade_ids = array_values(array_unique(array_filter(array_map('intval', $uids))));
    $row->unidades_label = $despFixasLabelUnidades((bool) ($row->aplica_todas_unidades ?? false), $row->unidade_ids);

    return $row;
};

Route::get('/despesas-fixas/categorias', function (Request $request) use ($proventosAuth, $despFixasPodeGerir) {
    try {
        if (!Schema::hasTable('despesas_fixas_categorias')) {
            return response()->json([])->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (!$u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (!$despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        $q = DB::table('despesas_fixas_categorias');
        if ($request->query('inativos') !== '1') {
            $q->where('ativo', 1);
        }
        $lista = $q->orderByDesc('ativo')->orderBy('ordem')->orderBy('nome')->get();

        return response()->json($lista)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /despesas-fixas/categorias: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao listar categorias'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::post('/despesas-fixas/categorias', function (Request $request) use ($proventosAuth, $despFixasPodeGerir) {
    try {
        if (!Schema::hasTable('despesas_fixas_categorias')) {
            return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (!$u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (!$despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        $body = $request->json()->all() ?: [];
        $nome = trim((string) ($body['nome'] ?? ''));
        if ($nome === '') {
            return response()->json(['error' => 'Nome da categoria obrigatório'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        $nome = mb_substr($nome, 0, 120);
        $dup = DB::table('despesas_fixas_categorias')
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($nome, 'UTF-8')])
            ->exists();
        if ($dup) {
            return response()->json(['error' => 'Já existe uma categoria com este nome'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        $ordem = (int) (DB::table('despesas_fixas_categorias')->max('ordem') ?? 0) + 10;
        $id = DB::table('despesas_fixas_categorias')->insertGetId([
            'nome' => $nome,
            'ordem' => $ordem,
            'ativo' => true,
            'criado_por' => $u->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = DB::table('despesas_fixas_categorias')->where('id', $id)->first();

        return response()->json($row)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('POST /despesas-fixas/categorias: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao criar categoria'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::put('/despesas-fixas/categorias/{id}', function (Request $request, $id) use ($proventosAuth, $despFixasPodeGerir) {
    try {
        if (!Schema::hasTable('despesas_fixas_categorias')) {
            return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (!$u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (!$despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        $row = DB::table('despesas_fixas_categorias')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['error' => 'Categoria não encontrada'], 404)->header('Access-Control-Allow-Origin', '*');
        }
        $body = $request->json()->all() ?: [];
        $nome = array_key_exists('nome', $body) ? trim((string) $body['nome']) : (string) ($row->nome ?? '');
        if ($nome === '') {
            return response()->json(['error' => 'Nome obrigatório'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        $nome = mb_substr($nome, 0, 120);
        $dup = DB::table('despesas_fixas_categorias')
            ->where('id', '!=', (int) $id)
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($nome, 'UTF-8')])
            ->exists();
        if ($dup) {
            return response()->json(['error' => 'Já existe uma categoria com este nome'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        $ativo = array_key_exists('ativo', $body) ? (bool) $body['ativo'] : (bool) ($row->ativo ?? true);
        DB::table('despesas_fixas_categorias')->where('id', $id)->update([
            'nome' => $nome,
            'ativo' => $ativo ? 1 : 0,
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('despesas_fixas_categorias')->where('id', $id)->first())->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('PUT /despesas-fixas/categorias/{id}: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao atualizar categoria'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::delete('/despesas-fixas/categorias/{id}', function (Request $request, $id) use ($proventosAuth, $despFixasPodeGerir) {
    try {
        if (!Schema::hasTable('despesas_fixas_categorias')) {
            return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (!$u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (!$despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        if (!DB::table('despesas_fixas_categorias')->where('id', $id)->exists()) {
            return response()->json(['error' => 'Categoria não encontrada'], 404)->header('Access-Control-Allow-Origin', '*');
        }
        if (Schema::hasTable('despesas_fixas') && DB::table('despesas_fixas')->where('categoria_id', $id)->exists()) {
            return response()->json(['error' => 'Não é possível excluir: existem despesas usando esta categoria'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        DB::table('despesas_fixas_categorias')->where('id', $id)->delete();

        return response()->json(['ok' => true])->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('DELETE /despesas-fixas/categorias/{id}: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao excluir categoria'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/despesas-fixas', function (Request $request) use ($proventosAuth, $despFixasPodeGerir, $despFixasHidratarLinha) {
    try {
        if (!Schema::hasTable('despesas_fixas')) {
            return response()->json([])->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (!$u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (!$despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        $q = DB::table('despesas_fixas as d')
            ->leftJoin('despesas_fixas_categorias as c', 'd.categoria_id', '=', 'c.id');
        if (Schema::hasTable('usuarios')) {
            $q->leftJoin('usuarios as criador', 'd.criado_por', '=', 'criador.id')
                ->select('d.*', 'c.nome as categoria_nome', 'criador.nome as criado_por_nome');
        } else {
            $q->select('d.*', 'c.nome as categoria_nome', DB::raw('NULL as criado_por_nome'));
        }

        $catF = trim((string) $request->query('categoria_id', ''));
        if ($catF !== '' && is_numeric($catF)) {
            $q->where('d.categoria_id', (int) $catF);
        }
        $stF = strtolower(trim((string) $request->query('status', '')));
        if (in_array($stF, ['ativo', 'pausado'], true)) {
            $q->where('d.status', $stF);
        }
        $uF = trim((string) $request->query('unidade_id', ''));
        if ($uF !== '' && is_numeric($uF)) {
            $uid = (int) $uF;
            $q->where(function ($sub) use ($uid) {
                $sub->where('d.aplica_todas_unidades', 1)
                    ->orWhereJsonContains('d.unidade_ids', $uid);
            });
        }

        $lista = $q->orderByDesc('d.id')->get();
        foreach ($lista as $row) {
            $despFixasHidratarLinha($row);
        }

        return response()->json($lista)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /despesas-fixas: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao listar despesas'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/despesas-fixas/{id}', function (Request $request, $id) use ($proventosAuth, $despFixasPodeGerir, $despFixasHidratarLinha) {
    try {
        if (!Schema::hasTable('despesas_fixas')) {
            return response()->json(['error' => 'Módulo não configurado'], 404)->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (!$u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (!$despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        $qOne = DB::table('despesas_fixas as d')
            ->leftJoin('despesas_fixas_categorias as c', 'd.categoria_id', '=', 'c.id')
            ->where('d.id', $id);
        if (Schema::hasTable('usuarios')) {
            $qOne->leftJoin('usuarios as criador', 'd.criado_por', '=', 'criador.id')
                ->select('d.*', 'c.nome as categoria_nome', 'criador.nome as criado_por_nome');
        } else {
            $qOne->select('d.*', 'c.nome as categoria_nome', DB::raw('NULL as criado_por_nome'));
        }
        $row = $qOne->first();
        if (!$row) {
            return response()->json(['error' => 'Registro não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }
        $despFixasHidratarLinha($row);

        return response()->json($row)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /despesas-fixas/{id}: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao buscar despesa'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::post('/despesas-fixas', function (Request $request) use ($proventosAuth, $despFixasPodeGerir, $despFixasParseUnidadeIds, $despFixasHidratarLinha) {
    try {
        if (!Schema::hasTable('despesas_fixas')) {
            return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (!$u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (!$despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        $body = $request->json()->all() ?: [];
        $nome = trim((string) ($body['nome'] ?? ''));
        $catId = (int) ($body['categoria_id'] ?? 0);
        $valor = (float) ($body['valor'] ?? 0);
        $dia = (int) ($body['dia_vencimento'] ?? 0);
        $fornecedor = trim((string) ($body['fornecedor'] ?? ''));
        $obs = trim((string) ($body['observacoes'] ?? $body['obs'] ?? ''));
        $status = strtolower(trim((string) ($body['status'] ?? 'ativo')));
        if (!in_array($status, ['ativo', 'pausado'], true)) {
            $status = 'ativo';
        }
        $aplicaTodas = !empty($body['aplica_todas_unidades']);
        $uids = $despFixasParseUnidadeIds($body['unidade_ids'] ?? []);

        if ($nome === '' || mb_strlen($nome) > 180) {
            return response()->json(['error' => 'Nome inválido'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        if ($catId <= 0 || !DB::table('despesas_fixas_categorias')->where('id', $catId)->where('ativo', 1)->exists()) {
            return response()->json(['error' => 'Categoria inválida'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        if ($valor <= 0) {
            return response()->json(['error' => 'Valor inválido'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        if ($dia < 1 || $dia > 28) {
            return response()->json(['error' => 'Dia de vencimento deve ser entre 1 e 28'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        if (!$aplicaTodas && $uids === []) {
            return response()->json(['error' => 'Selecione ao menos uma unidade ou marque todas'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        if (!$aplicaTodas && Schema::hasTable('unidades')) {
            $n = DB::table('unidades')->whereIn('id', $uids)->count();
            if ($n !== count(array_unique($uids))) {
                return response()->json(['error' => 'Unidade inválida na lista'], 422)->header('Access-Control-Allow-Origin', '*');
            }
        }

        $id = DB::table('despesas_fixas')->insertGetId([
            'nome' => $nome,
            'categoria_id' => $catId,
            'valor' => $valor,
            'dia_vencimento' => $dia,
            'fornecedor' => $fornecedor !== '' ? mb_substr($fornecedor, 0, 160) : null,
            'observacoes' => $obs !== '' ? mb_substr($obs, 0, 2000) : null,
            'status' => $status,
            'aplica_todas_unidades' => $aplicaTodas ? 1 : 0,
            'unidade_ids' => json_encode($aplicaTodas ? [] : array_values($uids), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'criado_por' => $u->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = DB::table('despesas_fixas as d')
            ->leftJoin('despesas_fixas_categorias as c', 'd.categoria_id', '=', 'c.id')
            ->where('d.id', $id)
            ->select('d.*', 'c.nome as categoria_nome')
            ->first();
        $despFixasHidratarLinha($row);

        return response()->json($row)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('POST /despesas-fixas: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao salvar despesa'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::put('/despesas-fixas/{id}', function (Request $request, $id) use ($proventosAuth, $despFixasPodeGerir, $despFixasParseUnidadeIds, $despFixasHidratarLinha) {
    try {
        if (!Schema::hasTable('despesas_fixas')) {
            return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (!$u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (!$despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        $r = DB::table('despesas_fixas')->where('id', $id)->first();
        if (!$r) {
            return response()->json(['error' => 'Registro não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }
        $body = $request->json()->all() ?: [];
        $nome = array_key_exists('nome', $body) ? trim((string) $body['nome']) : $r->nome;
        $catId = array_key_exists('categoria_id', $body) ? (int) $body['categoria_id'] : (int) $r->categoria_id;
        $valor = array_key_exists('valor', $body) ? (float) $body['valor'] : (float) $r->valor;
        $dia = array_key_exists('dia_vencimento', $body) ? (int) $body['dia_vencimento'] : (int) $r->dia_vencimento;
        $fornecedor = array_key_exists('fornecedor', $body) ? trim((string) $body['fornecedor']) : (string) ($r->fornecedor ?? '');
        $obs = array_key_exists('observacoes', $body) ? trim((string) $body['observacoes']) : (string) ($r->observacoes ?? '');
        $status = array_key_exists('status', $body) ? strtolower(trim((string) $body['status'])) : strtolower((string) ($r->status ?? 'ativo'));
        if (!in_array($status, ['ativo', 'pausado'], true)) {
            $status = 'ativo';
        }
        $aplicaTodas = array_key_exists('aplica_todas_unidades', $body) ? !empty($body['aplica_todas_unidades']) : (bool) $r->aplica_todas_unidades;
        $uids = array_key_exists('unidade_ids', $body) ? $despFixasParseUnidadeIds($body['unidade_ids']) : $despFixasParseUnidadeIds($r->unidade_ids ?? []);

        if ($nome === '' || mb_strlen($nome) > 180) {
            return response()->json(['error' => 'Nome inválido'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        if ($catId <= 0 || !DB::table('despesas_fixas_categorias')->where('id', $catId)->where('ativo', 1)->exists()) {
            return response()->json(['error' => 'Categoria inválida'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        if ($valor <= 0) {
            return response()->json(['error' => 'Valor inválido'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        if ($dia < 1 || $dia > 28) {
            return response()->json(['error' => 'Dia de vencimento deve ser entre 1 e 28'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        if (!$aplicaTodas && $uids === []) {
            return response()->json(['error' => 'Selecione ao menos uma unidade ou marque todas'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        if (!$aplicaTodas && Schema::hasTable('unidades')) {
            $n = DB::table('unidades')->whereIn('id', $uids)->count();
            if ($n !== count(array_unique($uids))) {
                return response()->json(['error' => 'Unidade inválida na lista'], 422)->header('Access-Control-Allow-Origin', '*');
            }
        }

        DB::table('despesas_fixas')->where('id', $id)->update([
            'nome' => $nome,
            'categoria_id' => $catId,
            'valor' => $valor,
            'dia_vencimento' => $dia,
            'fornecedor' => $fornecedor !== '' ? mb_substr($fornecedor, 0, 160) : null,
            'observacoes' => $obs !== '' ? mb_substr($obs, 0, 2000) : null,
            'status' => $status,
            'aplica_todas_unidades' => $aplicaTodas ? 1 : 0,
            'unidade_ids' => json_encode($aplicaTodas ? [] : array_values($uids), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
        $row = DB::table('despesas_fixas as d')
            ->leftJoin('despesas_fixas_categorias as c', 'd.categoria_id', '=', 'c.id')
            ->where('d.id', $id)
            ->select('d.*', 'c.nome as categoria_nome')
            ->first();
        $despFixasHidratarLinha($row);

        return response()->json($row)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('PUT /despesas-fixas/{id}: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao atualizar despesa'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::delete('/despesas-fixas/{id}', function (Request $request, $id) use ($proventosAuth, $despFixasPodeGerir) {
    try {
        if (!Schema::hasTable('despesas_fixas')) {
            return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (!$u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (!$despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        $n = DB::table('despesas_fixas')->where('id', $id)->delete();
        if (!$n) {
            return response()->json(['error' => 'Registro não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        return response()->json(['ok' => true])->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('DELETE /despesas-fixas/{id}: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao excluir'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

// ============================================
// VALE / CONSUMO (Financeiro — lançamentos por funcionário)
// ============================================

$valeConsumoCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Debug, X-Device-Model, X-Device-Platform');

Route::options('/financeiro/vale-consumo', $valeConsumoCors);
Route::options('/financeiro/vale-consumo/resumo', $valeConsumoCors);
Route::options('/financeiro/vale-consumo/relatorio.csv', $valeConsumoCors);
Route::options('/financeiro/vale-consumo/relatorio.pdf', $valeConsumoCors);
Route::options('/financeiro/vale-consumo/{id}', $valeConsumoCors);

$valeConsumoValidarCompetencia = static function (?string $c): ?string {
    $c = is_string($c) ? trim($c) : '';
    if ($c === '') {
        return null;
    }
    if (! preg_match('/^\d{4}-\d{2}$/', $c)) {
        return null;
    }

    return $c;
};

/** @return array{0: string, 1: string, 2: int} data_inicio, data_fim, unidade_id (0 = todas) */
$valeConsumoResolverPeriodo = static function (Request $request): array {
    $di = trim((string) $request->query('data_inicio', ''));
    $df = trim((string) $request->query('data_fim', ''));
    if ($di === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $di)) {
        $di = now()->startOfMonth()->toDateString();
    }
    if ($df === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) {
        $df = now()->endOfMonth()->toDateString();
    }
    if ($di > $df) {
        [$di, $df] = [$df, $di];
    }
    $unidadeId = $request->filled('unidade_id') ? max(0, (int) $request->query('unidade_id')) : 0;

    return [$di, $df, $unidadeId];
};

Route::get('/financeiro/vale-consumo', function (Request $request) use ($proventosAuth, $despFixasPodeGerir, $valeConsumoResolverPeriodo, $valeConsumoValidarCompetencia) {
    try {
        if (! Schema::hasTable('financeiro_vale_consumo')) {
            return response()->json([])->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (! $u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (! $despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        [$di, $df, $unidadeId] = $valeConsumoResolverPeriodo($request);
        $q = DB::table('financeiro_vale_consumo as v')
            ->join('funcionarios as f', 'v.funcionario_id', '=', 'f.id')
            ->leftJoin('unidades as u', 'f.unidade_id', '=', 'u.id')
            ->select('v.*', 'f.nome_completo as funcionario_nome', 'f.cpf as funcionario_cpf', 'f.cargo as funcionario_cargo', 'u.nome as unidade_nome');
        if (Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')) {
            $q->whereBetween('v.data_lancamento', [$di, $df]);
        } else {
            $comp = $valeConsumoValidarCompetencia($request->query('competencia')) ?? substr($di, 0, 7);
            $q->where('v.competencia', $comp);
        }
        if ($unidadeId > 0) {
            $q->where('f.unidade_id', $unidadeId);
        }
        if (Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')) {
            $q->orderByDesc('v.data_lancamento')->orderByDesc('v.id');
        } else {
            $q->orderBy('f.nome_completo')->orderBy('v.id');
        }

        return response()->json($q->get())->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /financeiro/vale-consumo: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao listar'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/financeiro/vale-consumo/resumo', function (Request $request) use ($proventosAuth, $despFixasPodeGerir, $valeConsumoResolverPeriodo, $valeConsumoValidarCompetencia) {
    try {
        if (! Schema::hasTable('financeiro_vale_consumo')) {
            return response()->json([
                'periodo' => ['data_inicio' => now()->toDateString(), 'data_fim' => now()->toDateString()],
                'linhas' => [],
                'totais' => ['total_vale' => 0, 'total_consumo' => 0],
            ])->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (! $u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (! $despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        [$di, $df, $unidadeId] = $valeConsumoResolverPeriodo($request);
        $q = DB::table('financeiro_vale_consumo as v')
            ->join('funcionarios as f', 'v.funcionario_id', '=', 'f.id')
            ->leftJoin('unidades as u', 'f.unidade_id', '=', 'u.id')
            ->groupBy('v.funcionario_id', 'f.nome_completo', 'f.cpf', 'f.cargo', 'u.nome')
            ->selectRaw('v.funcionario_id, f.nome_completo as funcionario_nome, f.cpf as funcionario_cpf, f.cargo as funcionario_cargo, u.nome as unidade_nome, SUM(v.valor_vale) as total_vale, SUM(v.valor_consumo) as total_consumo, COUNT(*) as qtd_lancamentos')
            ->orderBy('f.nome_completo');
        if (Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')) {
            $q->whereBetween('v.data_lancamento', [$di, $df]);
        } else {
            $comp = $valeConsumoValidarCompetencia($request->query('competencia')) ?? substr($di, 0, 7);
            $q->where('v.competencia', $comp);
        }
        if ($unidadeId > 0) {
            $q->where('f.unidade_id', $unidadeId);
        }
        $linhas = $q->get();
        $totVale = (float) $linhas->sum(fn ($r) => (float) ($r->total_vale ?? 0));
        $totCons = (float) $linhas->sum(fn ($r) => (float) ($r->total_consumo ?? 0));

        return response()->json([
            'periodo' => ['data_inicio' => $di, 'data_fim' => $df, 'unidade_id' => $unidadeId],
            'linhas' => $linhas,
            'totais' => ['total_vale' => $totVale, 'total_consumo' => $totCons],
        ])->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /financeiro/vale-consumo/resumo: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao montar resumo'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/financeiro/vale-consumo/relatorio.csv', function (Request $request) use ($proventosAuth, $despFixasPodeGerir, $valeConsumoResolverPeriodo, $valeConsumoValidarCompetencia) {
    try {
        if (! Schema::hasTable('financeiro_vale_consumo')) {
            return response("Sem dados (tabela não criada — rode migrate).\n", 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Content-Type', 'text/csv; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="vale-consumo.csv"');
        }
        $u = $proventosAuth($request);
        if (! $u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (! $despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        [$di, $df, $unidadeId] = $valeConsumoResolverPeriodo($request);
        $q = DB::table('financeiro_vale_consumo as v')
            ->join('funcionarios as f', 'v.funcionario_id', '=', 'f.id')
            ->leftJoin('unidades as u', 'f.unidade_id', '=', 'u.id')
            ->select('v.*', 'f.nome_completo as funcionario_nome', 'f.cpf as funcionario_cpf', 'f.cargo as funcionario_cargo', 'u.nome as unidade_nome');
        if (Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')) {
            $q->whereBetween('v.data_lancamento', [$di, $df]);
        } else {
            $comp = $valeConsumoValidarCompetencia($request->query('competencia')) ?? substr($di, 0, 7);
            $q->where('v.competencia', $comp);
        }
        if ($unidadeId > 0) {
            $q->where('f.unidade_id', $unidadeId);
        }
        if (Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')) {
            $q->orderByDesc('v.data_lancamento')->orderByDesc('v.id');
        } else {
            $q->orderBy('f.nome_completo')->orderBy('v.id');
        }
        $linhas = $q->get();
        $sep = ';';
        $rows = [];
        $uniLabel = $unidadeId > 0 && Schema::hasTable('unidades')
            ? (string) (DB::table('unidades')->where('id', $unidadeId)->value('nome') ?? ('ID ' . $unidadeId))
            : 'Todas';
        $totVNum = (float) $linhas->sum(fn ($x) => (float) ($x->valor_vale ?? 0));
        $totCNum = (float) $linhas->sum(fn ($x) => (float) ($x->valor_consumo ?? 0));
        $totV = number_format($totVNum, 2, ',', '');
        $totC = number_format($totCNum, 2, ',', '');
        $temDataLanc = Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento');
        $rows[] = 'Relatorio Vale/Consumo — periodo ' . $di . ' a ' . $df . ' — unidade: ' . $uniLabel;
        $rows[] = implode($sep, ['Vale', $totV, 'Consumo', $totC]);
        $rows[] = implode($sep, ['ID', 'Data', 'Funcionario', 'CPF', 'Cargo', 'Unidade', 'Vale', 'Consumo', 'Observacao']);
        foreach ($linhas as $r) {
            $dataStr = '';
            if ($temDataLanc && ! empty($r->data_lancamento)) {
                $dataStr = \Carbon\Carbon::parse($r->data_lancamento)->format('d/m/Y');
            } elseif (! empty($r->competencia)) {
                $dataStr = (string) $r->competencia;
            }
            $rows[] = implode($sep, [
                (string) (int) ($r->id ?? 0),
                '"' . str_replace('"', '""', $dataStr) . '"',
                '"' . str_replace('"', '""', (string) ($r->funcionario_nome ?? '')) . '"',
                '"' . str_replace('"', '""', (string) ($r->funcionario_cpf ?? '')) . '"',
                '"' . str_replace('"', '""', (string) ($r->funcionario_cargo ?? '')) . '"',
                '"' . str_replace('"', '""', (string) ($r->unidade_nome ?? '')) . '"',
                number_format((float) ($r->valor_vale ?? 0), 2, ',', ''),
                number_format((float) ($r->valor_consumo ?? 0), 2, ',', ''),
                '"' . str_replace('"', '""', (string) ($r->observacao ?? '')) . '"',
            ]);
        }
        $rows[] = implode($sep, ['Total', '', '', '', '', '', $totV, $totC, '']);
        $porPessoaCsv = $linhas
            ->groupBy(static fn ($x) => (string) ($x->funcionario_id ?? ''))
            ->map(static function ($grupo) {
                $first = $grupo->first();

                return (object) [
                    'funcionario_nome' => $first->funcionario_nome ?? '',
                    'unidade_nome' => $first->unidade_nome ?? '',
                    'total_vale' => (float) $grupo->sum(static fn ($x) => (float) ($x->valor_vale ?? 0)),
                    'total_consumo' => (float) $grupo->sum(static fn ($x) => (float) ($x->valor_consumo ?? 0)),
                    'qtd_lancamentos' => $grupo->count(),
                ];
            })
            ->sortBy(static fn ($p) => \Illuminate\Support\Str::lower((string) ($p->funcionario_nome ?? '')))
            ->values();
        $rows[] = '';
        $rows[] = 'TOTAL POR FUNCIONARIO';
        $rows[] = implode($sep, ['Funcionario', 'Unidade', 'Vale', 'Consumo']);
        foreach ($porPessoaCsv as $p) {
            $rows[] = implode($sep, [
                '"' . str_replace('"', '""', (string) ($p->funcionario_nome ?? '')) . '"',
                '"' . str_replace('"', '""', (string) ($p->unidade_nome ?? '')) . '"',
                number_format((float) ($p->total_vale ?? 0), 2, ',', ''),
                number_format((float) ($p->total_consumo ?? 0), 2, ',', ''),
            ]);
        }
        $csv = "\xEF\xBB\xBF" . implode("\r\n", $rows) . "\r\n";
        $fn = 'vale-consumo-' . $di . '-' . $df . ($unidadeId > 0 ? '-u' . $unidadeId : '') . '.csv';

        return response($csv, 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $fn . '"');
    } catch (\Exception $e) {
        \Log::error('GET /financeiro/vale-consumo/relatorio.csv: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao gerar CSV'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/financeiro/vale-consumo/relatorio.pdf', function (Request $request) use ($proventosAuth, $despFixasPodeGerir, $valeConsumoResolverPeriodo, $valeConsumoValidarCompetencia) {
    try {
        if (! Schema::hasTable('financeiro_vale_consumo')) {
            return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (! $u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (! $despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        [$di, $df, $unidadeId] = $valeConsumoResolverPeriodo($request);
        $uniLabel = $unidadeId > 0 && Schema::hasTable('unidades')
            ? (string) (DB::table('unidades')->where('id', $unidadeId)->value('nome') ?? ('Unidade #' . $unidadeId))
            : 'Todas as unidades';

        $qDet = DB::table('financeiro_vale_consumo as v')
            ->join('funcionarios as f', 'v.funcionario_id', '=', 'f.id')
            ->leftJoin('unidades as u', 'f.unidade_id', '=', 'u.id')
            ->select('v.*', 'f.nome_completo as funcionario_nome', 'f.cpf as funcionario_cpf', 'f.cargo as funcionario_cargo', 'u.nome as unidade_nome');
        if (Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')) {
            $qDet->whereBetween('v.data_lancamento', [$di, $df]);
        } else {
            $comp = $valeConsumoValidarCompetencia($request->query('competencia')) ?? substr($di, 0, 7);
            $qDet->where('v.competencia', $comp);
        }
        if ($unidadeId > 0) {
            $qDet->where('f.unidade_id', $unidadeId);
        }
        if (Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')) {
            $qDet->orderByDesc('v.data_lancamento')->orderByDesc('v.id');
        } else {
            $qDet->orderByDesc('v.id');
        }
        $detalhes = $qDet->get();
        $totV = (float) $detalhes->sum(fn ($d) => (float) ($d->valor_vale ?? 0));
        $totC = (float) $detalhes->sum(fn ($d) => (float) ($d->valor_consumo ?? 0));

        $qAgg = DB::table('financeiro_vale_consumo as v')
            ->join('funcionarios as f', 'v.funcionario_id', '=', 'f.id')
            ->leftJoin('unidades as u', 'f.unidade_id', '=', 'u.id')
            ->groupBy('v.funcionario_id', 'f.nome_completo', 'u.nome')
            ->selectRaw('f.nome_completo as funcionario_nome, u.nome as unidade_nome, SUM(v.valor_vale) as total_vale, SUM(v.valor_consumo) as total_consumo');
        if (Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')) {
            $qAgg->whereBetween('v.data_lancamento', [$di, $df]);
        } else {
            $compAgg = $valeConsumoValidarCompetencia($request->query('competencia')) ?? substr($di, 0, 7);
            $qAgg->where('v.competencia', $compAgg);
        }
        if ($unidadeId > 0) {
            $qAgg->where('f.unidade_id', $unidadeId);
        }
        $porPessoaPdf = $qAgg->orderBy('f.nome_completo')->get();

        $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
        $dataCol = Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento');
        $rowsDet = '';
        foreach ($detalhes as $d) {
            $dt = $dataCol && ! empty($d->data_lancamento)
                ? \Carbon\Carbon::parse($d->data_lancamento)->format('d/m/Y')
                : '—';
            $rowsDet .= '<tr>'
                . '<td>' . e($dt) . '</td>'
                . '<td>' . e((string) ($d->funcionario_nome ?? '')) . '</td>'
                . '<td>' . e((string) ($d->unidade_nome ?? '')) . '</td>'
                . '<td style="text-align:right">' . e($fmt($d->valor_vale ?? 0)) . '</td>'
                . '<td style="text-align:right">' . e($fmt($d->valor_consumo ?? 0)) . '</td>'
                . '<td>' . e(\Illuminate\Support\Str::limit((string) ($d->observacao ?? ''), 40)) . '</td>'
                . '</tr>';
        }
        if ($rowsDet === '') {
            $rowsDet = '<tr><td colspan="6" style="text-align:center;color:#666">Nenhum lançamento no período.</td></tr>';
        }

        $nLanc = $detalhes->count();
        $footPdf = '<tfoot><tr style="background:#f5f5f5">'
            . '<td colspan="3" style="text-align:right;font-weight:bold">Total</td>'
            . '<td class="num" style="font-weight:bold">' . e($fmt($totV)) . '</td>'
            . '<td class="num" style="font-weight:bold">' . e($fmt($totC)) . '</td>'
            . '<td></td>'
            . '</tr></tfoot>';

        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8" />'
            . '<style>body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:20px;font-size:11px}h1{font-size:16px}h2{font-size:13px;margin-top:16px}.meta{color:#444;margin-bottom:12px}table{width:100%;border-collapse:collapse;margin-top:6px}th,td{border:1px solid #ccc;padding:5px 6px}th{background:#f0f0f0;text-align:left}.num{text-align:right}tfoot td{border-top:2px solid #999}.tot-resumo{width:auto;margin-top:8px}</style></head><body>';
        $html .= '<h1>Vale / consumo</h1>'
            . '<div class="meta"><strong>Período:</strong> ' . e(\Carbon\Carbon::parse($di)->format('d/m/Y')) . ' a ' . e(\Carbon\Carbon::parse($df)->format('d/m/Y'))
            . ' &nbsp;|&nbsp; <strong>Unidade:</strong> ' . e($uniLabel)
            . ' &nbsp;|&nbsp; <strong>Gerado em:</strong> ' . e(now()->format('d/m/Y H:i')) . '</div>'
            . '<table class="tot-resumo"><thead><tr><th>Vale</th><th>Consumo</th></tr></thead><tbody><tr>'
            . '<td class="num" style="font-weight:bold">R$ ' . e($fmt($totV)) . '</td>'
            . '<td class="num" style="font-weight:bold">R$ ' . e($fmt($totC)) . '</td>'
            . '</tr></tbody></table>'
            . '<p style="margin:6px 0 0 0;font-size:10px;color:#555">' . e((string) $nLanc) . ' lançamento(s)</p>';

        $html .= '<h2>Lançamentos</h2><table><thead><tr>'
            . '<th>Data</th><th>Funcionário</th><th>Unidade</th><th class="num">Vale</th><th class="num">Consumo</th><th>Obs.</th></tr></thead><tbody>' . $rowsDet . '</tbody>' . $footPdf . '</table>';

        $rowsPorPessoa = '';
        foreach ($porPessoaPdf as $p) {
            $rowsPorPessoa .= '<tr>'
                . '<td>' . e((string) ($p->funcionario_nome ?? '')) . '</td>'
                . '<td>' . e((string) ($p->unidade_nome ?? '')) . '</td>'
                . '<td class="num">' . e($fmt($p->total_vale ?? 0)) . '</td>'
                . '<td class="num">' . e($fmt($p->total_consumo ?? 0)) . '</td>'
                . '</tr>';
        }
        if ($rowsPorPessoa === '') {
            $rowsPorPessoa = '<tr><td colspan="4" style="text-align:center;color:#666">Nenhum lançamento no período.</td></tr>';
        }
        $html .= '<h2 style="margin-top:18px">Total por funcionário</h2><table><thead><tr>'
            . '<th>Funcionário</th><th>Unidade</th><th class="num">Vale</th><th class="num">Consumo</th></tr></thead><tbody>'
            . $rowsPorPessoa . '</tbody></table>';
        $html .= '</body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->setIsRemoteEnabled(true);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfOutput = $dompdf->output();
        $fn = 'vale-consumo-' . $di . '-' . $df . ($unidadeId > 0 ? '-u' . $unidadeId : '') . '.pdf';
        $disp = $request->boolean('download', false) ? 'attachment' : 'inline';

        return response($pdfOutput, 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform')
            ->header('Access-Control-Expose-Headers', 'Content-Disposition, Content-Type, Content-Length')
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $disp . '; filename="' . $fn . '"')
            ->header('Content-Length', (string) strlen($pdfOutput));
    } catch (\Exception $e) {
        \Log::error('GET /financeiro/vale-consumo/relatorio.pdf: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao gerar PDF'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::post('/financeiro/vale-consumo', function (Request $request) use ($proventosAuth, $despFixasPodeGerir, $valeConsumoValidarCompetencia) {
    try {
        if (! Schema::hasTable('financeiro_vale_consumo')) {
            return response()->json(['error' => 'Módulo não configurado (migration pendente)'], 503)->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (! $u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (! $despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        if (! Schema::hasTable('funcionarios')) {
            return response()->json(['error' => 'Cadastro de funcionários indisponível'], 503)->header('Access-Control-Allow-Origin', '*');
        }
        $rules = [
            'funcionario_id' => 'required|integer|min:1',
            'valor_vale' => 'required|numeric|min:0',
            'valor_consumo' => 'required|numeric|min:0',
            'observacao' => 'nullable|string|max:500',
        ];
        if (Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')) {
            $rules['data_lancamento'] = 'required|date';
        } else {
            $rules['competencia'] = 'required|string|regex:/^\d{4}-\d{2}$/';
        }
        $data = $request->validate($rules);
        if (! DB::table('funcionarios')->where('id', (int) $data['funcionario_id'])->exists()) {
            return response()->json(['error' => 'Funcionário não encontrado'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        $comp = null;
        $dataLanc = null;
        if (Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')) {
            $dataLanc = \Carbon\Carbon::parse($data['data_lancamento'])->toDateString();
            $comp = substr($dataLanc, 0, 7);
        } else {
            $comp = $valeConsumoValidarCompetencia($data['competencia']);
            if (! $comp) {
                return response()->json(['error' => 'Competência inválida (use AAAA-MM)'], 422)->header('Access-Control-Allow-Origin', '*');
            }
        }
        $insert = [
            'funcionario_id' => (int) $data['funcionario_id'],
            'competencia' => $comp,
            'valor_vale' => round((float) $data['valor_vale'], 2),
            'valor_consumo' => round((float) $data['valor_consumo'], 2),
            'observacao' => $data['observacao'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($dataLanc !== null) {
            $insert['data_lancamento'] = $dataLanc;
        }
        $id = DB::table('financeiro_vale_consumo')->insertGetId($insert);
        $row = DB::table('financeiro_vale_consumo as v')
            ->join('funcionarios as f', 'v.funcionario_id', '=', 'f.id')
            ->leftJoin('unidades as u', 'f.unidade_id', '=', 'u.id')
            ->where('v.id', $id)
            ->select('v.*', 'f.nome_completo as funcionario_nome', 'f.cpf as funcionario_cpf', 'f.cargo as funcionario_cargo', 'u.nome as unidade_nome')
            ->first();

        return response()->json($row, 201)->header('Access-Control-Allow-Origin', '*');
    } catch (\Illuminate\Validation\ValidationException $ve) {
        return response()->json(['error' => 'Dados inválidos', 'details' => $ve->errors()], 422)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('POST /financeiro/vale-consumo: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao salvar'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::put('/financeiro/vale-consumo/{id}', function (Request $request, $id) use ($proventosAuth, $despFixasPodeGerir, $valeConsumoValidarCompetencia) {
    try {
        if (! Schema::hasTable('financeiro_vale_consumo')) {
            return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (! $u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (! $despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        $id = (int) $id;
        $ex = DB::table('financeiro_vale_consumo')->where('id', $id)->first();
        if (! $ex) {
            return response()->json(['error' => 'Registro não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }
        $rules = [
            'funcionario_id' => 'sometimes|required|integer|min:1',
            'valor_vale' => 'sometimes|required|numeric|min:0',
            'valor_consumo' => 'sometimes|required|numeric|min:0',
            'observacao' => 'nullable|string|max:500',
        ];
        if (Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')) {
            $rules['data_lancamento'] = 'sometimes|required|date';
        } else {
            $rules['competencia'] = 'sometimes|required|string|regex:/^\d{4}-\d{2}$/';
        }
        $data = $request->validate($rules);
        if (isset($data['funcionario_id']) && ! DB::table('funcionarios')->where('id', (int) $data['funcionario_id'])->exists()) {
            return response()->json(['error' => 'Funcionário não encontrado'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        $up = ['updated_at' => now()];
        if (isset($data['funcionario_id'])) {
            $up['funcionario_id'] = (int) $data['funcionario_id'];
        }
        if (Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento') && isset($data['data_lancamento'])) {
            $dl = \Carbon\Carbon::parse($data['data_lancamento'])->toDateString();
            $up['data_lancamento'] = $dl;
            $up['competencia'] = substr($dl, 0, 7);
        } elseif (isset($data['competencia'])) {
            $c = $valeConsumoValidarCompetencia($data['competencia']);
            if (! $c) {
                return response()->json(['error' => 'Competência inválida'], 422)->header('Access-Control-Allow-Origin', '*');
            }
            $up['competencia'] = $c;
        }
        if (isset($data['valor_vale'])) {
            $up['valor_vale'] = round((float) $data['valor_vale'], 2);
        }
        if (isset($data['valor_consumo'])) {
            $up['valor_consumo'] = round((float) $data['valor_consumo'], 2);
        }
        if (array_key_exists('observacao', $data)) {
            $up['observacao'] = $data['observacao'];
        }
        DB::table('financeiro_vale_consumo')->where('id', $id)->update($up);
        $row = DB::table('financeiro_vale_consumo as v')
            ->join('funcionarios as f', 'v.funcionario_id', '=', 'f.id')
            ->leftJoin('unidades as u', 'f.unidade_id', '=', 'u.id')
            ->where('v.id', $id)
            ->select('v.*', 'f.nome_completo as funcionario_nome', 'f.cpf as funcionario_cpf', 'f.cargo as funcionario_cargo', 'u.nome as unidade_nome')
            ->first();

        return response()->json($row)->header('Access-Control-Allow-Origin', '*');
    } catch (\Illuminate\Validation\ValidationException $ve) {
        return response()->json(['error' => 'Dados inválidos', 'details' => $ve->errors()], 422)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('PUT /financeiro/vale-consumo/{id}: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao atualizar'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::delete('/financeiro/vale-consumo/{id}', function (Request $request, $id) use ($proventosAuth, $despFixasPodeGerir) {
    try {
        if (! Schema::hasTable('financeiro_vale_consumo')) {
            return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (! $u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (! $despFixasPodeGerir($perfil)) {
            return response()->json(['error' => 'Não autorizado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        $n = DB::table('financeiro_vale_consumo')->where('id', (int) $id)->delete();
        if (! $n) {
            return response()->json(['error' => 'Registro não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        return response()->json(['ok' => true])->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('DELETE /financeiro/vale-consumo/{id}: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao excluir'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/proventos/meus', function (Request $request) use ($proventosAuth, $proventoSelectFuncionarioPix, $proventoSelectUnidadeCnpj) {
    try {
        if (!Schema::hasTable('proventos')) return response()->json([])->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        $funcId = DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
        if (!$funcId) return response()->json([])->header('Access-Control-Allow-Origin', '*');

        $lista = DB::table('proventos')
            ->leftJoin('funcionarios', 'proventos.funcionario_id', '=', 'funcionarios.id')
            ->leftJoin('unidades', 'proventos.unidade_id', '=', 'unidades.id')
            ->leftJoin('usuarios as criador', 'proventos.criado_por', '=', 'criador.id')
            ->where('proventos.funcionario_id', $funcId)
            ->select('proventos.*', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.cpf as funcionario_cpf', $proventoSelectFuncionarioPix(), 'unidades.nome as unidade_nome', $proventoSelectUnidadeCnpj(), 'criador.nome as criado_por_nome')
            ->orderByDesc('proventos.id')->get();
        foreach ($lista as $row) {
            $row->observacao_interna = null;
        }
        return response()->json($lista)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /proventos/meus: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao listar proventos'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/proventos/{id}', function (Request $request, $id) use ($proventosAuth, $podeCriarProvento, $proventoSelectFuncionarioPix, $proventoSelectUnidadeCnpj) {
    try {
        if (!Schema::hasTable('proventos')) return response()->json(['error' => 'Módulo não configurado'], 404)->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');

        $p = DB::table('proventos')
            ->leftJoin('funcionarios', 'proventos.funcionario_id', '=', 'funcionarios.id')
            ->leftJoin('unidades', 'proventos.unidade_id', '=', 'unidades.id')
            ->leftJoin('usuarios as criador', 'proventos.criado_por', '=', 'criador.id')
            ->leftJoin('usuarios as autorizador', 'proventos.autorizado_por', '=', 'autorizador.id')
            ->leftJoin('usuarios as finalizador', 'proventos.finalizado_por', '=', 'finalizador.id')
            ->where('proventos.id', $id)
            ->select('proventos.*', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.cpf as funcionario_cpf', 'funcionarios.whatsapp', 'funcionarios.email', $proventoSelectFuncionarioPix(),
                'unidades.nome as unidade_nome', $proventoSelectUnidadeCnpj(), 'criador.nome as criado_por_nome', 'autorizador.nome as autorizado_por_nome', 'finalizador.nome as finalizado_por_nome')
            ->first();
        if (!$p) return response()->json(['error' => 'Provento não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');

        $funcId = DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
        $perfil = strtoupper(trim($u->perfil ?? ''));
        // Sem permissão para lançar: só pode ver proventos próprios
        if (!$podeCriarProvento($perfil) && (int)$p->funcionario_id !== (int)$funcId) {
            return response()->json(['error' => 'Acesso negado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        if (!$podeCriarProvento($perfil)) {
            $p->observacao_interna = null;
        }
        return response()->json($p)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /proventos/{id}: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao buscar provento'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/proventos/{id}/recibo.pdf', function (Request $request, $id) use ($proventosAuth, $podeCriarProvento, $proventoSelectFuncionarioPix, $proventoSelectUnidadeCnpj) {
    $h = static fn ($s) => htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    try {
        if (!Schema::hasTable('proventos')) {
            return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        }
        $u = $proventosAuth($request);
        if (!$u) {
            return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        }

        $p = DB::table('proventos')
            ->leftJoin('funcionarios', 'proventos.funcionario_id', '=', 'funcionarios.id')
            ->leftJoin('unidades', 'proventos.unidade_id', '=', 'unidades.id')
            ->leftJoin('usuarios as criador', 'proventos.criado_por', '=', 'criador.id')
            ->leftJoin('usuarios as autorizador', 'proventos.autorizado_por', '=', 'autorizador.id')
            ->leftJoin('usuarios as finalizador', 'proventos.finalizado_por', '=', 'finalizador.id')
            ->where('proventos.id', $id)
            ->select('proventos.*', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.cpf as funcionario_cpf', 'funcionarios.whatsapp', 'funcionarios.email', $proventoSelectFuncionarioPix(),
                'unidades.nome as unidade_nome', $proventoSelectUnidadeCnpj(), 'criador.nome as criado_por_nome', 'autorizador.nome as autorizado_por_nome', 'finalizador.nome as finalizado_por_nome')
            ->first();
        if (!$p) {
            return response()->json(['error' => 'Provento não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        $funcId = DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (!$podeCriarProvento($perfil) && (int) $p->funcionario_id !== (int) $funcId) {
            return response()->json(['error' => 'Acesso negado'], 403)->header('Access-Control-Allow-Origin', '*');
        }
        if ($p->status !== 'finalizado') {
            return response()->json(['error' => 'Recibo disponível apenas para proventos finalizados'], 422)->header('Access-Control-Allow-Origin', '*');
        }

        $tipoLabels = [
            'vale' => 'Vale',
            'adiantamento' => 'Adiantamento',
            'consumo_interno' => 'Consumo interno',
            'ajuda_custo' => 'Ajuda de custo',
            'outro' => 'Outro',
        ];
        $tipoL = $tipoLabels[$p->tipo] ?? $p->tipo;
        $valorNum = (float) $p->valor;
        $valorFmt = 'R$ ' . number_format($valorNum, 2, ',', '.');

        $tz = config('app.timezone', 'America/Sao_Paulo');
        $fmtDt = static function ($v) use ($tz) {
            if (empty($v)) {
                return '—';
            }
            try {
                return \Carbon\Carbon::parse($v, $tz)->format('d/m/Y H:i');
            } catch (\Throwable $e) {
                return '—';
            }
        };
        $dataProv = $p->data_provento ? \Carbon\Carbon::parse($p->data_provento)->format('d/m/Y') : '—';
        $comp = $p->competencia ? $h($p->competencia) : '—';
        $cnpjDigits = preg_replace('/\D/', '', (string) ($p->unidade_cnpj ?? ''));
        $cnpjFmt = '—';
        if (strlen($cnpjDigits) === 14) {
            $cnpjFmt = $h(preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpjDigits));
        } elseif ($cnpjDigits !== '') {
            $cnpjFmt = $h($p->unidade_cnpj);
        }

        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"/><style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #222; margin: 24px; }
            h1 { font-size: 16pt; text-align: center; margin: 0 0 8px; color: #1565c0; }
            .sub { text-align: center; font-size: 9pt; color: #666; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin: 12px 0; }
            th, td { border: 1px solid #ccc; padding: 8px 10px; text-align: left; vertical-align: top; }
            th { background: #f5f5f5; width: 32%; }
            .valor { font-size: 14pt; font-weight: bold; color: #0d47a1; }
            .decl { margin-top: 20px; padding: 12px; background: #fafafa; border: 1px solid #e0e0e0; font-size: 10pt; line-height: 1.5; }
            .rod { margin-top: 24px; font-size: 9pt; color: #555; border-top: 1px solid #ddd; padding-top: 10px; }
        </style></head><body>
        <h1>Recibo de provento</h1>
        <div class="sub">Documento gerado em ' . $h(\Carbon\Carbon::now($tz)->format('d/m/Y H:i')) . '</div>
        <table>
            <tr><th>Nº do lançamento</th><td>' . $h($p->id) . '</td></tr>
            <tr><th>Funcionário</th><td>' . $h($p->funcionario_nome) . '</td></tr>
            <tr><th>CPF</th><td>' . $h($p->funcionario_cpf) . '</td></tr>
            <tr><th>Unidade</th><td>' . $h($p->unidade_nome) . '</td></tr>
            <tr><th>CNPJ (unidade)</th><td>' . $cnpjFmt . '</td></tr>
            <tr><th>Tipo</th><td>' . $h($tipoL) . '</td></tr>
            <tr><th>Valor</th><td class="valor">' . $h($valorFmt) . '</td></tr>
            <tr><th>Data do provento</th><td>' . $h($dataProv) . '</td></tr>
            <tr><th>Competência</th><td>' . $comp . '</td></tr>
        </table>
        <div class="decl">
            Declaro para os devidos fins que recebi da empresa a importância acima especificada,
            referente ao provento discriminado neste recibo, e que não há nada a reclamar quanto ao pagamento,
            estando quitado o valor discriminado nesta data.
        </div>
        <div class="rod">
            <strong>Aceite eletrônico do funcionário:</strong> ' . $h($fmtDt($p->data_assinatura)) . '<br/>
            <strong>Autorização (gestão):</strong> ' . $h($p->autorizado_por_nome ?: '—') . ' — ' . $h($fmtDt($p->data_autorizacao)) . '<br/>
            <strong>Finalizado por:</strong> ' . $h($p->finalizado_por_nome ?: '—') . ' — ' . $h($fmtDt($p->data_finalizacao)) . '<br/>
            <strong>Lançado por:</strong> ' . $h($p->criado_por_nome ?: '—') . '
        </div>
        </body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfOutput = $dompdf->output();
        $fn = 'recibo-provento-' . $id . '.pdf';

        return response($pdfOutput, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $fn . '"')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform')
            ->header('Content-Length', (string) strlen($pdfOutput));
    } catch (\Exception $e) {
        \Log::error('GET /proventos/recibo.pdf: ' . $e->getMessage());

        return response()->json(['error' => 'Erro ao gerar recibo'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::post('/proventos', function (Request $request) use ($proventosAuth, $proventosLog, $mergeDeviceExtras, $podeCriarProvento, $proventoSelectUnidadeCnpj) {
    try {
        if (!Schema::hasTable('proventos')) return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        if (!$podeCriarProvento($u->perfil)) return response()->json(['error' => 'Sem permissão para criar proventos'], 403)->header('Access-Control-Allow-Origin', '*');

        $d = $request->all();
        $rules = [
            'funcionario_id' => 'required|integer|exists:funcionarios,id',
            'unidade_id' => 'required|integer|exists:unidades,id',
            'tipo' => 'required|in:vale,adiantamento,consumo_interno,ajuda_custo,outro',
            'verba' => 'nullable|string|max:255',
            'valor' => 'required|numeric|min:0.01',
            'data_provento' => 'required|date',
            'motivo' => 'nullable|string|max:2000',
        ];
        $v = \Illuminate\Support\Facades\Validator::make($d, $rules);
        if ($v->fails()) return response()->json(['error' => implode(' ', $v->errors()->all()), 'details' => $v->errors()], 422)->header('Access-Control-Allow-Origin', '*');

        $tipoLabels = [
            'vale' => 'Vale',
            'adiantamento' => 'Adiantamento',
            'consumo_interno' => 'Consumo interno',
            'ajuda_custo' => 'Ajuda de custo',
            'outro' => 'Outro',
        ];
        $funcNome = (string) (DB::table('funcionarios')->where('id', (int) $d['funcionario_id'])->value('nome_completo') ?? '');
        $tipoLeg = $tipoLabels[$d['tipo']] ?? $d['tipo'];
        $verba = trim((string) ($d['verba'] ?? ''));
        if ($verba === '') {
            $verba = $tipoLeg;
        }
        $motivo = trim((string) ($d['motivo'] ?? ''));
        if ($motivo === '') {
            $motivo = 'Provento: ' . $tipoLeg . ' — ' . $funcNome;
        }

        $insert = [
            'funcionario_id' => (int)$d['funcionario_id'],
            'unidade_id' => !empty($d['unidade_id']) ? (int)$d['unidade_id'] : null,
            'tipo' => $d['tipo'],
            'verba' => $verba,
            'valor' => (float)$d['valor'],
            'data_provento' => $d['data_provento'],
            'competencia' => $d['competencia'] ?? null,
            'motivo' => $motivo,
            'observacao_interna' => $d['observacao_interna'] ?? null,
            'status' => 'aguardando_autorizacao',
            'criado_por' => $u->id,
        ];
        $id = DB::table('proventos')->insertGetId($insert);
        $provento = DB::table('proventos')->leftJoin('funcionarios', 'proventos.funcionario_id', '=', 'funcionarios.id')
            ->leftJoin('unidades', 'proventos.unidade_id', '=', 'unidades.id')
            ->where('proventos.id', $id)->select('proventos.*', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.cpf as funcionario_cpf', 'unidades.nome as unidade_nome', $proventoSelectUnidadeCnpj())->first();
        $proventosLog($id, $u->id, $insert['funcionario_id'], 'criacao', null, 'aguardando_autorizacao', 'Provento criado', $request->ip(), $request->userAgent(), $mergeDeviceExtras($request, null));
        return response()->json($provento, 201)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('POST /proventos: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::put('/proventos/{id}', function (Request $request, $id) use ($proventosAuth, $proventosLog, $mergeDeviceExtras, $podeCriarProvento, $proventoSelectUnidadeCnpj) {
    try {
        if (!Schema::hasTable('proventos')) return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        if (!$podeCriarProvento($u->perfil)) return response()->json(['error' => 'Sem permissão'], 403)->header('Access-Control-Allow-Origin', '*');

        $p = DB::table('proventos')->where('id', $id)->first();
        if (!$p) return response()->json(['error' => 'Provento não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        if (!in_array($p->status, ['rascunho', 'aguardando_autorizacao'])) return response()->json(['error' => 'Provento não pode mais ser editado'], 422)->header('Access-Control-Allow-Origin', '*');

        $d = $request->all();
        $rules = [
            'unidade_id' => 'nullable|integer|exists:unidades,id',
            'tipo' => 'required|in:vale,adiantamento,consumo_interno,ajuda_custo,outro',
            'verba' => 'nullable|string|max:255',
            'valor' => 'required|numeric|min:0.01',
            'data_provento' => 'required|date',
            'motivo' => 'nullable|string|max:2000',
        ];
        $v = \Illuminate\Support\Facades\Validator::make($d, $rules);
        if ($v->fails()) return response()->json(['error' => implode(' ', $v->errors()->all()), 'details' => $v->errors()], 422)->header('Access-Control-Allow-Origin', '*');

        $tipoLabels = [
            'vale' => 'Vale',
            'adiantamento' => 'Adiantamento',
            'consumo_interno' => 'Consumo interno',
            'ajuda_custo' => 'Ajuda de custo',
            'outro' => 'Outro',
        ];
        $funcNome = (string) (DB::table('funcionarios')->where('id', $p->funcionario_id)->value('nome_completo') ?? '');
        $tipoLeg = $tipoLabels[$d['tipo']] ?? $d['tipo'];
        $verba = trim((string) ($d['verba'] ?? ''));
        if ($verba === '') {
            $verba = $tipoLeg;
        }
        $motivo = trim((string) ($d['motivo'] ?? ''));
        if ($motivo === '') {
            $motivo = 'Provento: ' . $tipoLeg . ' — ' . $funcNome;
        }

        $up = [
            'unidade_id' => isset($d['unidade_id']) && $d['unidade_id'] !== '' ? (int)$d['unidade_id'] : null,
            'tipo' => $d['tipo'], 'verba' => $verba, 'valor' => (float)$d['valor'],
            'data_provento' => $d['data_provento'], 'competencia' => $d['competencia'] ?? null,
            'motivo' => $motivo, 'observacao_interna' => $d['observacao_interna'] ?? null,
        ];
        DB::table('proventos')->where('id', $id)->update($up);
        $proventosLog($id, $u->id, $p->funcionario_id, 'edicao', $p->status, $p->status, 'Provento editado', $request->ip(), $request->userAgent(), $mergeDeviceExtras($request, null));
        $novo = DB::table('proventos')->leftJoin('funcionarios', 'proventos.funcionario_id', '=', 'funcionarios.id')
            ->leftJoin('unidades', 'proventos.unidade_id', '=', 'unidades.id')
            ->where('proventos.id', $id)->select('proventos.*', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.cpf as funcionario_cpf', 'unidades.nome as unidade_nome', $proventoSelectUnidadeCnpj())->first();
        return response()->json($novo)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('PUT /proventos: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::post('/proventos/{id}/autorizar', function (Request $request, $id) use ($proventosAuth, $proventosLog, $mergeDeviceExtras, $podeAutorizarOuFinalizar, $proventoSelectUnidadeCnpj) {
    try {
        if (!Schema::hasTable('proventos')) return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        if (!$podeAutorizarOuFinalizar($u->perfil)) return response()->json(['error' => 'Sem permissão para autorizar'], 403)->header('Access-Control-Allow-Origin', '*');

        $p = DB::table('proventos')->where('id', $id)->first();
        if (!$p) return response()->json(['error' => 'Provento não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        if ($p->status !== 'assinado') return response()->json(['error' => 'Provento precisa estar assinado para autorizar'], 422)->header('Access-Control-Allow-Origin', '*');

        DB::table('proventos')->where('id', $id)->update(['status' => 'autorizado', 'autorizado_por' => $u->id, 'data_autorizacao' => now()]);
        $proventosLog($id, $u->id, $p->funcionario_id, 'autorizacao', 'assinado', 'autorizado', 'Provento autorizado', $request->ip(), $request->userAgent(), $mergeDeviceExtras($request, null));
        $novo = DB::table('proventos')->leftJoin('funcionarios', 'proventos.funcionario_id', '=', 'funcionarios.id')
            ->leftJoin('unidades', 'proventos.unidade_id', '=', 'unidades.id')
            ->where('proventos.id', $id)->select('proventos.*', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.cpf as funcionario_cpf', 'unidades.nome as unidade_nome', $proventoSelectUnidadeCnpj())->first();
        return response()->json($novo)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('POST /proventos/autorizar: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::post('/proventos/{id}/enviar-codigo', function (Request $request, $id) use ($proventosAuth, $proventosLog, $mergeDeviceExtras) {
    try {
        if (!Schema::hasTable('proventos') || !Schema::hasTable('proventos_assinaturas')) return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');

        $p = DB::table('proventos')->where('id', $id)->first();
        if (!$p) return response()->json(['error' => 'Provento não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        if ($p->status !== 'aguardando_autorizacao') return response()->json(['error' => 'Provento deve estar aguardando assinatura'], 422)->header('Access-Control-Allow-Origin', '*');

        $funcId = DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
        if ((int)$p->funcionario_id !== (int)$funcId) return response()->json(['error' => 'Apenas o funcionário do provento pode assinar'], 403)->header('Access-Control-Allow-Origin', '*');

        $canal = $request->input('canal');
        if (!in_array($canal, ['whatsapp', 'email'])) return response()->json(['error' => 'Canal inválido. Use whatsapp ou email'], 422)->header('Access-Control-Allow-Origin', '*');

        $func = DB::table('funcionarios')->where('id', $p->funcionario_id)->first();
        if ($canal === 'whatsapp' && empty(trim($func->whatsapp ?? ''))) {
            return response()->json(['error' => 'WhatsApp não cadastrado para este funcionário. Cadastre em RH ou use o envio por e-mail.'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        $destino = $canal === 'whatsapp' ? ($func->whatsapp ?? '') : ($func->email ?? $u->email ?? '');
        if ($canal === 'email' && empty($destino)) {
            return response()->json(['error' => 'E-mail não cadastrado. Cadastre o e-mail no funcionário ou use o e-mail do usuário de login.'], 422)->header('Access-Control-Allow-Origin', '*');
        }

        $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = Hash::make($codigo);
        $expira = now()->addMinutes(5);

        DB::table('proventos_assinaturas')
            ->where('provento_id', $id)
            ->where('status_envio', 'enviado')
            ->update(['status_envio' => 'invalidado']);

        DB::table('proventos_assinaturas')->insert([
            'provento_id' => $id, 'funcionario_id' => $p->funcionario_id, 'canal_envio' => $canal,
            'codigo_hash' => $hash, 'codigo_expira_em' => $expira, 'tentativas' => 0, 'status_envio' => 'enviado',
        ]);

        $msg = "Seu código de aceite eletrônico para o provento #{$id} é: {$codigo}. Válido por 5 minutos.";
        $emailEnviado = false;
        $whatsappLink = null;
        if ($canal === 'email') {
            try {
                Mail::raw($msg, fn($m) => $m->to($destino)->subject('Código de aceite - Provento #' . $id));
                $emailEnviado = true;
            } catch (\Exception $e) {
                \Log::warning('Email OTP falhou: ' . $e->getMessage());
            }
        }
        // Mesma lógica da Reserva de Mesa: formatTelefoneParaWhatsApp
        if ($canal === 'whatsapp' && !empty($destino)) {
            $dig = preg_replace('/\D/', '', $destino);
            if (strlen($dig) >= 10) {
                $numFinal = (substr($dig, 0, 2) === '55' && strlen($dig) >= 12) ? $dig : ('55' . $dig);
                if (strlen($numFinal) >= 12) {
                    $whatsappLink = 'https://wa.me/' . $numFinal . '?text=' . rawurlencode($msg);
                }
            }
        }
        $proventosLog($id, $u->id, $p->funcionario_id, 'otp_enviado', null, null, "Código enviado por {$canal}", $request->ip(), $request->userAgent(), $mergeDeviceExtras($request, ['canal' => $canal]));
        $resp = ['message' => 'Código enviado com sucesso', 'codigo' => $codigo];
        if ($canal === 'email' && !$emailEnviado) {
            $resp['_aviso'] = 'E-mail pode não ter sido enviado. Use o código exibido abaixo.';
        }
        if ($canal === 'whatsapp') {
            if (!empty($whatsappLink)) {
                $resp['whatsapp_link'] = $whatsappLink;
                $resp['_aviso'] = 'Clique no botão abaixo para abrir o WhatsApp e enviar o código para você mesmo.';
            } else {
                $resp['_aviso'] = 'WhatsApp não cadastrado no funcionário. Use o código exibido abaixo e cadastre o WhatsApp no próximo provento.';
            }
        }
        return response()->json($resp)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('POST /proventos/enviar-codigo: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::post('/proventos/{id}/confirmar-assinatura', function (Request $request, $id) use ($proventosAuth, $proventosLog, $mergeDeviceExtras, $podeAutorizarOuFinalizar, $proventoSelectUnidadeCnpj) {
    try {
        if (!Schema::hasTable('proventos') || !Schema::hasTable('proventos_assinaturas')) return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');

        $p = DB::table('proventos')->where('id', $id)->first();
        if (!$p) return response()->json(['error' => 'Provento não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        if ($p->status !== 'aguardando_autorizacao') return response()->json(['error' => 'Provento deve estar aguardando assinatura'], 422)->header('Access-Control-Allow-Origin', '*');

        $funcId = DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
        if ((int)$p->funcionario_id !== (int)$funcId) return response()->json(['error' => 'Apenas o funcionário pode assinar'], 403)->header('Access-Control-Allow-Origin', '*');

        $codigo = $request->input('codigo');
        if (empty($codigo)) return response()->json(['error' => 'Código obrigatório'], 422)->header('Access-Control-Allow-Origin', '*');

        $reg = DB::table('proventos_assinaturas')->where('provento_id', $id)->where('funcionario_id', $funcId)->where('status_envio', 'enviado')->orderByDesc('id')->first();
        if (!$reg) return response()->json(['error' => 'Nenhum código pendente. Solicite novo código.'], 422)->header('Access-Control-Allow-Origin', '*');
        if ($reg->codigo_expira_em < now()) {
            DB::table('proventos_assinaturas')->where('id', $reg->id)->update(['status_envio' => 'expirado']);
            return response()->json(['error' => 'Código expirado. Solicite novo código.'], 422)->header('Access-Control-Allow-Origin', '*');
        }
        if ($reg->tentativas >= 5) return response()->json(['error' => 'Muitas tentativas. Solicite novo código.'], 422)->header('Access-Control-Allow-Origin', '*');

        DB::table('proventos_assinaturas')->where('id', $reg->id)->increment('tentativas');
        if (!Hash::check($codigo, $reg->codigo_hash)) return response()->json(['error' => 'Código inválido'], 422)->header('Access-Control-Allow-Origin', '*');

        DB::table('proventos_assinaturas')->where('id', $reg->id)->update(['status_envio' => 'validado', 'validado_em' => now(), 'ip' => $request->ip(), 'user_agent' => $request->userAgent()]);
        DB::table('proventos')->where('id', $id)->update(['status' => 'assinado', 'data_assinatura' => now()]);
        $proventosLog($id, null, $funcId, 'assinatura', 'aguardando_autorizacao', 'assinado', 'Aceite eletrônico confirmado', $request->ip(), $request->userAgent(), $mergeDeviceExtras($request, ['otp_validado' => true]));
        $novo = DB::table('proventos')->leftJoin('funcionarios', 'proventos.funcionario_id', '=', 'funcionarios.id')->leftJoin('unidades', 'proventos.unidade_id', '=', 'unidades.id')
            ->where('proventos.id', $id)->select('proventos.*', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.cpf as funcionario_cpf', 'unidades.nome as unidade_nome', $proventoSelectUnidadeCnpj())->first();
        return response()->json($novo)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('POST /proventos/confirmar-assinatura: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::post('/proventos/{id}/finalizar', function (Request $request, $id) use ($proventosAuth, $proventosLog, $mergeDeviceExtras, $podeAutorizarOuFinalizar, $proventoSelectUnidadeCnpj) {
    try {
        if (!Schema::hasTable('proventos')) return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        if (!$podeAutorizarOuFinalizar($u->perfil)) return response()->json(['error' => 'Sem permissão para finalizar'], 403)->header('Access-Control-Allow-Origin', '*');

        $p = DB::table('proventos')->where('id', $id)->first();
        if (!$p) return response()->json(['error' => 'Provento não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        if ($p->status !== 'autorizado') return response()->json(['error' => 'Provento precisa estar autorizado para finalizar'], 422)->header('Access-Control-Allow-Origin', '*');

        DB::table('proventos')->where('id', $id)->update(['status' => 'finalizado', 'finalizado_por' => $u->id, 'data_finalizacao' => now()]);
        $proventosLog($id, $u->id, $p->funcionario_id, 'finalizacao', 'autorizado', 'finalizado', 'Provento finalizado', $request->ip(), $request->userAgent(), $mergeDeviceExtras($request, null));
        $novo = DB::table('proventos')->leftJoin('funcionarios', 'proventos.funcionario_id', '=', 'funcionarios.id')->leftJoin('unidades', 'proventos.unidade_id', '=', 'unidades.id')
            ->where('proventos.id', $id)->select('proventos.*', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.cpf as funcionario_cpf', 'unidades.nome as unidade_nome', $proventoSelectUnidadeCnpj())->first();
        return response()->json($novo)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('POST /proventos/finalizar: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::post('/proventos/{id}/cancelar', function (Request $request, $id) use ($proventosAuth, $proventosLog, $mergeDeviceExtras, $podeAutorizarOuFinalizar, $proventoSelectUnidadeCnpj) {
    try {
        if (!Schema::hasTable('proventos')) return response()->json(['error' => 'Módulo não configurado'], 503)->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        if (!$podeAutorizarOuFinalizar($u->perfil)) return response()->json(['error' => 'Sem permissão para cancelar'], 403)->header('Access-Control-Allow-Origin', '*');

        $p = DB::table('proventos')->where('id', $id)->first();
        if (!$p) return response()->json(['error' => 'Provento não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        if (in_array($p->status, ['finalizado', 'cancelado'])) return response()->json(['error' => 'Provento não pode ser cancelado'], 422)->header('Access-Control-Allow-Origin', '*');

        $just = trim($request->input('justificativa', ''));
        if (empty($just)) return response()->json(['error' => 'Justificativa obrigatória para cancelamento'], 422)->header('Access-Control-Allow-Origin', '*');

        $ant = $p->status;
        DB::table('proventos')->where('id', $id)->update(['status' => 'cancelado', 'cancelado_por' => $u->id, 'data_cancelamento' => now(), 'justificativa_cancelamento' => $just]);
        $proventosLog($id, $u->id, $p->funcionario_id, 'cancelamento', $ant, 'cancelado', $just, $request->ip(), $request->userAgent(), $mergeDeviceExtras($request, null));
        $novo = DB::table('proventos')->leftJoin('funcionarios', 'proventos.funcionario_id', '=', 'funcionarios.id')->leftJoin('unidades', 'proventos.unidade_id', '=', 'unidades.id')
            ->where('proventos.id', $id)->select('proventos.*', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.cpf as funcionario_cpf', 'unidades.nome as unidade_nome', $proventoSelectUnidadeCnpj())->first();
        return response()->json($novo)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('POST /proventos/cancelar: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/proventos/{id}/logs', function (Request $request, $id) use ($proventosAuth, $podeCriarProvento, $formatLogCreatedAt) {
    try {
        if (!Schema::hasTable('proventos_logs')) return response()->json([])->header('Access-Control-Allow-Origin', '*');
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');

        $p = DB::table('proventos')->where('id', $id)->first();
        if (!$p) return response()->json(['error' => 'Provento não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        $funcId = DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (!$podeCriarProvento($perfil) && (int)$p->funcionario_id !== (int)$funcId) return response()->json(['error' => 'Acesso negado'], 403)->header('Access-Control-Allow-Origin', '*');

        $logs = DB::table('proventos_logs')
            ->leftJoin('usuarios', 'proventos_logs.usuario_id', '=', 'usuarios.id')
            ->leftJoin('funcionarios', 'proventos_logs.funcionario_id', '=', 'funcionarios.id')
            ->where('proventos_logs.provento_id', $id)
            ->select('proventos_logs.*', 'usuarios.nome as usuario_nome', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.whatsapp as funcionario_whatsapp')
            ->orderByDesc('proventos_logs.created_at')->get();
        $formatLogCreatedAt($logs);
        return response()->json($logs)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /proventos/logs: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao buscar logs'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

// ============================================
// AUDIT LOGS - Log geral e exportação para perícia
// ============================================
$auditCors = fn() => response()->json([])->header('Access-Control-Allow-Origin', '*')->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform');
Route::options('/audit-logs', $auditCors);
Route::options('/audit-logs/registrar', $auditCors);
Route::options('/proventos/{id}/export-pericia', $auditCors);

$auditAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');
    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

Route::post('/audit-logs/registrar', function (Request $request) use ($auditAuth) {
    try {
        if (!Schema::hasTable('audit_logs')) return response()->json(['ok' => true])->header('Access-Control-Allow-Origin', '*');
        $u = $auditAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        $acao = trim($request->input('acao', ''));
        $recurso = trim($request->input('recurso', ''));
        $recursoId = $request->input('recurso_id');
        $descricao = trim($request->input('descricao', ''));
        if (empty($acao)) return response()->json(['error' => 'Ação obrigatória'], 422)->header('Access-Control-Allow-Origin', '*');
        $extras = $request->input('dados_extras');
        if (is_array($extras) || is_object($extras)) $extras = (array) $extras; else $extras = [];
        if ($m = $request->header('X-Device-Model')) $extras['device_model'] = $m;
        if ($p = $request->header('X-Device-Platform')) $extras['device_platform'] = $p;
        $extras = !empty($extras) ? json_encode($extras) : null;
        DB::table('audit_logs')->insert([
            'usuario_id' => $u->id,
            'acao' => $acao,
            'recurso' => $recurso ?: null,
            'recurso_id' => $recursoId,
            'descricao' => $descricao ?: null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'dados_extras' => $extras,
            'created_at' => now(),
        ]);
        return response()->json(['ok' => true])->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('POST /audit-logs/registrar: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao registrar'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/audit-logs', function (Request $request) use ($auditAuth, $formatLogCreatedAt) {
    try {
        if (!Schema::hasTable('audit_logs')) return response()->json([])->header('Access-Control-Allow-Origin', '*');
        $u = $auditAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (!in_array($perfil, ['ADMIN', 'GERENTE'])) return response()->json(['error' => 'Sem permissão'], 403)->header('Access-Control-Allow-Origin', '*');
        $q = DB::table('audit_logs')->leftJoin('usuarios', 'audit_logs.usuario_id', '=', 'usuarios.id')
            ->select('audit_logs.*', 'usuarios.nome as usuario_nome', 'usuarios.email as usuario_email');
        if ($usuarioId = $request->query('usuario_id')) $q->where('audit_logs.usuario_id', $usuarioId);
        if ($acao = trim($request->query('acao', ''))) $q->where('audit_logs.acao', $acao);
        if ($recurso = trim($request->query('recurso', ''))) $q->where('audit_logs.recurso', $recurso);
        if ($dataInicio = $request->query('data_inicio')) $q->whereDate('audit_logs.created_at', '>=', $dataInicio);
        if ($dataFim = $request->query('data_fim')) $q->whereDate('audit_logs.created_at', '<=', $dataFim);
        $lista = $q->orderByDesc('audit_logs.created_at')->limit(500)->get();
        $formatLogCreatedAt($lista);
        return response()->json($lista)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /audit-logs: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao listar'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

Route::get('/proventos/{id}/export-pericia', function (Request $request, $id) use ($proventosAuth, $podeCriarProvento, $formatLogCreatedAt, $proventoSelectUnidadeCnpj) {
    try {
        $u = $proventosAuth($request);
        if (!$u) return response()->json(['error' => 'Não autorizado'], 401)->header('Access-Control-Allow-Origin', '*');
        $perfil = strtoupper(trim($u->perfil ?? ''));
        if (!in_array($perfil, ['ADMIN', 'GERENTE', 'FINANCEIRO'])) return response()->json(['error' => 'Sem permissão'], 403)->header('Access-Control-Allow-Origin', '*');
        $funcId = DB::table('funcionarios')->where('usuario_id', $u->id)->value('id');
        if (!$podeCriarProvento($perfil) && !$funcId) return response()->json(['error' => 'Acesso negado'], 403)->header('Access-Control-Allow-Origin', '*');
        $p = DB::table('proventos')->leftJoin('funcionarios', 'proventos.funcionario_id', '=', 'funcionarios.id')
            ->leftJoin('unidades', 'proventos.unidade_id', '=', 'unidades.id')
            ->where('proventos.id', $id)
            ->select('proventos.*', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.cpf', 'funcionarios.whatsapp', 'funcionarios.email as funcionario_email', 'unidades.nome as unidade_nome', $proventoSelectUnidadeCnpj())
            ->first();
        if (!$p) return response()->json(['error' => 'Provento não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        if (!$podeCriarProvento($perfil) && (int)$p->funcionario_id !== (int)$funcId) return response()->json(['error' => 'Acesso negado'], 403)->header('Access-Control-Allow-Origin', '*');
        $logs = [];
        if (Schema::hasTable('proventos_logs')) {
            $logs = DB::table('proventos_logs')
                ->leftJoin('usuarios', 'proventos_logs.usuario_id', '=', 'usuarios.id')
                ->leftJoin('funcionarios', 'proventos_logs.funcionario_id', '=', 'funcionarios.id')
                ->where('proventos_logs.provento_id', $id)
                ->select('proventos_logs.*', 'usuarios.nome as usuario_nome', 'funcionarios.nome_completo as funcionario_nome', 'funcionarios.whatsapp as funcionario_whatsapp')
                ->orderBy('proventos_logs.created_at')->get();
            $formatLogCreatedAt($logs);
        }
        $assinaturas = [];
        if (Schema::hasTable('proventos_assinaturas')) {
            $assinaturas = DB::table('proventos_assinaturas')->where('provento_id', $id)->orderBy('id')->get();
        }
        $backup = [
            'gerado_em' => now()->toIso8601String(),
            'provento' => $p,
            'logs' => $logs,
            'assinaturas' => $assinaturas,
        ];
        return response()->json($backup)->header('Access-Control-Allow-Origin', '*');
    } catch (\Exception $e) {
        \Log::error('GET /proventos/export-pericia: ' . $e->getMessage());
        return response()->json(['error' => 'Erro ao exportar'], 500)->header('Access-Control-Allow-Origin', '*');
    }
});

// ============================================
// KANBAN ADMINISTRATIVO
// ============================================
$kanbanCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform');

Route::options('/kanban-tasks', $kanbanCors);
Route::options('/kanban-tasks/{task}', $kanbanCors);
Route::options('/kanban-tasks/{task}/status', $kanbanCors);

Route::middleware(['sas.usuario'])->group(function () {
    Route::get('/kanban-tasks', [KanbanTaskController::class, 'index']);
    Route::post('/kanban-tasks', [KanbanTaskController::class, 'store']);
    Route::put('/kanban-tasks/{task}', [KanbanTaskController::class, 'update']);
    Route::delete('/kanban-tasks/{task}', [KanbanTaskController::class, 'destroy']);
    Route::patch('/kanban-tasks/{task}/status', [KanbanTaskController::class, 'updateStatus']);
});

// ============================================
// RH (Recrutamento) - API Admin
// ============================================
$rhCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform')
    ->header('Access-Control-Expose-Headers', 'Content-Disposition, Content-Type, Content-Length');

// Preflight CORS (IMPORTANTE: não passa pelo sas.usuario)
Route::options('/rh/candidatos/{id}/curriculo', $rhCors);
Route::options('/rh/candidatos/{id}/foto', $rhCors);
Route::options('/rh/candidatos/{id}', $rhCors);

Route::middleware(['sas.usuario'])->prefix('rh')->group(function () {
    // Dashboard
    Route::get('/dashboard/stats', [RhDashboardController::class, 'stats']);

    // Vagas
    Route::get('/vagas', [RhVagaController::class, 'index']);
    Route::post('/vagas', [RhVagaController::class, 'store']);
    Route::put('/vagas/{id}', [RhVagaController::class, 'update']);
    Route::delete('/vagas/{id}', [RhVagaController::class, 'destroy']);
    Route::get('/vagas/{id}/qrcode', [RhVagaController::class, 'qrcode']);

    // Candidatos
    Route::get('/candidatos', [RhCandidatoController::class, 'index']);
    Route::get('/candidatos/{id}', [RhCandidatoController::class, 'show']);
    Route::get('/candidatos/{id}/curriculo', [RhCandidatoController::class, 'downloadCurriculo']);
    Route::get('/candidatos/{id}/foto', [RhCandidatoController::class, 'downloadFoto']);
    Route::put('/candidatos/{id}/status', [RhCandidatoController::class, 'updateStatus']);
    Route::post('/candidatos/{id}/documentacao-link', [RhCandidatoController::class, 'gerarLinkDocumentacao']);
    Route::put('/candidatos/{id}/observacoes', [RhCandidatoController::class, 'updateObservacoes']);
    Route::post('/candidatos/{id}/anonimizar', [RhCandidatoController::class, 'anonymize']);
    Route::delete('/candidatos/{id}', [RhCandidatoController::class, 'destroyDefinitivo']);

    // Entrevistas
    Route::get('/entrevistas', [RhEntrevistaController::class, 'index']);
    Route::post('/entrevistas', [RhEntrevistaController::class, 'store']);
    Route::put('/entrevistas/{id}', [RhEntrevistaController::class, 'update']);

    // Documentos (pós aprovação)
    Route::get('/documentos', [RhDocumentoController::class, 'index']);
    Route::post('/candidatos/{candidatoId}/documentos', [RhDocumentoController::class, 'upload']);
    Route::get('/documentos/{id}/download', [RhDocumentoController::class, 'download']);
    Route::delete('/documentos/{id}', [RhDocumentoController::class, 'destroy']);

    // Folha de ponto
    Route::get('/folhas-ponto', [RhFolhaPontoController::class, 'index']);
    Route::post('/folhas-ponto', [RhFolhaPontoController::class, 'store']);
    Route::get('/folhas-ponto/{id}', [RhFolhaPontoController::class, 'show']);
    Route::put('/folhas-ponto/{id}', [RhFolhaPontoController::class, 'update']);
    Route::delete('/folhas-ponto/{id}', [RhFolhaPontoController::class, 'destroy']);
    Route::get('/folhas-ponto/{id}/pdf', [RhFolhaPontoController::class, 'pdf']);
});

// ============================================
// DEPLOY - Atualiza o servidor via git pull
// ============================================
Route::get('/deploy', function (Request $request) {
    $key = $request->query('key', '');
    if ($key !== 'sas2026deploy') {
        return response()->json(['error' => 'Acesso negado'], 403)
            ->header('Access-Control-Allow-Origin', '*');
    }

    $projDir = base_path('../');
    $output = [];

    exec("cd " . escapeshellarg($projDir) . " && git pull origin main 2>&1", $output, $returnCode);

    return response()->json([
        'sucesso' => $returnCode === 0,
        'output'  => implode("\n", $output),
        'dir'     => $projDir,
    ])->header('Access-Control-Allow-Origin', '*');
});
