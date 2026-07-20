<?php

namespace App\Http\Controllers\Delivery;

use App\Support\Delivery\DeliveryMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeliveryEntregadorController extends DeliveryBaseController
{
    private const FOTO_MAX_BYTES = 2 * 1024 * 1024;

    private const IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function index(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryEntregadores');
        $query = DB::table('dlv_entregadores');
        $this->access->aplicarEscopo($query, $usuario, $request);

        if ($request->has('ativo')) {
            $query->where('ativo', filter_var($request->query('ativo'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }

        $items = $query->orderBy('ordem')->orderBy('nome')->get()
            ->map(fn ($row) => $this->serializar($row));

        return response()->json(['items' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryEntregadores');
        $data = $this->validar($request);
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $data);
        $foto = $this->salvarFotoBase64($data['foto_base64'] ?? null, $unidadeId);
        $payload = [
            'unidade_id' => $unidadeId,
            'nome' => $data['nome'],
            'whatsapp' => $data['whatsapp'],
            'telefone' => $data['telefone'] ?? null,
            'moto_placa' => $data['moto_placa'] ?? null,
            'moto_modelo' => $data['moto_modelo'] ?? null,
            'foto_path' => $foto,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
            'ordem' => (int) ($data['ordem'] ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('dlv_entregadores', 'moto_cor')) {
            $payload['moto_cor'] = $data['moto_cor'] ?? null;
        }
        if (Schema::hasColumn('dlv_entregadores', 'acesso_token')) {
            $payload['acesso_token'] = Str::lower(Str::random(48));
        }
        if (Schema::hasColumn('dlv_entregadores', 'acesso_pin')) {
            $payload['acesso_pin'] = $this->gerarPinSeisDigitos();
            if (Schema::hasColumn('dlv_entregadores', 'acesso_pin_usado_em')) {
                $payload['acesso_pin_usado_em'] = null;
            }
            if (Schema::hasColumn('dlv_entregadores', 'acesso_install_id')) {
                $payload['acesso_install_id'] = null;
            }
        }
        if (Schema::hasColumn('dlv_entregadores', 'recebendo_entregas')) {
            $payload['recebendo_entregas'] = true;
        }

        try {
            $id = DB::table('dlv_entregadores')->insertGetId($payload);
        } catch (Throwable $e) {
            $this->removerFoto($foto, $unidadeId);
            throw $e;
        }

        return response()->json($this->serializar(
            DB::table('dlv_entregadores')->where('id', $id)->first()
        ), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryEntregadores');
        $row = DB::table('dlv_entregadores')->where('id', $id)->first();
        abort_unless($row, 404, 'Entregador não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);

        return response()->json($this->serializar($row));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryEntregadores');
        $row = DB::table('dlv_entregadores')->where('id', $id)->first();
        abort_unless($row, 404, 'Entregador não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);
        $data = $this->validar($request, false);

        $novaFoto = null;
        $fotoPath = $row->foto_path ?? null;
        if (! empty($data['foto_base64'])) {
            $novaFoto = $this->salvarFotoBase64($data['foto_base64'], (int) $row->unidade_id);
            $fotoPath = $novaFoto;
        } elseif (($data['remover_foto'] ?? false) === true) {
            $fotoPath = null;
        }

        $payload = [
            'nome' => $data['nome'] ?? $row->nome,
            'whatsapp' => array_key_exists('whatsapp', $data) ? $data['whatsapp'] : $row->whatsapp,
            'telefone' => array_key_exists('telefone', $data) ? $data['telefone'] : $row->telefone,
            'moto_placa' => array_key_exists('moto_placa', $data) ? $data['moto_placa'] : $row->moto_placa,
            'moto_modelo' => array_key_exists('moto_modelo', $data) ? $data['moto_modelo'] : $row->moto_modelo,
            'foto_path' => $fotoPath,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : (bool) $row->ativo,
            'ordem' => array_key_exists('ordem', $data) ? (int) $data['ordem'] : $row->ordem,
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('dlv_entregadores', 'moto_cor')) {
            $payload['moto_cor'] = array_key_exists('moto_cor', $data)
                ? $data['moto_cor']
                : ($row->moto_cor ?? null);
        }

        try {
            DB::table('dlv_entregadores')->where('id', $id)->update($payload);
        } catch (Throwable $e) {
            $this->removerFoto($novaFoto, (int) $row->unidade_id);
            throw $e;
        }
        if ($fotoPath !== ($row->foto_path ?? null)) {
            $this->removerFoto($row->foto_path ?? null, (int) $row->unidade_id);
        }

        return response()->json($this->serializar(
            DB::table('dlv_entregadores')->where('id', $id)->first()
        ));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryEntregadores');
        $row = DB::table('dlv_entregadores')->where('id', $id)->first();
        abort_unless($row, 404, 'Entregador não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);
        DB::table('dlv_entregadores')->where('id', $id)->delete();
        $this->removerFoto($row->foto_path ?? null, (int) $row->unidade_id);

        return response()->json(['ok' => true]);
    }

    public function gerarPin(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryEntregadores');
        $row = DB::table('dlv_entregadores')->where('id', $id)->first();
        abort_unless($row, 404, 'Entregador não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);
        abort_unless(Schema::hasColumn('dlv_entregadores', 'acesso_pin'), 422, 'PIN não disponível neste ambiente.');

        $pin = $this->gerarPinSeisDigitos();
        $payload = [
            'acesso_pin' => $pin,
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('dlv_entregadores', 'acesso_pin_usado_em')) {
            $payload['acesso_pin_usado_em'] = null;
        }
        if (Schema::hasColumn('dlv_entregadores', 'acesso_install_id')) {
            $payload['acesso_install_id'] = null;
        }

        DB::table('dlv_entregadores')->where('id', $id)->update($payload);

        return response()->json($this->serializar(
            DB::table('dlv_entregadores')->where('id', $id)->first()
        ));
    }

    private function gerarPinSeisDigitos(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function validar(Request $request, bool $criar = true): array
    {
        $rules = [
            'nome' => ($criar ? 'required' : 'sometimes').'|string|max:255',
            'whatsapp' => ($criar ? 'required' : 'sometimes').'|string|max:32',
            'telefone' => 'nullable|string|max:32',
            'moto_placa' => 'nullable|string|max:16',
            'moto_modelo' => 'nullable|string|max:120',
            'moto_cor' => 'nullable|string|max:64',
            'foto_base64' => 'nullable|string',
            'remover_foto' => 'nullable|boolean',
            'ativo' => 'nullable|boolean',
            'ordem' => 'nullable|integer|min:0|max:99999',
            'unidade_id' => 'nullable|integer',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function serializar(object $row): array
    {
        $data = array_merge((array) $row, [
            'foto_url' => DeliveryMediaUrl::fromPublicPath($row->foto_path ?? null),
        ]);

        if (Schema::hasColumn('dlv_entregadores', 'acesso_pin')) {
            $pin = preg_replace('/\D+/', '', (string) ($row->acesso_pin ?? '')) ?? '';
            $pinOk = strlen($pin) === 6;
            $vinculado = Schema::hasColumn('dlv_entregadores', 'acesso_install_id')
                && trim((string) ($row->acesso_install_id ?? '')) !== '';
            $data['acesso_pin'] = $pinOk ? $pin : '';
            $data['tem_pin'] = $pinOk;
            $data['pin_vinculado'] = $pinOk && $vinculado;
            // PIN pode ser reenviado enquanto existir; novo PIN só se desinstalar.
            $data['pin_disponivel'] = $pinOk;
            $data['pin_usado'] = false;
        }
        if (Schema::hasColumn('dlv_entregadores', 'recebendo_entregas')) {
            $data['recebendo_entregas'] = (bool) ($row->recebendo_entregas ?? true);
        }

        if (Schema::hasColumn('dlv_entregadores', 'acesso_token')) {
            $token = trim((string) ($row->acesso_token ?? ''));
            if ($token === '') {
                $token = Str::lower(Str::random(48));
                DB::table('dlv_entregadores')->where('id', $row->id)->update([
                    'acesso_token' => $token,
                    'updated_at' => now(),
                ]);
                $data['acesso_token'] = $token;
            }
            $config = DB::table('dlv_loja_config')->where('unidade_id', $row->unidade_id)->first();
            $appUrl = null;
            if ($config && trim((string) ($config->slug ?? '')) !== '') {
                $appUrl = route('delivery.public.motoboy.app', [
                    'slug' => $config->slug,
                    'acessoToken' => $token,
                ], absolute: true);
                $data['url_app'] = $appUrl;
            }

            $nomeLoja = trim((string) ($config->nome_loja ?? 'nossa loja')) ?: 'nossa loja';
            $nomeMotoboy = trim((string) ($row->nome ?? 'motoboy')) ?: 'motoboy';
            $pin = preg_replace('/\D+/', '', (string) ($row->acesso_pin ?? '')) ?? '';
            $pinOk = strlen($pin) === 6;
            $whatsappCadastro = trim((string) ($row->whatsapp ?? '')) !== ''
                ? $row->whatsapp
                : ($row->telefone ?? null);
            $textoApp = "Olá, {$nomeMotoboy}! Aqui está o app de entregas da {$nomeLoja}.\n\n"
                ."1) Abra o link no celular\n"
                ."2) Toque em Instalar / Adicionar à tela inicial\n"
                ."3) Digite o PIN\n\n"
                .($appUrl ?: '');
            if ($pinOk) {
                $textoApp .= "\n\nPIN: {$pin}\n"
                    ."Pode sair e voltar com o mesmo PIN.\n"
                    ."Se desinstalar o app, peça um PIN novo à loja.\n"
                    .'(Envie só para o WhatsApp cadastrado — não encaminhe.)';
            } else {
                $textoApp .= "\n\nPeça à loja um PIN para entrar no app.";
            }
            $data['url_app_whatsapp'] = ($appUrl && $pinOk)
                ? \App\Support\Delivery\DeliveryWhatsAppHelper::urlComTexto($whatsappCadastro, $textoApp)
                : null;
            $data['whatsapp_cadastro'] = $whatsappCadastro;
        }

        return $data;
    }

    private function salvarFotoBase64(?string $dataUrl, int $unidadeId): ?string
    {
        if ($dataUrl === null || trim($dataUrl) === '') {
            return null;
        }
        if (! preg_match('#^data:(image/(?:jpeg|png|webp|gif));base64,([a-zA-Z0-9+/=\r\n]+)$#', trim($dataUrl), $match)) {
            throw ValidationException::withMessages([
                'foto_base64' => 'Imagem inválida. Use JPG, PNG, WebP ou GIF em base64.',
            ]);
        }

        $binario = base64_decode(preg_replace('/\s+/', '', $match[2]) ?? '', true);
        if ($binario === false || $binario === '') {
            throw ValidationException::withMessages(['foto_base64' => 'Não foi possível decodificar a imagem.']);
        }
        if (strlen($binario) > self::FOTO_MAX_BYTES) {
            throw ValidationException::withMessages(['foto_base64' => 'A foto do entregador não pode exceder 2MB.']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binario) ?: '';
        if (! isset(self::IMAGE_EXTENSIONS[$mime])) {
            throw ValidationException::withMessages(['foto_base64' => 'O conteúdo enviado não é uma imagem permitida.']);
        }

        $diretorio = 'uploads/delivery/entregadores/'.$unidadeId;
        $diretorioAbsoluto = public_path($diretorio);
        if (! is_dir($diretorioAbsoluto) && ! mkdir($diretorioAbsoluto, 0755, true) && ! is_dir($diretorioAbsoluto)) {
            throw ValidationException::withMessages(['foto_base64' => 'Não foi possível criar o diretório da foto.']);
        }

        $caminho = $diretorio.'/'.Str::lower(Str::random(32)).'.'.self::IMAGE_EXTENSIONS[$mime];
        if (file_put_contents(public_path($caminho), $binario, LOCK_EX) === false) {
            throw ValidationException::withMessages(['foto_base64' => 'Não foi possível salvar a foto.']);
        }

        return $caminho;
    }

    private function removerFoto(?string $caminho, int $unidadeId): void
    {
        if (empty($caminho)) {
            return;
        }

        $relativo = str_replace('\\', '/', ltrim($caminho, '/'));
        $prefixo = 'uploads/delivery/entregadores/'.$unidadeId.'/';
        if (! str_starts_with($relativo, $prefixo) || str_contains($relativo, '..')) {
            return;
        }

        $arquivo = realpath(public_path($relativo));
        $raiz = realpath(public_path(rtrim($prefixo, '/')));
        if ($arquivo && $raiz && str_starts_with($arquivo, $raiz.DIRECTORY_SEPARATOR) && is_file($arquivo)) {
            @unlink($arquivo);
        }
    }
}
