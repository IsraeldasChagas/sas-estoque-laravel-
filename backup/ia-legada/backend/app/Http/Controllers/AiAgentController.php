<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Support\AiAgentResolver;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AiAgentController extends Controller
{
    private function authAdmin(Request $request): ?object
    {
        $usuario = $this->authUsuario($request);
        if (! $usuario) {
            return null;
        }
        if (strtoupper(trim((string) ($usuario->perfil ?? ''))) !== 'ADMIN') {
            return null;
        }

        return $usuario;
    }

    private function authUsuario(Request $request): ?object
    {
        $id = $request->header('X-Usuario-Id');
        if (! $id || ! Schema::hasTable('usuarios')) {
            return null;
        }

        return DB::table('usuarios')->where('id', $id)->first();
    }

    private function json(mixed $data, int $status = 200)
    {
        return response()->json($data, $status)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }

    private function serializar(AiAgent $agent): array
    {
        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'role' => $agent->role,
            'description' => $agent->description,
            'system_prompt' => $agent->system_prompt,
            'avatar' => $agent->avatar,
            'avatar_url' => $this->avatarUrl($agent->avatar),
            'model' => $agent->model,
            'temperature' => (float) $agent->temperature,
            'is_active' => (bool) $agent->is_active,
            'created_at' => $agent->created_at,
            'updated_at' => $agent->updated_at,
        ];
    }

    private function avatarUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        $p = ltrim($path, '/');
        if (str_starts_with($p, 'http')) {
            return $p;
        }

        $base = rtrim((string) config('app.url'), '/');
        if (str_starts_with($p, 'uploads/') || str_starts_with($p, 'storage/')) {
            return $base.'/'.$p;
        }

        return $base.'/storage/'.$p;
    }

    private function salvarAvatar(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        $dir = public_path('uploads/ai-agents');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $nomeArquivo = time().'_'.uniqid().'.'.$ext;
        $file->move($dir, $nomeArquivo);

        return 'uploads/ai-agents/'.$nomeArquivo;
    }

    private function apagarAvatarArquivo(?string $path): void
    {
        if (! $path) {
            return;
        }
        $p = ltrim($path, '/');
        if (str_starts_with($p, 'uploads/')) {
            $full = public_path($p);
            if (is_file($full)) {
                @unlink($full);
            }

            return;
        }
        if (Storage::disk('public')->exists($p)) {
            Storage::disk('public')->delete($p);
        }
    }

    public function index(Request $request)
    {
        if (! Schema::hasTable('ai_agents')) {
            return $this->json(['agents' => [], 'modules' => AiAgentResolver::moduleBindings()], 503);
        }

        $admin = $this->authAdmin($request);
        if (! $admin) {
            return $this->json(['error' => 'Somente ADMIN'], 403);
        }

        $agents = AiAgent::query()->orderByDesc('is_active')->orderBy('name')->get()
            ->map(fn (AiAgent $a) => $this->serializar($a));

        return $this->json([
            'agents' => $agents,
            'modules' => AiAgentResolver::moduleBindings(),
            'module_options' => AiAgentResolver::MODULES,
            'models_sugeridos' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1'],
        ]);
    }

    public function active(Request $request)
    {
        $module = AiAgentResolver::normalizeModule($request->query('module'));
        $agent = AiAgentResolver::resolveForModule($module);
        if (! $agent) {
            return $this->json(['agent' => null, 'module' => $module]);
        }

        return $this->json([
            'module' => $module,
            'agent' => $this->serializar($agent),
        ]);
    }

    public function show(Request $request, $id)
    {
        $admin = $this->authAdmin($request);
        if (! $admin) {
            return $this->json(['error' => 'Somente ADMIN'], 403);
        }

        $agent = AiAgent::findOrFail($id);

        return $this->json($this->serializar($agent));
    }

    public function store(Request $request)
    {
        $admin = $this->authAdmin($request);
        if (! $admin) {
            return $this->json(['error' => 'Somente ADMIN'], 403);
        }

        return $this->salvarAgente($request, null);
    }

    public function update(Request $request, $id)
    {
        $admin = $this->authAdmin($request);
        if (! $admin) {
            return $this->json(['error' => 'Somente ADMIN'], 403);
        }

        $agent = AiAgent::findOrFail($id);

        return $this->salvarAgente($request, $agent);
    }

    private function salvarAgente(Request $request, ?AiAgent $agent)
    {
        $validator = Validator::make($request->all(), [
            'name' => ($agent ? 'sometimes|' : '').'required|string|max:120',
            'role' => 'nullable|string|max:160',
            'description' => 'nullable|string',
            'system_prompt' => ($agent ? 'sometimes|' : '').'required|string',
            'model' => 'nullable|string|max:80',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'is_active' => 'nullable|boolean',
            'avatar' => 'nullable|image|max:2048',
            'remover_avatar' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->json(['error' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        unset($data['avatar'], $data['remover_avatar']);

        if (isset($data['temperature'])) {
            $data['temperature'] = round((float) $data['temperature'], 2);
        }
        if (isset($data['model']) && trim($data['model']) === '') {
            unset($data['model']);
        }
        if (! isset($data['model']) && ! $agent) {
            $data['model'] = 'gpt-4o-mini';
        }
        if (! isset($data['temperature']) && ! $agent) {
            $data['temperature'] = 0.65;
        }
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $criando = $agent === null;
        if ($agent) {
            $agent->update($data);
        } else {
            $data['is_active'] = $data['is_active'] ?? true;
            $agent = AiAgent::create($data);
        }

        if ($request->boolean('remover_avatar') && $agent->avatar) {
            $this->apagarAvatarArquivo($agent->avatar);
            $agent->update(['avatar' => null]);
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            if ($file instanceof UploadedFile && $file->isValid()) {
                if ($agent->avatar) {
                    $this->apagarAvatarArquivo($agent->avatar);
                }
                $path = $this->salvarAvatar($file);
                $agent->update(['avatar' => $path]);
            }
        }

        return $this->json([
            'message' => $criando ? 'Agente criado' : 'Agente atualizado',
            'agent' => $this->serializar($agent->fresh()),
        ], $criando ? 201 : 200);
    }

    public function toggleActive(Request $request, $id)
    {
        $admin = $this->authAdmin($request);
        if (! $admin) {
            return $this->json(['error' => 'Somente ADMIN'], 403);
        }

        $agent = AiAgent::findOrFail($id);
        $agent->update(['is_active' => ! $agent->is_active]);

        return $this->json([
            'message' => $agent->is_active ? 'Agente ativado' : 'Agente desativado',
            'agent' => $this->serializar($agent->fresh()),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $admin = $this->authAdmin($request);
        if (! $admin) {
            return $this->json(['error' => 'Somente ADMIN'], 403);
        }

        $agent = AiAgent::findOrFail($id);

        if (Schema::hasTable('ai_agent_modules')) {
            $emUso = DB::table('ai_agent_modules')->where('agent_id', $agent->id)->exists();
            if ($emUso) {
                DB::table('ai_agent_modules')->where('agent_id', $agent->id)->update([
                    'agent_id' => null,
                    'updated_at' => now(),
                ]);
            }
        }

        if ($agent->avatar) {
            $this->apagarAvatarArquivo($agent->avatar);
        }

        $agent->delete();

        return $this->json(['message' => 'Agente excluído']);
    }

    public function moduleBindings(Request $request)
    {
        $admin = $this->authAdmin($request);
        if (! $admin) {
            return $this->json(['error' => 'Somente ADMIN'], 403);
        }

        return $this->json([
            'modules' => AiAgentResolver::moduleBindings(),
            'module_options' => AiAgentResolver::MODULES,
        ]);
    }

    public function updateModuleBindings(Request $request)
    {
        $admin = $this->authAdmin($request);
        if (! $admin) {
            return $this->json(['error' => 'Somente ADMIN'], 403);
        }

        $bindings = $request->input('bindings', $request->all());
        if (! is_array($bindings)) {
            return $this->json(['error' => 'bindings inválido'], 422);
        }

        foreach (AiAgentResolver::MODULES as $key => $_) {
            if (! array_key_exists($key, $bindings)) {
                continue;
            }
            $aid = $bindings[$key];
            if ($aid !== null && $aid !== '') {
                if (! AiAgent::query()->where('id', (int) $aid)->exists()) {
                    return $this->json(['error' => "Agente inválido para módulo {$key}"], 422);
                }
            }
        }

        AiAgentResolver::salvarBindings($bindings);

        return $this->json([
            'message' => 'Módulos atualizados',
            'modules' => AiAgentResolver::moduleBindings(),
        ]);
    }
}
