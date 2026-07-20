<?php

namespace App\Http\Controllers\Delivery;

use App\Services\Delivery\DeliveryEntregadorOfertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DeliveryPublicMotoboyController extends DeliveryBaseController
{
    public function __construct(
        \App\Services\Delivery\DeliveryAccessService $access,
        private readonly DeliveryEntregadorOfertaService $ofertas,
    ) {
        parent::__construct($access);
    }

    public function app(string $slug, string $acessoToken): View
    {
        $ctx = $this->resolverEntregador($slug, $acessoToken);
        abort_unless($ctx, 404);

        $appNome = $this->nomeAppInstalado($ctx['config']);
        $desbloqueado = $this->estaDesbloqueado($ctx['entregador']);

        return view('delivery.public.motoboy-app', [
            'slug' => $slug,
            'config' => $ctx['config'],
            'entregador' => $ctx['entregador'],
            'acessoToken' => $acessoToken,
            'appNome' => $appNome['name'],
            'appNomeCurto' => $appNome['short_name'],
            'desbloqueado' => $desbloqueado,
            'ofertasUrl' => route('delivery.public.motoboy.ofertas', [
                'slug' => $slug,
                'acessoToken' => $acessoToken,
            ]),
            'sessaoUrl' => route('delivery.public.motoboy.sessao', [
                'slug' => $slug,
                'acessoToken' => $acessoToken,
            ]),
            'desbloquearUrl' => route('delivery.public.motoboy.desbloquear', [
                'slug' => $slug,
                'acessoToken' => $acessoToken,
            ]),
            'bloquearUrl' => route('delivery.public.motoboy.bloquear', [
                'slug' => $slug,
                'acessoToken' => $acessoToken,
            ]),
            'recebendoUrl' => route('delivery.public.motoboy.recebendo', [
                'slug' => $slug,
                'acessoToken' => $acessoToken,
            ]),
            'recebendoEntregas' => $this->estaRecebendo($ctx['entregador']),
            'manifestUrl' => route('delivery.public.motoboy.manifest', [
                'slug' => $slug,
                'acessoToken' => $acessoToken,
            ]),
            'aceitarUrlTpl' => route('delivery.public.motoboy.aceitar', [
                'slug' => $slug,
                'acessoToken' => $acessoToken,
                'pedidoId' => 999999,
            ]),
            'recusarUrlTpl' => route('delivery.public.motoboy.recusar', [
                'slug' => $slug,
                'acessoToken' => $acessoToken,
                'pedidoId' => 999999,
            ]),
        ]);
    }

    public function sessao(string $slug, string $acessoToken): JsonResponse
    {
        $ctx = $this->resolverEntregador($slug, $acessoToken);
        abort_unless($ctx, 404);

        return response()->json([
            'ok' => true,
            'desbloqueado' => $this->estaDesbloqueado($ctx['entregador']),
            'tem_pin' => $this->temPin($ctx['entregador']),
            'pin_vinculado' => $this->temInstallVinculado($ctx['entregador']),
            'recebendo_entregas' => $this->estaRecebendo($ctx['entregador']),
            'entregador' => [
                'id' => (int) $ctx['entregador']->id,
                'nome' => (string) $ctx['entregador']->nome,
            ],
        ]);
    }

    public function desbloquear(Request $request, string $slug, string $acessoToken): JsonResponse
    {
        $ctx = $this->resolverEntregador($slug, $acessoToken);
        abort_unless($ctx, 404);

        if (! $this->temPin($ctx['entregador'])) {
            throw ValidationException::withMessages([
                'pin' => 'Não há PIN ativo. Peça à loja para gerar um PIN.',
            ]);
        }

        $pin = preg_replace('/\D+/', '', (string) $request->input('pin', '')) ?? '';
        if (strlen($pin) !== 6) {
            throw ValidationException::withMessages(['pin' => 'Informe o PIN de 6 dígitos.']);
        }

        $esperado = (string) ($ctx['entregador']->acesso_pin ?? '');
        if (! hash_equals($esperado, $pin)) {
            throw ValidationException::withMessages(['pin' => 'PIN incorreto.']);
        }

        $installId = trim((string) $request->input('install_id', ''));
        if (! preg_match('/^[a-zA-Z0-9_-]{16,64}$/', $installId)) {
            throw ValidationException::withMessages([
                'pin' => 'Não foi possível validar esta instalação. Abra o app pelo link da loja.',
            ]);
        }

        $vinculado = trim((string) ($ctx['entregador']->acesso_install_id ?? ''));
        if ($vinculado !== '' && ! hash_equals($vinculado, $installId)) {
            throw ValidationException::withMessages([
                'pin' => 'Este PIN já está vinculado a outra instalação. Se desinstalou o app, peça um PIN novo à loja.',
            ]);
        }

        $update = ['updated_at' => now()];
        if ($vinculado === '' && Schema::hasColumn('dlv_entregadores', 'acesso_install_id')) {
            $update['acesso_install_id'] = $installId;
        }
        if (count($update) > 1) {
            DB::table('dlv_entregadores')->where('id', $ctx['entregador']->id)->update($update);
        }

        $request->session()->put($this->sessionKey($ctx['entregador']), [
            'entregador_id' => (int) $ctx['entregador']->id,
            'pin_hash' => hash('sha256', $pin),
            'install_id' => $installId,
            'at' => now()->toIso8601String(),
        ]);

        return response()->json([
            'ok' => true,
            'desbloqueado' => true,
            'recebendo_entregas' => $this->estaRecebendo(
                DB::table('dlv_entregadores')->where('id', $ctx['entregador']->id)->first() ?: $ctx['entregador']
            ),
            'mensagem' => 'Acesso liberado. Pode sair e voltar com o mesmo PIN neste aparelho.',
        ]);
    }

    public function bloquear(Request $request, string $slug, string $acessoToken): JsonResponse
    {
        $ctx = $this->resolverEntregador($slug, $acessoToken);
        abort_unless($ctx, 404);
        $request->session()->forget($this->sessionKey($ctx['entregador']));

        return response()->json(['ok' => true, 'desbloqueado' => false]);
    }

    public function recebendo(Request $request, string $slug, string $acessoToken): JsonResponse
    {
        $ctx = $this->exigirDesbloqueado($slug, $acessoToken);
        abort_unless(Schema::hasColumn('dlv_entregadores', 'recebendo_entregas'), 422, 'Recurso indisponível.');

        $recebendo = filter_var($request->input('recebendo', true), FILTER_VALIDATE_BOOLEAN);
        DB::table('dlv_entregadores')->where('id', $ctx['entregador']->id)->update([
            'recebendo_entregas' => $recebendo,
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'recebendo_entregas' => $recebendo,
            'mensagem' => $recebendo
                ? 'Você voltou a receber entregas.'
                : 'Entregas pausadas. Ative de novo quando quiser trabalhar.',
        ]);
    }

    public function ofertas(string $slug, string $acessoToken): JsonResponse
    {
        $ctx = $this->exigirDesbloqueado($slug, $acessoToken);
        $recebendo = $this->estaRecebendo($ctx['entregador']);
        $items = $recebendo
            ? $this->ofertas->listarOfertasAbertas($ctx['config'], $ctx['entregador'])
            : [];

        return response()->json([
            'entregador' => [
                'id' => (int) $ctx['entregador']->id,
                'nome' => (string) $ctx['entregador']->nome,
            ],
            'loja' => (string) ($ctx['config']->nome_loja ?? 'Loja'),
            'recebendo_entregas' => $recebendo,
            'count' => count($items),
            'items' => $items,
            'agora' => now()->toIso8601String(),
        ]);
    }

    public function aceitar(Request $request, string $slug, string $acessoToken, int $pedidoId): JsonResponse
    {
        $ctx = $this->exigirDesbloqueado($slug, $acessoToken);
        if (! $this->estaRecebendo($ctx['entregador'])) {
            throw ValidationException::withMessages([
                'oferta' => 'Você pausou as entregas. Ative novamente para aceitar.',
            ]);
        }
        $result = $this->ofertas->aceitarOferta($ctx['config'], $ctx['entregador'], $pedidoId);

        return response()->json($result);
    }

    public function recusar(Request $request, string $slug, string $acessoToken, int $pedidoId): JsonResponse
    {
        $ctx = $this->exigirDesbloqueado($slug, $acessoToken);
        $result = $this->ofertas->recusarOferta($ctx['config'], $ctx['entregador'], $pedidoId);

        return response()->json($result);
    }

    public function manifest(string $slug, string $acessoToken): JsonResponse
    {
        $ctx = $this->resolverEntregador($slug, $acessoToken);
        abort_unless($ctx, 404);

        $appNome = $this->nomeAppInstalado($ctx['config']);
        $start = route('delivery.public.motoboy.app', [
            'slug' => $slug,
            'acessoToken' => $acessoToken,
        ], absolute: true);

        return response()->json([
            'name' => $appNome['name'],
            'short_name' => $appNome['short_name'],
            'description' => 'Receba e aceite entregas — '.$appNome['name'],
            'start_url' => $start,
            'scope' => parse_url($start, PHP_URL_PATH) ?: '/',
            'display' => 'standalone',
            'background_color' => '#14532d',
            'theme_color' => '#166534',
            'lang' => 'pt-BR',
            'icons' => [
                [
                    'src' => asset('assets/delivery/motoboy-icon-192.png').'?v=20260720-esp',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset('assets/delivery/motoboy-icon-512.png').'?v=20260720-esp',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset('assets/delivery/motoboy-icon-512.png').'?v=20260720-esp',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    /** @return array{config: object, entregador: object} */
    private function exigirDesbloqueado(string $slug, string $acessoToken): array
    {
        $ctx = $this->resolverEntregador($slug, $acessoToken);
        abort_unless($ctx, 404);

        if (! $this->estaDesbloqueado($ctx['entregador'])) {
            abort(response()->json([
                'ok' => false,
                'desbloqueado' => false,
                'message' => 'Digite o PIN para continuar.',
            ], 401));
        }

        return $ctx;
    }

    private function temPin(object $entregador): bool
    {
        if (! Schema::hasColumn('dlv_entregadores', 'acesso_pin')) {
            return false;
        }

        $pin = preg_replace('/\D+/', '', (string) ($entregador->acesso_pin ?? '')) ?? '';

        return strlen($pin) === 6;
    }

    private function temInstallVinculado(object $entregador): bool
    {
        return Schema::hasColumn('dlv_entregadores', 'acesso_install_id')
            && trim((string) ($entregador->acesso_install_id ?? '')) !== '';
    }

    private function estaRecebendo(object $entregador): bool
    {
        if (! Schema::hasColumn('dlv_entregadores', 'recebendo_entregas')) {
            return true;
        }

        return (bool) ($entregador->recebendo_entregas ?? true);
    }

    private function estaDesbloqueado(object $entregador): bool
    {
        if (! $this->temPin($entregador)) {
            return false;
        }

        $sess = session($this->sessionKey($entregador));
        if (! is_array($sess)) {
            return false;
        }
        if ((int) ($sess['entregador_id'] ?? 0) !== (int) $entregador->id) {
            return false;
        }

        $pin = (string) ($entregador->acesso_pin ?? '');
        $esperado = hash('sha256', $pin);
        if (! hash_equals($esperado, (string) ($sess['pin_hash'] ?? ''))) {
            return false;
        }

        // Se o PIN foi regenerado e a instalação limpa, a sessão antiga cai.
        $vinculado = trim((string) ($entregador->acesso_install_id ?? ''));
        $sessInstall = trim((string) ($sess['install_id'] ?? ''));
        if ($vinculado !== '' && $sessInstall !== '' && ! hash_equals($vinculado, $sessInstall)) {
            return false;
        }

        return true;
    }

    private function sessionKey(object $entregador): string
    {
        return 'motoboy_pin_'.$entregador->id;
    }

    /** @return array{name: string, short_name: string, unidade: string} */
    private function nomeAppInstalado(object $config): array
    {
        $unidade = '';
        $unidadeId = (int) ($config->unidade_id ?? 0);
        if ($unidadeId > 0 && Schema::hasTable('unidades')) {
            $unidade = trim((string) DB::table('unidades')->where('id', $unidadeId)->value('nome'));
        }
        if ($unidade === '') {
            $unidade = trim((string) ($config->nome_loja ?? ''));
        }
        if ($unidade === '') {
            $unidade = 'Unidade';
        }

        $name = 'Entrega Sabor Paraense · '.$unidade;
        $shortBase = 'Entrega Sabor Paraense';
        $shortName = $shortBase.' · '.$unidade;
        if (mb_strlen($shortName) > 30) {
            $uniShort = mb_strlen($unidade) <= 12 ? $unidade : (mb_substr($unidade, 0, 10).'…');
            $shortName = 'Entrega · '.$uniShort;
        }

        return [
            'name' => $name,
            'short_name' => $shortName,
            'unidade' => $unidade,
        ];
    }

    /** @return array{config: object, entregador: object}|null */
    private function resolverEntregador(string $slug, string $acessoToken): ?array
    {
        $token = trim($acessoToken);
        if ($token === '' || strlen($token) < 20) {
            return null;
        }

        $config = DB::table('dlv_loja_config')->where('slug', $slug)->where('ativo', 1)->first();
        if (! $config) {
            return null;
        }

        if (! Schema::hasColumn('dlv_entregadores', 'acesso_token')) {
            return null;
        }

        $entregador = DB::table('dlv_entregadores')
            ->where('unidade_id', $config->unidade_id)
            ->where('acesso_token', $token)
            ->where('ativo', 1)
            ->first();

        if (! $entregador) {
            return null;
        }

        return compact('config', 'entregador');
    }
}
