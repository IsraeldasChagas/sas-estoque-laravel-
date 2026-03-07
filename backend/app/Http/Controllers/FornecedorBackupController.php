<?php

namespace App\Http\Controllers;

use App\Models\Fornecedor;
use App\Models\FornecedorBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FornecedorBackupController extends Controller
{
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
     * Lista backups de fornecedores excluídos.
     */
    public function index(Request $request)
    {
        $usuario = $this->requireAdmin($request);
        if (!$usuario) {
            return response()->json(['error' => 'Apenas administradores podem acessar o backup de fornecedores'], 403);
        }

        $backups = FornecedorBackup::where('restaurado', false)
            ->orderBy('data_backup', 'desc')
            ->get();

        $result = $backups->map(function ($b) {
            $dados = $b->dados_fornecedor ?? [];
            return [
                'id' => $b->id,
                'fornecedor_id_original' => $b->fornecedor_id_original,
                'nome_fornecedor' => $dados['nome'] ?? '-',
                'cnpj_cpf' => $dados['cnpj'] ?? $dados['cpf'] ?? '-',
                'data_exclusao' => $b->data_backup?->format('Y-m-d H:i'),
                'usuario_exclusao_id' => $b->usuario_exclusao,
                'usuario_exclusao_nome' => $b->usuario_exclusao
                    ? (DB::table('usuarios')->where('id', $b->usuario_exclusao)->value('nome') ?? '-')
                    : '-',
            ];
        });

        return response()->json($result);
    }

    /**
     * Detalhes de um backup.
     */
    public function show(Request $request, $id)
    {
        $usuario = $this->requireAdmin($request);
        if (!$usuario) {
            return response()->json(['error' => 'Apenas administradores podem acessar o backup de fornecedores'], 403);
        }

        $backup = FornecedorBackup::find($id);
        if (!$backup) {
            return response()->json(['error' => 'Backup não encontrado'], 404);
        }
        if ($backup->restaurado) {
            return response()->json(['error' => 'Este backup já foi restaurado'], 400);
        }

        $dados = $backup->dados_fornecedor ?? [];
        return response()->json([
            'id' => $backup->id,
            'fornecedor_id_original' => $backup->fornecedor_id_original,
            'dados_fornecedor' => $dados,
            'data_backup' => $backup->data_backup?->format('Y-m-d H:i:s'),
            'usuario_exclusao' => $backup->usuario_exclusao,
            'usuario_exclusao_nome' => $backup->usuario_exclusao
                ? (DB::table('usuarios')->where('id', $backup->usuario_exclusao)->value('nome') ?? '-')
                : '-',
            'motivo_exclusao' => $backup->motivo_exclusao,
        ]);
    }

    /**
     * Restaura fornecedor a partir do backup.
     */
    public function restaurar(Request $request, $id)
    {
        $usuario = $this->requireAdmin($request);
        if (!$usuario) {
            return response()->json(['error' => 'Apenas administradores podem restaurar fornecedores'], 403);
        }

        $backup = FornecedorBackup::find($id);
        if (!$backup) {
            return response()->json(['error' => 'Backup não encontrado'], 404);
        }
        if ($backup->restaurado) {
            return response()->json(['error' => 'Este backup já foi restaurado'], 400);
        }

        $dados = $backup->dados_fornecedor ?? [];
        if (empty($dados) || empty($dados['nome'] ?? null)) {
            return response()->json(['error' => 'Dados do backup inválidos'], 400);
        }

        try {
            DB::beginTransaction();

            $attrs = [
                'nome', 'cnpj', 'cpf', 'email', 'telefone', 'endereco', 'observacoes', 'ativo',
            ];
            $createData = [];
            foreach ($attrs as $attr) {
                if (array_key_exists($attr, $dados)) {
                    $createData[$attr] = $dados[$attr];
                }
            }
            $createData['ativo'] = $createData['ativo'] ?? true;

            $novo = Fornecedor::create($createData);
            $backup->update(['restaurado' => true]);

            DB::commit();
            return response()->json([
                'message' => 'Fornecedor restaurado com sucesso.',
                'fornecedor' => $novo,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erro ao restaurar fornecedor: ' . $e->getMessage());
            return response()->json(['error' => 'Erro ao restaurar fornecedor'], 500);
        }
    }

    /**
     * Exclui backup definitivamente.
     */
    public function destroy(Request $request, $id)
    {
        $usuario = $this->requireAdmin($request);
        if (!$usuario) {
            return response()->json(['error' => 'Apenas administradores podem excluir backups'], 403);
        }

        $backup = FornecedorBackup::find($id);
        if (!$backup) {
            return response()->json(['error' => 'Backup não encontrado'], 404);
        }

        $backup->delete();
        return response()->json(['message' => 'Backup excluído definitivamente']);
    }
}
