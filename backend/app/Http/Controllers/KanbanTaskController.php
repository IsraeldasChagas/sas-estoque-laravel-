<?php

namespace App\Http\Controllers;

use App\Models\KanbanTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class KanbanTaskController extends Controller
{
    private const PERFIS_KANBAN = ['ADMIN', 'GERENTE', 'FINANCEIRO', 'ASSISTENTE_ADMINISTRATIVO'];

    private function usuarioAutorizadoKanban(Request $request): ?JsonResponse
    {
        $uid = $request->header('X-Usuario-Id');
        $u = $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
        if (! $u) {
            return response()->json(['error' => 'Faça login novamente. Sessão expirada ou usuário não identificado.'], 401);
        }
        $perfil = strtoupper(trim((string) ($u->perfil ?? '')));
        if (! in_array($perfil, self::PERFIS_KANBAN, true)) {
            return response()->json(['error' => 'Sem permissão para o Kanban Administrativo.'], 403);
        }

        return null;
    }

    public function showBoard(): View
    {
        $unidades = Schema::hasTable('unidades')
            ? DB::table('unidades')->orderBy('nome')->get(['id', 'nome'])
            : collect();

        return view('kanban.administrativo', [
            'unidades' => $unidades,
            'apiBase' => url('/api'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        if ($e = $this->usuarioAutorizadoKanban($request)) {
            return $e;
        }

        $q = KanbanTask::query()->with('unidade:id,nome');

        if ($request->filled('unidade_id')) {
            $q->where('unidade_id', (int) $request->query('unidade_id'));
        }
        if ($request->filled('setor')) {
            $q->where('setor', $request->query('setor'));
        }
        if ($request->filled('prioridade')) {
            $q->where('prioridade', $request->query('prioridade'));
        }

        $tasks = $q
            ->orderByRaw('prazo IS NULL')
            ->orderBy('prazo')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        if ($e = $this->usuarioAutorizadoKanban($request)) {
            return $e;
        }

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'unidade_id' => 'required|integer|exists:unidades,id',
            'setor' => 'required|string|max:80',
            'responsavel' => 'nullable|string|max:255',
            'prioridade' => 'required|in:baixa,media,alta',
            'status' => 'required|in:planejamento,a_fazer,em_execucao,aguardando,finalizado',
            'prazo' => 'nullable|date',
            'observacoes' => 'nullable|string',
        ]);

        $task = KanbanTask::create($validated);
        $task->load('unidade:id,nome');

        return response()->json($task, 201);
    }

    public function update(Request $request, KanbanTask $task): JsonResponse
    {
        if ($e = $this->usuarioAutorizadoKanban($request)) {
            return $e;
        }

        $validated = $request->validate([
            'titulo' => 'sometimes|required|string|max:255',
            'descricao' => 'nullable|string',
            'unidade_id' => 'sometimes|required|integer|exists:unidades,id',
            'setor' => 'sometimes|required|string|max:80',
            'responsavel' => 'nullable|string|max:255',
            'prioridade' => 'sometimes|required|in:baixa,media,alta',
            'status' => 'sometimes|required|in:planejamento,a_fazer,em_execucao,aguardando,finalizado',
            'prazo' => 'nullable|date',
            'observacoes' => 'nullable|string',
        ]);

        $task->update($validated);
        $task->load('unidade:id,nome');

        return response()->json($task);
    }

    public function destroy(Request $request, KanbanTask $task): JsonResponse
    {
        if ($e = $this->usuarioAutorizadoKanban($request)) {
            return $e;
        }

        $task->delete();

        return response()->json(['ok' => true]);
    }

    public function updateStatus(Request $request, KanbanTask $task): JsonResponse
    {
        if ($e = $this->usuarioAutorizadoKanban($request)) {
            return $e;
        }

        $validated = $request->validate([
            'status' => 'required|in:planejamento,a_fazer,em_execucao,aguardando,finalizado',
        ]);

        $task->update(['status' => $validated['status']]);
        $task->load('unidade:id,nome');

        return response()->json($task);
    }
}
