<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EntradaEstoqueService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EntradaEstoqueController extends Controller
{
    public function __construct(
        private EntradaEstoqueService $entradaEstoqueService
    ) {}

    /**
     * POST /api/estoque/entradas
     * Centraliza registro de entrada de estoque com geração automática de lote.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'produto_id' => 'required|integer|exists:produtos,id',
                'unidade_id' => 'required|integer|exists:unidades,id',
                'quantidade' => 'required|numeric|min:0.001',
                'qtd' => 'nullable|numeric|min:0.001',
                'custo_unitario' => 'required|numeric|min:0',
                'usuario_id' => 'required|integer|exists:usuarios,id',
                'numero_lote' => 'nullable|string|max:255',
                'data_fabricacao' => 'nullable|date',
                'data_validade' => 'nullable|date',
                'local_id' => 'nullable|integer|exists:locais,id',
                'motivo' => 'nullable|string|max:500',
                'observacao' => 'nullable|string',
                'origem' => 'nullable|string|in:DASHBOARD,LISTA_COMPRAS',
            ]);

            $dados = [
                'produto_id' => $validated['produto_id'],
                'unidade_id' => $validated['unidade_id'],
                'quantidade' => $validated['quantidade'] ?? $validated['qtd'] ?? 0,
                'custo_unitario' => $validated['custo_unitario'],
                'usuario_id' => $validated['usuario_id'],
                'numero_lote' => $validated['numero_lote'] ?? null,
                'data_fabricacao' => $validated['data_fabricacao'] ?? null,
                'data_validade' => $validated['data_validade'] ?? null,
                'local_id' => $validated['local_id'] ?? null,
                'motivo' => $validated['motivo'] ?? 'Entrada de estoque',
                'observacao' => $validated['observacao'] ?? null,
                'origem' => $validated['origem'] ?? 'DASHBOARD',
            ];

            $resultado = $this->entradaEstoqueService->registrarEntrada($dados);

            return response()->json([
                'success' => true,
                'message' => 'Entrada registrada com sucesso.',
                'lote' => $resultado['lote'],
                'movimentacao' => $resultado['movimentacao'],
                'lote_id' => $resultado['lote_id'],
                'movimentacao_id' => $resultado['movimentacao_id'],
                'details' => [
                    'produto' => \Illuminate\Support\Facades\DB::table('produtos')->where('id', $dados['produto_id'])->value('nome') ?? 'N/A',
                    'quantidade' => $dados['quantidade'],
                    'lote' => $resultado['codigo_lote'],
                    'unidade' => \Illuminate\Support\Facades\DB::table('unidades')->where('id', $dados['unidade_id'])->value('nome') ?? 'N/A',
                ],
            ], 201)->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Dados inválidos',
                'message' => collect($e->errors())->flatten()->first(),
                'details' => $e->errors(),
            ], 422)->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'error' => 'Erro de validação',
                'message' => $e->getMessage(),
            ], 400)->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Exception $e) {
            \Log::error('Erro ao registrar entrada: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => 'Erro ao registrar entrada',
                'message' => 'Ocorreu um erro ao processar a entrada. Tente novamente ou entre em contato com o suporte.',
            ], 500)->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }
}
