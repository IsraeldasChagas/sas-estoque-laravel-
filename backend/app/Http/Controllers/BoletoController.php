<?php

namespace App\Http\Controllers;

use App\Models\Boleto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BoletoController extends Controller
{
    /**
     * Lista todos os boletos
     */
    public function index(Request $request)
    {
        \Log::info('📊 BoletoController::index - Listando boletos');
        \Log::info('📥 Filtros recebidos:', $request->all());
        
        try {
            $query = Boleto::query();

            // Filtro por unidade
            if ($request->has('unidade_id') && $request->unidade_id) {
                $query->where('unidade_id', $request->unidade_id);
                \Log::info('🏢 Filtrando por unidade: ' . $request->unidade_id);
            }

            // Filtro por status
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
                \Log::info('📌 Filtrando por status: ' . $request->status);
            }

            // Filtro por mes/ano
            if ($request->has('mes_ano') && $request->mes_ano) {
                $mesAno = explode('-', $request->mes_ano);
                if (count($mesAno) == 2) {
                    $ano = $mesAno[0];
                    $mes = $mesAno[1];
                    $query->whereYear('data_vencimento', $ano)
                          ->whereMonth('data_vencimento', $mes);
                    \Log::info("📅 Filtrando por mês/ano: {$mes}/{$ano}");
                }
            }

            // Filtro por periodo
            if ($request->has('data_inicio')) {
                $query->where('data_vencimento', '>=', $request->data_inicio);
                \Log::info('📅 Data início: ' . $request->data_inicio);
            }
            if ($request->has('data_fim')) {
                $query->where('data_vencimento', '<=', $request->data_fim);
                \Log::info('📅 Data fim: ' . $request->data_fim);
            }

            $boletos = $query->orderBy('data_vencimento', 'desc')->get();
            
            \Log::info("✅ Total de boletos encontrados: " . $boletos->count());

            return response()->json($boletos);
        } catch (\Exception $e) {
            \Log::error('❌ Erro ao buscar boletos: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Erro ao buscar boletos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo boleto
     */
    public function store(Request $request)
    {
        \Log::info('🚀 BoletoController::store - Iniciando criação de boleto');
        \Log::info('📥 Dados recebidos:', $request->all());
        
        // Normaliza campos vazios ANTES da validação (evita falha em exists)
        $input = $request->all();
        if (isset($input['unidade_id']) && $input['unidade_id'] === '') {
            $request->merge(['unidade_id' => null]);
        }
        if (isset($input['data_pagamento']) && $input['data_pagamento'] === '') {
            $request->merge(['data_pagamento' => null]);
        }
        
        $validator = Validator::make($request->all(), [
            'fornecedor' => 'required|string|max:255',
            'descricao' => 'required|string|max:255',
            'data_vencimento' => 'required|date',
            'valor' => 'required|numeric|min:0.01',
            'unidade_id' => 'nullable|exists:unidades,id',
            'categoria' => 'nullable|string',
            'status' => 'required|in:A_VENCER,VENCIDO,PAGO,CANCELADO',
            'data_pagamento' => 'nullable|date',
            'valor_pago' => 'nullable|numeric|min:0',
            'juros_multa' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
            'anexo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB
            'is_recorrente' => 'nullable|boolean',
            'meses_recorrencia' => 'nullable|integer|min:1|max:60',
            'grupo_recorrencia' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            \Log::warning('❌ Validação falhou:', $validator->errors()->toArray());
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->all();
            
            // Adiciona o ID do usuário logado via header
            $usuarioId = $request->header('X-Usuario-Id');
            if ($usuarioId) {
                $data['usuario_id'] = $usuarioId;
                \Log::info('👤 Usuario ID: ' . $usuarioId);
            }

            // Se não informou juros/multa, define como 0
            if (!isset($data['juros_multa'])) {
                $data['juros_multa'] = 0;
            }

            // Processa upload do anexo
            if ($request->hasFile('anexo')) {
                \Log::info('📎 Processando anexo...');
                $file = $request->file('anexo');
                $nomeOriginal = $file->getClientOriginalName();
                $extensao = $file->getClientOriginalExtension();
                $nomeArquivo = time() . '_' . uniqid() . '.' . $extensao;
                
                // Salva o arquivo na pasta storage/app/public/boletos
                $path = $file->storeAs('boletos', $nomeArquivo, 'public');
                
                $data['anexo_path'] = $path;
                $data['anexo_nome'] = $nomeOriginal;
                $data['anexo_tipo'] = $extensao;
                
                \Log::info('✅ Anexo salvo: ' . $path);
            }

            // Remove anexo (arquivo) - já processado acima em anexo_path/nome/tipo
            unset($data['anexo']);

            \Log::info('💾 Criando boleto no banco...');
            $boleto = Boleto::create($data);

            \Log::info('✅ Boleto criado com sucesso - ID: ' . $boleto->id);

            return response()->json([
                'message' => 'Boleto criado com sucesso',
                'boleto' => $boleto
            ], 201);
        } catch (\Exception $e) {
            \Log::error('❌ Erro ao criar boleto: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'message' => 'Erro ao criar boleto',
                'error' => $e->getMessage(),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Exibe um boleto específico
     */
    public function show($id)
    {
        try {
            $boleto = Boleto::findOrFail($id);
            return response()->json($boleto);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Boleto não encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Atualiza um boleto
     */
    public function update(Request $request, $id)
    {
        // Normaliza campos vazios antes da validação (mesmo que store)
        $input = $request->all();
        if (isset($input['unidade_id']) && $input['unidade_id'] === '') {
            $request->merge(['unidade_id' => null]);
        }
        if (isset($input['data_pagamento']) && $input['data_pagamento'] === '') {
            $request->merge(['data_pagamento' => null]);
        }

        $validator = Validator::make($request->all(), [
            'fornecedor' => 'sometimes|required|string|max:255',
            'descricao' => 'sometimes|required|string|max:255',
            'data_vencimento' => 'sometimes|required|date',
            'valor' => 'sometimes|required|numeric|min:0.01',
            'unidade_id' => 'nullable|exists:unidades,id',
            'categoria' => 'nullable|string',
            'status' => 'sometimes|required|in:A_VENCER,VENCIDO,PAGO,CANCELADO',
            'data_pagamento' => 'nullable|date',
            'valor_pago' => 'nullable|numeric|min:0',
            'juros_multa' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
            'anexo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120' // 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $boleto = Boleto::findOrFail($id);
            $data = $request->all();

            // Remove anexo (arquivo) - será processado separadamente se houver
            unset($data['anexo']);

            // Processa upload do anexo se houver
            if ($request->hasFile('anexo')) {
                // Remove arquivo antigo se existir
                if ($boleto->anexo_path && \Storage::disk('public')->exists($boleto->anexo_path)) {
                    \Storage::disk('public')->delete($boleto->anexo_path);
                }

                $file = $request->file('anexo');
                $nomeOriginal = $file->getClientOriginalName();
                $extensao = $file->getClientOriginalExtension();
                $nomeArquivo = time() . '_' . uniqid() . '.' . $extensao;
                
                $path = $file->storeAs('boletos', $nomeArquivo, 'public');
                
                $data['anexo_path'] = $path;
                $data['anexo_nome'] = $nomeOriginal;
                $data['anexo_tipo'] = $extensao;
            }

            $boleto->update($data);

            return response()->json([
                'message' => 'Boleto atualizado com sucesso',
                'boleto' => $boleto
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar boleto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove um boleto
     */
    public function destroy($id)
    {
        try {
            $boleto = Boleto::findOrFail($id);
            $boleto->delete();

            return response()->json([
                'message' => 'Boleto excluído com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao excluir boleto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resumo financeiro de boletos
     */
    public function resumo(Request $request)
    {
        \Log::info('💰 BoletoController::resumo - Gerando resumo financeiro');
        \Log::info('📥 Filtros recebidos:', $request->all());
        
        try {
            $query = Boleto::query();

            // Filtro por mes/ano
            if ($request->has('mes_ano') && $request->mes_ano) {
                $mesAno = explode('-', $request->mes_ano);
                if (count($mesAno) == 2) {
                    $ano = $mesAno[0];
                    $mes = $mesAno[1];
                    $query->whereYear('data_vencimento', $ano)
                          ->whereMonth('data_vencimento', $mes);
                    \Log::info("📅 Filtrando resumo por: {$mes}/{$ano}");
                }
            } else {
                \Log::info('📅 Resumo SEM filtro (todos os boletos)');
            }

            $boletos = $query->get();
            \Log::info("📊 Total de boletos no resumo: " . $boletos->count());

            $totalMes = $boletos->sum('valor');
            $pagoEmDia = $boletos->where('status', 'PAGO')
                                 ->where('juros_multa', 0)
                                 ->sum('valor_pago');
            $jurosPagos = $boletos->where('status', 'PAGO')->sum('juros_multa');
            
            // Calcula boletos pagos com atraso
            // Um boleto é considerado pago com atraso se:
            // 1. Tem juros/multa (juros_multa > 0), OU
            // 2. Data de pagamento é posterior à data de vencimento
            $boletosPagosComAtraso = $boletos->where('status', 'PAGO')
                ->filter(function($boleto) {
                    // Se tem juros/multa, foi pago com atraso
                    if ($boleto->juros_multa > 0) {
                        return true;
                    }
                    // Se data de pagamento > data de vencimento, foi pago com atraso
                    if ($boleto->data_pagamento && $boleto->data_vencimento) {
                        return $boleto->data_pagamento > $boleto->data_vencimento;
                    }
                    return false;
                })
                ->count();
            
            // Economia = valor que PODERIA ter pago de juros mas não pagou
            $valorPotencialJuros = $boletos->where('status', 'PAGO')->sum('valor') * 0.1; // estimativa 10%
            $economia = $valorPotencialJuros - $jurosPagos;

            $resumo = [
                'total_mes' => $totalMes,
                'pago_em_dia' => $pagoEmDia,
                'juros_pagos' => $jurosPagos,
                'economia' => max(0, $economia),
                'boletos_pagos_com_atraso' => $boletosPagosComAtraso,
                'total_boletos' => $boletos->count(),
                'boletos_pagos' => $boletos->where('status', 'PAGO')->count(),
                'boletos_vencidos' => $boletos->where('status', 'VENCIDO')->count(),
                'boletos_a_vencer' => $boletos->where('status', 'A_VENCER')->count(),
            ];
            
            \Log::info('✅ Resumo gerado:', $resumo);

            return response()->json($resumo);
        } catch (\Exception $e) {
            \Log::error('❌ Erro ao gerar resumo: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Erro ao gerar resumo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Economia por mês (últimos 6 meses)
     */
    public function economiaMensal()
    {
        try {
            $meses = [];
            
            for ($i = 5; $i >= 0; $i--) {
                $data = now()->subMonths($i);
                $mes = $data->format('m');
                $ano = $data->format('Y');
                
                $boletos = Boleto::whereYear('data_vencimento', $ano)
                                 ->whereMonth('data_vencimento', $mes)
                                 ->where('status', 'PAGO')
                                 ->get();
                
                $valorTotal = $boletos->sum('valor');
                $jurosPagos = $boletos->sum('juros_multa');
                $valorPotencialJuros = $valorTotal * 0.1; // estimativa 10%
                $economia = max(0, $valorPotencialJuros - $jurosPagos);
                
                $meses[] = [
                    'mes' => $data->format('M'),
                    'mes_completo' => $data->format('F Y'),
                    'economia' => $economia,
                    'mes_ano' => $data->format('Y-m')
                ];
            }

            return response()->json($meses);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao gerar economia mensal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download do anexo do boleto
     */
    public function downloadAnexo($id)
    {
        try {
            $boleto = Boleto::findOrFail($id);
            
            if (!$boleto->anexo_path) {
                return response()->json([
                    'message' => 'Este boleto não possui anexo'
                ], 404);
            }

            $path = storage_path('app/public/' . $boleto->anexo_path);
            
            if (!file_exists($path)) {
                return response()->json([
                    'message' => 'Arquivo não encontrado'
                ], 404);
            }

            return response()->download($path, $boleto->anexo_nome);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao baixar anexo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove o anexo do boleto
     */
    public function removerAnexo($id)
    {
        try {
            $boleto = Boleto::findOrFail($id);
            
            if ($boleto->anexo_path && \Storage::disk('public')->exists($boleto->anexo_path)) {
                \Storage::disk('public')->delete($boleto->anexo_path);
            }

            $boleto->update([
                'anexo_path' => null,
                'anexo_nome' => null,
                'anexo_tipo' => null
            ]);

            return response()->json([
                'message' => 'Anexo removido com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao remover anexo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
