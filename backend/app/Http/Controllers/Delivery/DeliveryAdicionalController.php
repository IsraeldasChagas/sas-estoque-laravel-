<?php

namespace App\Http\Controllers\Delivery;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryAdicionalController extends DeliveryBaseController
{
    private const FOTO_MAX_BYTES = 2 * 1024 * 1024;

    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function index(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryAdicionais');
        $query = DB::table('dlv_adicionais as a')
            ->select('a.*')
            ->selectSub(function ($query) {
                $query->from('dlv_produto_adicional as pa')
                    ->selectRaw('count(distinct pa.produto_id)')
                    ->whereColumn('pa.adicional_id', 'a.id');
            }, 'product_count');
        $this->access->aplicarEscopo($query, $usuario, $request, 'a.unidade_id');

        if ($tipo = trim((string) $request->query('tipo', ''))) {
            $query->where('a.tipo', $tipo);
        }
        if ($request->has('ativo')) {
            $query->where('a.ativo', filter_var($request->query('ativo'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }
        if ($busca = trim((string) $request->query('busca', ''))) {
            $query->where('a.nome', 'like', '%'.$busca.'%');
        }

        $items = $query->orderBy('a.ordem')->orderBy('a.nome')->get()
            ->map(fn ($row) => $this->formatar($row))
            ->values();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryAdicionais');
        $data = $this->validar($request);
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $data);
        $data['nome'] = trim($data['nome']);
        if ($data['nome'] === '') {
            throw ValidationException::withMessages(['nome' => 'O nome do adicional é obrigatório.']);
        }
        $fotoPath = $request->filled('foto_base64')
            ? $this->salvarBase64((string) $request->input('foto_base64'), $unidadeId)
            : null;
        $agora = now();

        try {
            $id = DB::table('dlv_adicionais')->insertGetId([
                'unidade_id' => $unidadeId,
                'nome' => $data['nome'],
                'tipo' => $data['tipo'] ?? 'acrescentar',
                'preco' => $this->precoNormalizado($data),
                'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
                'ordem' => (int) ($data['ordem'] ?? 0),
                'foto_path' => $fotoPath,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        } catch (\Throwable $exception) {
            $this->removerArquivoProprio($fotoPath, $unidadeId);
            throw $exception;
        }

        return response()->json($this->formatar(DB::table('dlv_adicionais')->where('id', $id)->first()), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryAdicionais');
        $row = DB::table('dlv_adicionais')->where('id', $id)->first();
        abort_unless($row, 404, 'Adicional não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);

        return response()->json($this->formatar($row));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryAdicionais');
        $row = DB::table('dlv_adicionais')->where('id', $id)->first();
        abort_unless($row, 404, 'Adicional não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);
        $data = $this->validar($request, false);
        if (array_key_exists('nome', $data)) {
            $data['nome'] = trim($data['nome']);
            if ($data['nome'] === '') {
                throw ValidationException::withMessages(['nome' => 'O nome do adicional é obrigatório.']);
            }
        }

        $unidadeId = (int) $row->unidade_id;
        $novoFotoPath = $row->foto_path;
        $fotoSubstituida = false;
        if ($request->filled('foto_base64')) {
            $novoFotoPath = $this->salvarBase64((string) $request->input('foto_base64'), $unidadeId);
            $fotoSubstituida = true;
        } elseif ((bool) ($data['remover_foto'] ?? false)) {
            $novoFotoPath = null;
            $fotoSubstituida = true;
        }

        $merged = array_merge((array) $row, $data);
        try {
            DB::table('dlv_adicionais')->where('id', $id)->update([
                'nome' => $data['nome'] ?? $row->nome,
                'tipo' => $data['tipo'] ?? $row->tipo,
                'preco' => $this->precoNormalizado($merged),
                'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : (bool) $row->ativo,
                'ordem' => array_key_exists('ordem', $data) ? (int) $data['ordem'] : $row->ordem,
                'foto_path' => $novoFotoPath,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            if ($novoFotoPath !== $row->foto_path) {
                $this->removerArquivoProprio($novoFotoPath, $unidadeId);
            }
            throw $exception;
        }

        if ($fotoSubstituida && $row->foto_path !== $novoFotoPath) {
            $this->removerArquivoProprio($row->foto_path, $unidadeId);
        }

        return response()->json($this->formatar(DB::table('dlv_adicionais')->where('id', $id)->first()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryAdicionais');
        $row = DB::table('dlv_adicionais')->where('id', $id)->first();
        abort_unless($row, 404, 'Adicional não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);

        DB::transaction(function () use ($id) {
            DB::table('dlv_produto_adicional')->where('adicional_id', $id)->delete();
            DB::table('dlv_adicionais')->where('id', $id)->delete();
        });
        $this->removerArquivoProprio($row->foto_path, (int) $row->unidade_id);

        return response()->json(['ok' => true]);
    }

    private function validar(Request $request, bool $criar = true): array
    {
        $rules = [
            'nome' => ($criar ? 'required' : 'sometimes').'|string|max:120',
            'tipo' => ($criar ? 'required' : 'sometimes').'|in:acrescentar,retirar',
            'preco' => ($criar ? 'required' : 'sometimes').'|numeric|min:0',
            'ativo' => 'nullable|boolean',
            'ordem' => 'nullable|integer|min:0|max:9999',
            'foto_base64' => 'nullable|string',
            'remover_foto' => 'nullable|boolean',
            'foto_path' => 'prohibited',
            'unidade_id' => 'nullable|integer',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function precoNormalizado(array $data): float
    {
        if (($data['tipo'] ?? 'acrescentar') === 'retirar') {
            return 0.0;
        }

        return round((float) ($data['preco'] ?? 0), 2);
    }

    private function formatar(object $row): array
    {
        return array_merge((array) $row, [
            'product_count' => (int) ($row->product_count ?? 0),
            'foto_url' => $this->fotoUrl($row->foto_path ?? null),
        ]);
    }

    private function salvarBase64(string $dataUrl, int $unidadeId): string
    {
        if (! preg_match('#^data:(image/(?:jpeg|png|webp|gif));base64,([a-z0-9+/=\r\n]+)$#i', trim($dataUrl), $matches)) {
            throw ValidationException::withMessages([
                'foto_base64' => 'Imagem inválida. Use uma foto JPG, PNG, WebP ou GIF em base64.',
            ]);
        }

        if (strlen($matches[2]) > (int) ceil(self::FOTO_MAX_BYTES * 4 / 3) + 8) {
            throw ValidationException::withMessages(['foto_base64' => 'A foto não pode exceder 2 MB.']);
        }

        $binario = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);
        if ($binario === false || $binario === '') {
            throw ValidationException::withMessages(['foto_base64' => 'Não foi possível decodificar a foto.']);
        }
        if (strlen($binario) > self::FOTO_MAX_BYTES) {
            throw ValidationException::withMessages(['foto_base64' => 'A foto não pode exceder 2 MB.']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binario) ?: '';
        $declarado = strtolower($matches[1]);
        if (! isset(self::ALLOWED_IMAGE_MIMES[$mime]) || $mime !== $declarado) {
            throw ValidationException::withMessages(['foto_base64' => 'O conteúdo da foto não corresponde a um formato permitido.']);
        }

        $diretorio = 'uploads/delivery/adicionais/'.$unidadeId;
        $diretorioAbsoluto = public_path($diretorio);
        if (! is_dir($diretorioAbsoluto) && ! mkdir($diretorioAbsoluto, 0755, true) && ! is_dir($diretorioAbsoluto)) {
            throw ValidationException::withMessages(['foto_base64' => 'Não foi possível criar o diretório da foto.']);
        }

        $path = $diretorio.'/'.Str::lower(Str::random(24)).'.'.self::ALLOWED_IMAGE_MIMES[$mime];
        if (file_put_contents(public_path($path), $binario, LOCK_EX) === false) {
            throw ValidationException::withMessages(['foto_base64' => 'Não foi possível salvar a foto.']);
        }

        return $path;
    }

    private function pathSeguro(?string $path, int $unidadeId): ?string
    {
        $normalizado = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
        $prefixo = 'uploads/delivery/adicionais/'.$unidadeId.'/';

        if ($normalizado === '' || str_contains($normalizado, '..') || ! str_starts_with($normalizado, $prefixo)) {
            return null;
        }
        if (str_contains(substr($normalizado, strlen($prefixo)), '/')) {
            return null;
        }

        return $normalizado;
    }

    private function removerArquivoProprio(?string $path, int $unidadeId): void
    {
        $seguro = $this->pathSeguro($path, $unidadeId);
        if ($seguro === null) {
            return;
        }

        $raiz = realpath(public_path('uploads/delivery/adicionais/'.$unidadeId));
        $arquivo = realpath(public_path($seguro));
        if ($raiz && $arquivo && str_starts_with($arquivo, $raiz.DIRECTORY_SEPARATOR) && is_file($arquivo)) {
            @unlink($arquivo);
        }
    }

    private function fotoUrl(?string $path): ?string
    {
        $normalizado = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
        if ($normalizado === '' || str_contains($normalizado, '..') || ! str_starts_with($normalizado, 'uploads/delivery/adicionais/')) {
            return null;
        }

        return '/'.$normalizado;
    }
}
