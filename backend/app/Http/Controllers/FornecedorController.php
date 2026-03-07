<?php

namespace App\Http\Controllers;

use App\Models\Fornecedor;
use App\Models\FornecedorBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class FornecedorController extends Controller
{
    /**
     * Verifica se o usuário logado é ADMIN.
     */
    private function requireAdmin(Request $request): ?array
    {
        $usuarioId = $request->header('X-Usuario-Id');
        if (!$usuarioId) {
            return null;
        }
        $usuario = DB::table('usuarios')->where('id', $usuarioId)->first();
        if (!$usuario || strtoupper(trim($usuario->perfil ?? '')) !== 'ADMIN') {
            return null;
        }
        return (array) $usuario;
    }

    /**
     * Conta vínculos do fornecedor (boletos, lotes, movimentacoes).
     */
    private function contarVinculos(int $fornecedorId): array
    {
        $boletos = Schema::hasTable('boletos') && Schema::hasColumn('boletos', 'fornecedor_id')
            ? DB::table('boletos')->where('fornecedor_id', $fornecedorId)->count()
            : 0;
        $lotes = Schema::hasTable('lotes') && Schema::hasColumn('lotes', 'fornecedor_id')
            ? DB::table('lotes')->where('fornecedor_id', $fornecedorId)->count()
            : 0;
        $movimentacoes = Schema::hasTable('movimentacoes') && Schema::hasColumn('movimentacoes', 'fornecedor_id')
            ? DB::table('movimentacoes')->where('fornecedor_id', $fornecedorId)->count()
            : 0;

        return [
            'boletos' => $boletos,
            'lotes' => $lotes,
            'movimentacoes' => $movimentacoes,
            'total' => $boletos + $lotes + $movimentacoes,
        ];
    }

    /**
     * Lista fornecedores.
     */
    public function index(Request $request)
    {
        $query = Fornecedor::query();
        if ($request->has('ativo') && $request->ativo !== '') {
            $query->where('ativo', (bool) $request->ativo);
        }
        if ($request->has('search') && trim($request->search) !== '') {
            $s = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($s) {
                $q->where('nome', 'like', $s)
                  ->orWhere('cnpj', 'like', $s)
                  ->orWhere('cpf', 'like', $s)
                  ->orWhere('email', 'like', $s);
            });
        }
        $fornecedores = $query->orderBy('nome')->get();
        return response()->json($fornecedores);
    }

    /**
     * Exibe um fornecedor.
     */
    public function show($id)
    {
        $fornecedor = Fornecedor::find($id);
        if (!$fornecedor) {
            return response()->json(['error' => 'Fornecedor não encontrado'], 404);
        }
        $vinculos = $this->contarVinculos((int) $id);
        return response()->json([
            'fornecedor' => $fornecedor,
            'vinculos' => $vinculos,
        ]);
    }

    /**
     * Verifica se o fornecedor possui histórico (para modal de exclusão).
     */
    public function checkHistorico($id)
    {
        $fornecedor = Fornecedor::find($id);
        if (!$fornecedor) {
            return response()->json(['error' => 'Fornecedor não encontrado'], 404);
        }
        $vinculos = $this->contarVinculos((int) $id);
        return response()->json([
            'possui_historico' => $vinculos['total'] > 0,
            'vinculos' => $vinculos,
        ]);
    }

    /**
     * Cria fornecedor.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'cpf' => 'nullable|string|max:14',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'endereco' => 'nullable|string|max:500',
            'observacoes' => 'nullable|string',
            'ativo' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
        }
        $fornecedor = Fornecedor::create($request->only([
            'nome', 'cnpj', 'cpf', 'email', 'telefone', 'endereco', 'observacoes', 'ativo'
        ]));
        return response()->json($fornecedor, 201);
    }

    /**
     * Atualiza fornecedor.
     */
    public function update(Request $request, $id)
    {
        $fornecedor = Fornecedor::find($id);
        if (!$fornecedor) {
            return response()->json(['error' => 'Fornecedor não encontrado'], 404);
        }
        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'cpf' => 'nullable|string|max:14',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'endereco' => 'nullable|string|max:500',
            'observacoes' => 'nullable|string',
            'ativo' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
        }
        $fornecedor->update($request->only([
            'nome', 'cnpj', 'cpf', 'email', 'telefone', 'endereco', 'observacoes', 'ativo'
        ]));
        return response()->json($fornecedor);
    }

    /**
     * Inativa fornecedor.
     */
    public function desativar(Request $request, $id)
    {
        $fornecedor = Fornecedor::find($id);
        if (!$fornecedor) {
            return response()->json(['error' => 'Fornecedor não encontrado'], 404);
        }
        $fornecedor->update(['ativo' => false]);
        return response()->json($fornecedor);
    }

    /**
     * Ativa fornecedor.
     */
    public function ativar(Request $request, $id)
    {
        $fornecedor = Fornecedor::find($id);
        if (!$fornecedor) {
            return response()->json(['error' => 'Fornecedor não encontrado'], 404);
        }
        $fornecedor->update(['ativo' => true]);
        return response()->json($fornecedor);
    }

    /**
     * Exclusão direta (sem histórico) ou exclusão com backup (com histórico).
     * Requer perfil ADMIN.
     */
    public function destroy(Request $request, $id)
    {
        $usuario = $this->requireAdmin($request);
        if (!$usuario) {
            return response()->json(['error' => 'Apenas administradores podem excluir fornecedores'], 403);
        }

        $fornecedor = Fornecedor::find($id);
        if (!$fornecedor) {
            return response()->json(['error' => 'Fornecedor não encontrado'], 404);
        }

        $vinculos = $this->contarVinculos((int) $id);
        $comBackup = (bool) $request->input('com_backup', false);

        if ($vinculos['total'] > 0 && !$comBackup) {
            return response()->json([
                'error' => 'fornecedor_com_historico',
                'message' => 'Este fornecedor possui histórico no sistema. Utilize a opção "Fazer backup e excluir".',
                'vinculos' => $vinculos,
            ], 400);
        }

        try {
            DB::beginTransaction();

            if ($vinculos['total'] > 0 && $comBackup) {
                $dados = $fornecedor->toArray();
                FornecedorBackup::create([
                    'fornecedor_id_original' => $fornecedor->id,
                    'dados_fornecedor' => $dados,
                    'data_backup' => now(),
                    'usuario_exclusao' => $usuario['id'] ?? null,
                    'motivo_exclusao' => $request->input('motivo_exclusao'),
                ]);
            }

            $fornecedor->delete();
            DB::commit();
            return response()->json(['message' => 'Fornecedor excluído com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erro ao excluir fornecedor: ' . $e->getMessage());
            return response()->json(['error' => 'Erro ao excluir fornecedor'], 500);
        }
    }
}
