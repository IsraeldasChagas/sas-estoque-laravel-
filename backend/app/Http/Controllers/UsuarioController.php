<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UsuarioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            Log::info('📋 Listando usuários');
            
            $query = Usuario::query();
            
            // Filtro por perfil
            if ($request->has('perfil')) {
                $query->where('perfil', strtoupper($request->perfil));
            }
            
            // Filtro por status ativo
            if ($request->has('ativo')) {
                $query->where('ativo', $request->ativo);
            }
            
            // Ordenação
            $query->orderBy('nome', 'asc');
            
            $usuarios = $query->get();
            
            Log::info('✅ Usuários listados', ['total' => $usuarios->count()]);
            
            return response()->json($usuarios);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar usuários: ' . $e->getMessage());
            return response()->json(['error' => 'Erro ao listar usuários'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            Log::info('📝 Criando novo usuário', [
                'nome' => $request->nome,
                'email' => $request->email,
                'perfil' => $request->perfil,
            ]);
            
            // Validação
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:120',
                'email' => 'required|email|max:150|unique:usuarios,email',
                'senha' => 'required|string|min:6',
                'perfil' => 'required|string|max:50',
                'unidade_id' => 'nullable|integer|exists:unidades,id',
                'ativo' => 'nullable|boolean',
                'foto' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            ]);
            
            if ($validator->fails()) {
                Log::warning('❌ Validação falhou', ['errors' => $validator->errors()]);
                return response()->json([
                    'error' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Prepara dados
            $data = [
                'nome' => $request->nome,
                'email' => $request->email,
                'senha_hash' => Hash::make($request->senha),
                'perfil' => strtoupper($request->perfil),
                'unidade_id' => $request->unidade_id,
                'ativo' => $request->ativo ?? 1,
            ];
            
            // Upload de foto
            if ($request->hasFile('foto')) {
                $foto = $request->file('foto');
                $nomeArquivo = time() . '_' . $foto->getClientOriginalName();
                $path = $foto->storeAs('usuarios', $nomeArquivo, 'public');
                $data['foto_path'] = $path;
                Log::info('📸 Foto enviada', ['path' => $path]);
            }
            
            // Cria usuário
            $usuario = Usuario::create($data);
            
            Log::info('✅ Usuário criado', ['id' => $usuario->id]);
            
            return response()->json($usuario, 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar usuário: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Erro ao criar usuário: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            Log::info('👁️ Buscando usuário', ['id' => $id]);
            
            $usuario = Usuario::findOrFail($id);
            
            Log::info('✅ Usuário encontrado', ['id' => $usuario->id]);
            
            return response()->json($usuario);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar usuário: ' . $e->getMessage());
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            Log::info('✏️ Atualizando usuário', [
                'id' => $id,
                'nome' => $request->nome,
                'email' => $request->email,
                'perfil' => $request->perfil,
            ]);
            
            $usuario = Usuario::findOrFail($id);
            
            // Se for atualização parcial (só ativo), processa direto
            if ($request->has('ativo') && !$request->has('nome') && !$request->has('email') && !$request->has('perfil')) {
                $usuario->update(['ativo' => $request->ativo]);
                Log::info('✅ Status do usuário atualizado', ['id' => $usuario->id, 'ativo' => $request->ativo]);
                return response()->json($usuario);
            }

            // Validação completa para edição de dados
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:120',
                'email' => 'required|email|max:150|unique:usuarios,email,' . $id,
                'senha' => 'nullable|string|min:6',
                'perfil' => 'required|string|max:50',
                'unidade_id' => 'nullable|integer|exists:unidades,id',
                'ativo' => 'nullable|boolean',
                'foto' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            ]);
            
            if ($validator->fails()) {
                Log::warning('❌ Validação falhou', ['errors' => $validator->errors()]);
                return response()->json([
                    'error' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Prepara dados
            $data = [
                'nome' => $request->nome,
                'email' => $request->email,
                'perfil' => strtoupper($request->perfil),
                'unidade_id' => $request->unidade_id,
                'ativo' => $request->ativo ?? $usuario->ativo,
            ];
            
            // Atualiza senha se fornecida
            if ($request->filled('senha')) {
                $data['senha_hash'] = Hash::make($request->senha);
                Log::info('🔐 Senha atualizada');
            }
            
            // Upload de nova foto
            if ($request->hasFile('foto')) {
                // Remove foto antiga
                if ($usuario->foto_path) {
                    Storage::disk('public')->delete($usuario->foto_path);
                    Log::info('🗑️ Foto antiga removida');
                }
                
                $foto = $request->file('foto');
                $nomeArquivo = time() . '_' . $foto->getClientOriginalName();
                $path = $foto->storeAs('usuarios', $nomeArquivo, 'public');
                $data['foto_path'] = $path;
                Log::info('📸 Nova foto enviada', ['path' => $path]);
            }
            
            // Remove foto se solicitado
            if ($request->filled('remove_foto') && $request->remove_foto == '1') {
                if ($usuario->foto_path) {
                    Storage::disk('public')->delete($usuario->foto_path);
                    Log::info('🗑️ Foto removida');
                }
                $data['foto_path'] = null;
            }
            
            // Atualiza usuário
            $usuario->update($data);
            
            Log::info('✅ Usuário atualizado', ['id' => $usuario->id]);
            
            return response()->json($usuario);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar usuário: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Erro ao atualizar usuário: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            Log::info('🗑️ Removendo usuário', ['id' => $id]);
            
            $usuario = Usuario::findOrFail($id);
            
            // Remove foto se existir
            if ($usuario->foto_path) {
                Storage::disk('public')->delete($usuario->foto_path);
                Log::info('🗑️ Foto removida');
            }
            
            // Deleta usuário
            $usuario->delete();
            
            Log::info('✅ Usuário removido', ['id' => $id]);
            
            return response()->json(['message' => 'Usuário removido com sucesso']);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao remover usuário: ' . $e->getMessage());
            return response()->json(['error' => 'Erro ao remover usuário'], 500);
        }
    }

    /**
     * Login de usuário
     */
    public function login(Request $request)
    {
        try {
            Log::info('🔐 Tentativa de login', ['email' => $request->email]);
            
            // Validação
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'senha' => 'required|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Busca usuário
            $usuario = Usuario::where('email', $request->email)
                             ->where('ativo', 1)
                             ->first();
            
            if (!$usuario) {
                Log::warning('❌ Usuário não encontrado ou inativo', ['email' => $request->email]);
                return response()->json(['error' => 'Credenciais inválidas'], 401);
            }
            
            // Verifica senha
            if (!Hash::check($request->senha, $usuario->senha_hash)) {
                Log::warning('❌ Senha incorreta', ['email' => $request->email]);
                return response()->json(['error' => 'Credenciais inválidas'], 401);
            }
            
            Log::info('✅ Login bem-sucedido', ['id' => $usuario->id, 'email' => $usuario->email]);
            
            return response()->json($usuario);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro no login: ' . $e->getMessage());
            return response()->json(['error' => 'Erro ao fazer login'], 500);
        }
    }
}
