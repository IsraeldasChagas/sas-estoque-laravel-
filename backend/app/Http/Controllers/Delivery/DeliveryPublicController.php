<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Services\Delivery\DeliveryFreteService;
use App\Services\Delivery\DeliveryPedidoService;
use App\Http\Controllers\Delivery\DeliveryFidelidadePublicController;
use App\Services\Fidelidade\DeliveryPedidoFidelidadeService;
use App\Services\Fidelidade\FidelidadeLgpdService;
use App\Services\Fidelidade\FidelidadePublicConsultaService;
use App\Services\Payments\DeliveryCardGatewayService;
use App\Services\Payments\DeliveryPixGatewayService;
use App\Support\Delivery\DeliveryLojaCheckoutHelper;
use App\Support\Delivery\DeliveryPedidoPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DeliveryPublicController extends Controller
{
    public function __construct(
        private readonly DeliveryPedidoService $pedidos,
        private readonly DeliveryFreteService $frete,
        private readonly DeliveryPedidoFidelidadeService $pedidoFidelidade,
        private readonly FidelidadePublicConsultaService $fidelidadeConsulta,
        private readonly DeliveryPixGatewayService $pixGateway,
        private readonly DeliveryCardGatewayService $cardGateway,
    ) {}

    public function loja(string $slug): View
    {
        $config = $this->config($slug);
        $unidadeId = (int) $config->unidade_id;
        $categorias = DB::table('dlv_categorias as c')
            ->where('c.unidade_id', $unidadeId)->where('c.ativo', 1)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('dlv_produtos as p')
                ->whereColumn('p.categoria_id', 'c.id')->where('p.unidade_id', $unidadeId)
                ->where('p.ativo', 1)->where('p.visivel_loja', 1))
            ->orderBy('c.ordem')->orderBy('c.nome')->get();
        $produtos = DB::table('dlv_produtos as p')
            ->leftJoin('dlv_categorias as c', 'c.id', '=', 'p.categoria_id')
            ->where('p.unidade_id', $unidadeId)->where('p.ativo', 1)->where('p.visivel_loja', 1)
            ->where(fn ($q) => $q->whereNull('p.categoria_id')->orWhere('c.ativo', 1))
            ->orderBy('p.ordem')->orderBy('p.nome')
            ->get(['p.*', 'c.nome as categoria_nome'])
            ->map(fn ($p) => $this->produtoPublico($p, $unidadeId));
        $adicionais = DB::table('dlv_adicionais')->where('unidade_id', $unidadeId)
            ->where('ativo', 1)->where('tipo', 'acrescentar')->orderBy('ordem')->orderBy('nome')->get()
            ->map(fn ($a) => (object) array_merge((array) $a, ['foto_url' => $this->imagem($a->foto_path ?? null, $unidadeId, 'adicionais')]));
        $banners = $this->bannersPublicos($config);
        $passoAtual = 'loja';
        $fidelidadeAtiva = $this->fidelidadeAtiva($config);
        $footerFixed = true;

        return view('delivery.public.loja', compact(
            'config', 'slug', 'categorias', 'produtos', 'adicionais', 'banners',
            'passoAtual', 'fidelidadeAtiva', 'footerFixed'
        ));
    }

    public function produto(string $slug, int $id): View
    {
        $config = $this->config($slug);
        $produto = DB::table('dlv_produtos as p')->leftJoin('dlv_categorias as c', 'c.id', '=', 'p.categoria_id')
            ->where('p.id', $id)->where('p.unidade_id', $config->unidade_id)
            ->where('p.ativo', 1)->where('p.visivel_loja', 1)
            ->where(fn ($q) => $q->whereNull('p.categoria_id')->orWhere('c.ativo', 1))
            ->select('p.*', 'c.nome as categoria_nome')->first();
        abort_unless($produto, 404);
        $produto = $this->produtoPublico($produto, (int) $config->unidade_id);
        $ingredientes = DB::table('dlv_produto_ingredientes')->where('produto_id', $id)
            ->orderBy('ordem')->orderBy('id')->get();
        $adicionais = DB::table('dlv_produto_adicional as pa')
            ->join('dlv_adicionais as a', 'a.id', '=', 'pa.adicional_id')
            ->where('pa.produto_id', $id)->where('a.unidade_id', $config->unidade_id)
            ->where('a.ativo', 1)->where('a.tipo', 'acrescentar')
            ->orderBy('a.ordem')->orderBy('a.nome')->get(['a.*']);
        $passoAtual = 'loja';
        $fidelidadeAtiva = $this->fidelidadeAtiva($config);
        $footerFixed = false;

        return view('delivery.public.produto', compact(
            'config', 'slug', 'produto', 'ingredientes', 'adicionais',
            'passoAtual', 'fidelidadeAtiva', 'footerFixed'
        ));
    }

    public function carrinho(string $slug): View
    {
        $config = $this->config($slug);
        $prefs = $this->entregaPrefs($slug);
        $passoAtual = 'carrinho';
        $fidelidadeAtiva = $this->fidelidadeAtiva($config);
        $footerFixed = false;
        $permiteBalcao = (bool) $config->permite_retirada;
        $freteModo = $this->frete->modoEfetivo($config);
        $freteResumoUrl = route('delivery.public.freight.summary', [$slug]);
        $prefsUrl = route('delivery.public.cart.prefs', [$slug]);

        return view('delivery.public.carrinho', compact(
            'config', 'slug', 'prefs', 'passoAtual', 'fidelidadeAtiva', 'footerFixed',
            'permiteBalcao', 'freteModo', 'freteResumoUrl', 'prefsUrl'
        ));
    }

    public function carrinhoEntregaPrefs(Request $request, string $slug): JsonResponse
    {
        $config = $this->config($slug);
        $data = $request->validate([
            'modo' => 'required|in:entrega,balcao,retirada',
            'cep' => 'nullable|string|max:16',
            'subtotal' => 'nullable|numeric|min:0|max:99999999.99',
        ]);
        $modo = DeliveryLojaCheckoutHelper::fulfillmentFromTipoEntrega($data['modo']);
        $cep = preg_replace('/\D+/', '', (string) ($data['cep'] ?? ''));
        session()->put($this->entregaPrefsKey($slug), ['modo' => $modo, 'cep' => $cep]);
        $subtotal = (float) ($data['subtotal'] ?? 0);
        $resumo = $this->resumoFreteCheckout($config, $modo, $modo === 'entrega' && strlen($cep) === 8 ? $cep : null, $subtotal);

        return response()->json([
            'ok' => true,
            'prefs' => ['modo' => DeliveryLojaCheckoutHelper::tipoEntregaFromFulfillment($modo), 'cep' => $cep],
            'taxa' => $resumo['taxa'],
            'rotulo' => $resumo['rotulo'],
            'entrega_bloqueada' => (bool) ($resumo['entrega_bloqueada'] ?? false),
            'total' => $resumo['total'],
        ]);
    }

    public function checkout(string $slug): View|RedirectResponse
    {
        $config = $this->config($slug);
        $formasCheckout = DeliveryLojaCheckoutHelper::formasPagamentoLojaPublica($config);
        $prefs = $this->entregaPrefs($slug);
        $tipoCheckout = DeliveryLojaCheckoutHelper::tipoEntregaFromFulfillment($prefs['modo']);
        if ($tipoCheckout === 'balcao' && ! (bool) $config->permite_retirada) {
            $tipoCheckout = 'entrega';
        }
        $cepDigits = $prefs['cep'];
        $permiteBalcao = (bool) $config->permite_retirada;
        $passoAtual = 'checkout';
        $fidelidadeAtiva = $this->fidelidadeAtiva($config);
        $programaFidelidade = $fidelidadeAtiva ? $this->programaFidelidade($config) : null;
        $footerFixed = false;
        $freteModo = $this->frete->modoEfetivo($config);
        $checkoutOsrm = $freteModo === DeliveryFreteService::MODO_OSRM;
        $freteResumoUrl = route('delivery.public.freight.summary', [$slug]);
        $calcularEntregaApiUrl = route('delivery.public.calcular-entrega');
        $pixQrDataUri = DeliveryLojaCheckoutHelper::pixQrCodeDataUri($config);
        $pixConfigurada = DeliveryLojaCheckoutHelper::pixConfiguradaParaCheckout($config);
        $cartUrl = route('delivery.public.cart', [$slug]);

        return view('delivery.public.checkout', compact(
            'config', 'slug', 'formasCheckout', 'passoAtual', 'fidelidadeAtiva', 'programaFidelidade',
            'footerFixed', 'freteModo', 'checkoutOsrm', 'freteResumoUrl', 'calcularEntregaApiUrl',
            'tipoCheckout', 'cepDigits', 'permiteBalcao', 'pixQrDataUri', 'pixConfigurada', 'cartUrl', 'prefs'
        ));
    }

    public function fidelidade(string $slug): View
    {
        $config = $this->config($slug);
        $programa = DB::table('fid_programas')
            ->where('unidade_id', $config->unidade_id)
            ->where('ativo', 1)
            ->first();
        abort_unless($programa, 404);
        $passoAtual = 'loja';
        $fidelidadeAtiva = true;
        $footerFixed = false;

        return view('delivery.public.fidelidade', compact(
            'config', 'slug', 'programa', 'passoAtual', 'fidelidadeAtiva', 'footerFixed'
        ));
    }

    public function frete(Request $request, string $slug): JsonResponse
    {
        $config = $this->config($slug);
        $data = $request->validate([
            'fulfillment' => 'required|in:entrega,retirada,pickup',
            'cep' => 'nullable|string|max:12',
            'endereco_cep' => 'nullable|string|max:12',
            'endereco_rua' => 'nullable|string|max:180',
            'endereco_numero' => 'nullable|string|max:40',
            'endereco_bairro' => 'nullable|string|max:120',
            'endereco_cidade' => 'nullable|string|max:120',
            'endereco_uf' => 'nullable|string|size:2',
            'itens' => 'required|array|min:1|max:80',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.quantidade' => 'required|integer|min:1|max:99',
            'itens.*.opcoes' => 'nullable|array',
        ]);
        $this->validarRetirada($config, $data['fulfillment']);
        $montagem = $this->pedidos->montarItens((int) $config->unidade_id, $data['itens'], true);
        $resultado = $this->frete->calcular((int) $config->unidade_id, array_merge($data, [
            'fulfillment' => $data['fulfillment'],
            'subtotal' => $montagem['subtotal'],
            'cep' => $data['cep'] ?? $data['endereco_cep'] ?? null,
        ]));

        return response()->json(array_merge($resultado, [
            'subtotal' => $montagem['subtotal'],
            'label' => $resultado['bloqueado'] ?? false
                ? 'Entrega indisponível'
                : (($resultado['frete_gratis'] ?? false) ? 'Frete grátis' : ($resultado['rotulo'] ?? $resultado['mensagem'] ?? 'Frete calculado')),
        ]));
    }

    public function freteResumo(Request $request, string $slug): JsonResponse
    {
        $config = $this->config($slug);
        $request->validate([
            'cep' => ['nullable', 'string', 'max:16'],
            'subtotal' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);
        $digits = preg_replace('/\D+/', '', (string) $request->input('cep', ''));
        if (strlen($digits) > 0 && strlen($digits) < 8) {
            return response()->json([
                'ok' => true,
                'incomplete' => true,
            ]);
        }
        $cepParam = strlen($digits) === 8 ? $digits : null;
        $sub = $request->input('subtotal');
        $subF = $sub !== null && $sub !== '' ? (float) $sub : null;
        $resumo = $this->frete->calcularResumo($config, $cepParam, $subF);

        return response()->json([
            'ok' => true,
            'incomplete' => false,
            'taxa' => $resumo['taxa'],
            'rotulo' => $resumo['rotulo'],
            'entrega_bloqueada' => (bool) ($resumo['entrega_bloqueada'] ?? false),
        ]);
    }

    public function finalizar(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        $config = $this->config($slug);
        $payload = $request->all();
        if (is_string($payload['itens'] ?? null)) {
            $payload['itens'] = json_decode($payload['itens'], true);
        }
        if (! empty($payload['tipo_entrega']) && empty($payload['fulfillment'])) {
            $payload['fulfillment'] = DeliveryLojaCheckoutHelper::fulfillmentFromTipoEntrega((string) $payload['tipo_entrega']);
        }
        if (! empty($payload['forma_pagamento']) && empty($payload['pagamento_forma'])) {
            $payload['pagamento_forma'] = DeliveryLojaCheckoutHelper::normalizarFormaPagamento((string) $payload['forma_pagamento']);
        }
        if (! empty($payload['cep_entrega']) && empty($payload['endereco_cep'])) {
            $payload['endereco_cep'] = $payload['cep_entrega'];
        }
        if (! empty($payload['endereco']) && empty($payload['endereco_rua'])) {
            $payload['endereco_rua'] = $payload['endereco'];
        }
        if (! empty($payload['entrega_numero']) && empty($payload['endereco_numero'])) {
            $payload['endereco_numero'] = $payload['entrega_numero'];
        }
        if (! empty($payload['entrega_bairro']) && empty($payload['endereco_bairro'])) {
            $payload['endereco_bairro'] = $payload['entrega_bairro'];
        }
        if (! empty($payload['entrega_cidade']) && empty($payload['endereco_cidade'])) {
            $payload['endereco_cidade'] = $payload['entrega_cidade'];
        }
        if (! empty($payload['entrega_estado']) && empty($payload['endereco_uf'])) {
            $payload['endereco_uf'] = strtoupper((string) $payload['entrega_estado']);
        }
        if (! empty($payload['complemento']) && empty($payload['endereco_complemento'])) {
            $payload['endereco_complemento'] = $payload['complemento'];
        }
        if (empty($payload['cliente_whatsapp']) && ! empty($payload['cliente_telefone'])) {
            $payload['cliente_whatsapp'] = $payload['cliente_telefone'];
        }

        $formasCheckout = array_keys(DeliveryLojaCheckoutHelper::formasPagamentoLojaPublica($config));
        $validator = Validator::make($payload, [
            'cliente_nome' => 'required|string|max:160',
            'cliente_telefone' => 'required|string|max:30',
            'cliente_whatsapp' => 'nullable|string|max:30',
            'cliente_email' => 'nullable|email|max:190',
            'fulfillment' => 'required|in:entrega,retirada,pickup',
            'endereco_cep' => 'nullable|string|max:12',
            'endereco_rua' => 'nullable|string|max:180',
            'endereco_numero' => 'nullable|string|max:40',
            'endereco_bairro' => 'nullable|string|max:120',
            'endereco_cidade' => 'nullable|string|max:120',
            'endereco_uf' => 'nullable|string|max:2',
            'endereco_complemento' => 'nullable|string|max:500',
            'pagamento_forma' => 'required|string|max:40',
            'pagamento_dinheiro_modo' => 'nullable|in:exato,com_troco',
            'pagamento_troco_para' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string|max:220',
            'fidelidade_quero' => 'nullable|boolean',
            'itens' => 'required|array|min:1|max:80',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.quantidade' => 'required|integer|min:1|max:99',
            'itens.*.opcoes' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $data = $validator->validated();
        $data['pagamento_forma'] = DeliveryLojaCheckoutHelper::normalizarFormaPagamento($data['pagamento_forma']);
        $this->validarRetirada($config, $data['fulfillment']);
        if ($data['fulfillment'] === 'entrega') {
            foreach (['endereco_cep', 'endereco_rua', 'endereco_numero', 'endereco_bairro', 'endereco_cidade', 'endereco_uf'] as $campo) {
                if (blank($data[$campo] ?? null)) {
                    throw ValidationException::withMessages([$campo => 'Preencha o endereço completo para entrega.']);
                }
            }
        }
        if (! in_array($data['pagamento_forma'], $formasCheckout, true)) {
            throw ValidationException::withMessages(['pagamento_forma' => 'Forma de pagamento indisponível.']);
        }

        $montagem = $this->pedidos->montarItens((int) $config->unidade_id, $data['itens'], true);
        $totais = $this->pedidos->calcularTotais((int) $config->unidade_id, $data, $montagem);
        if (! empty($totais['frete']['bloqueado']) && $data['fulfillment'] === 'entrega') {
            throw ValidationException::withMessages(['endereco_cep' => $totais['frete']['mensagem'] ?? 'Entrega indisponível para este endereço.']);
        }
        $totalPedido = (float) $totais['total'];

        $pagamentoTrocoPara = null;
        if ($data['pagamento_forma'] === DeliveryLojaCheckoutHelper::PAGAMENTO_DINHEIRO) {
            $modoDin = $data['pagamento_dinheiro_modo'] ?? 'exato';
            if ($modoDin === 'com_troco') {
                $trocoPara = $data['pagamento_troco_para'] ?? null;
                if ($trocoPara === null || $trocoPara === '') {
                    throw ValidationException::withMessages([
                        'pagamento_troco_para' => 'Informe com quanto vai pagar em dinheiro (valor igual ou maior ao total) para levarmos o troco.',
                    ]);
                }
                if ((float) $trocoPara + 0.009 < $totalPedido) {
                    throw ValidationException::withMessages([
                        'pagamento_troco_para' => 'O valor deve ser igual ou maior ao total do pedido (R$ '.number_format($totalPedido, 2, ',', '.').').',
                    ]);
                }
                $pagamentoTrocoPara = round((float) $trocoPara, 2);
            }
        }

        $data['canal'] = 'loja';
        $data['pagamento_troco_para'] = $pagamentoTrocoPara;
        $data['endereco_texto'] = $data['fulfillment'] === 'entrega'
            ? trim(implode(', ', array_filter([
                $data['endereco_rua'],
                $data['endereco_numero'],
                $data['endereco_bairro'],
                $data['endereco_cidade'].'/'.$data['endereco_uf'],
            ])))
            : null;
        session()->put($this->entregaPrefsKey($slug), [
            'modo' => $data['fulfillment'],
            'cep' => preg_replace('/\D+/', '', (string) ($data['endereco_cep'] ?? '')),
        ]);
        $id = $this->pedidos->criar((int) $config->unidade_id, $data, null);
        $pedido = DB::table('dlv_pedidos')->where('id', $id)->first();
        $pixResumo = null;
        $cartaoResumo = null;
        if (DeliveryPedidoPresenter::isPix($pedido)) {
            $pixResumo = $this->pixGateway->iniciarPix($pedido, $config);
        } elseif (DeliveryPedidoPresenter::isCartaoOnline($pedido)) {
            $cartaoResumo = $this->cardGateway->iniciarCheckout($pedido, $config, [
                'success' => route('delivery.public.success', [$slug, $pedido->codigo_publico, $pedido->cliente_token]),
                'failure' => route('delivery.public.order', [$slug, $pedido->codigo_publico, $pedido->cliente_token]),
                'pending' => route('delivery.public.order', [$slug, $pedido->codigo_publico, $pedido->cliente_token]),
            ]);
        }
        if ($pixResumo !== null || $cartaoResumo !== null) {
            $pedido = DB::table('dlv_pedidos')->where('id', $id)->first();
        }
        $url = route('delivery.public.success', [$slug, $pedido->codigo_publico, $pedido->cliente_token]);

        return $request->expectsJson()
            ? response()->json([
                'codigo' => $pedido->codigo_publico,
                'redirect_url' => $url,
                'pix' => $pixResumo,
                'cartao_online' => $cartaoResumo,
            ], 201)
            : redirect()->to($url);
    }

    public function sucesso(string $slug, string $codigo, string $token): View
    {
        [$config, $pedido] = $this->pedidoSeguro($slug, $codigo, $token);
        $passoAtual = 'pedido';
        $pedidoShowUrl = route('delivery.public.order', [$slug, $codigo, $token]);
        $fidelidadeAtiva = $this->fidelidadeAtiva($config);
        $programaFidelidade = $fidelidadeAtiva ? $this->programaFidelidade($config) : null;
        $fidelidadeSnap = $this->pedidoFidelidade->snapshot($pedido, $config);
        $unidadeFidelidadeNome = $this->nomeUnidadeFidelidade($config);
        if (($fidelidadeSnap['precisa_formulario'] ?? false) && $programaFidelidade) {
            DeliveryFidelidadePublicController::marcarOrigemCompraSessao((int) $config->unidade_id);
        }
        $lgpdTexto = FidelidadeLgpdService::textoTermo(
            (string) ($config->nome_loja ?: 'Loja'),
            $unidadeFidelidadeNome,
            $config->whatsapp ?? $config->telefone ?? null
        );
        $footerFixed = false;
        extract($this->dadosPagamentoPublico($config, $pedido, $slug, $token));

        return view('delivery.public.sucesso', compact(
            'config', 'slug', 'pedido', 'token', 'passoAtual', 'pedidoShowUrl', 'fidelidadeAtiva', 'footerFixed',
            'programaFidelidade', 'fidelidadeSnap', 'lgpdTexto', 'unidadeFidelidadeNome',
            'pixConfigurada', 'pixQrDataUri', 'pixPayload', 'pixAutomatico', 'pixPollUrl',
            'cartaoCheckoutUrl', 'cartaoOnlinePendente', 'cartaoOnlinePago', 'cartaoPollUrl'
        ));
    }

    public function fidelidadePedido(Request $request, string $slug, string $codigo, string $token): JsonResponse
    {
        [$config, $pedido] = $this->pedidoSeguro($slug, $codigo, $token);

        $validator = Validator::make($request->all(), [
            'fidelidade_nome' => 'required|string|min:3|max:160',
            'fidelidade_cpf' => 'required|string|max:20',
            'fidelidade_email' => 'required|email|max:160',
            'fidelidade_whatsapp' => 'required|string|max:30',
            'lgpd_autorizo' => 'required|accepted',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $result = $this->pedidoFidelidade->concluirCadastro(
            $pedido,
            $config,
            $data['fidelidade_nome'],
            $data['fidelidade_cpf'],
            $data['fidelidade_email'],
            $data['fidelidade_whatsapp'],
            true,
            $request->ip()
        );

        return response()->json(array_merge(['ok' => true], $result));
    }

    public function pedido(string $slug, string $codigo, string $token): View
    {
        [$config, $pedido] = $this->pedidoSeguro($slug, $codigo, $token);
        $itens = DB::table('dlv_pedido_itens')->where('pedido_id', $pedido->id)->orderBy('ordem')->get();
        $historico = DB::table('dlv_pedido_historico')->where('pedido_id', $pedido->id)->orderBy('id')->get();
        $passoAtual = 'pedido';
        $pedidoShowUrl = route('delivery.public.order', [$slug, $codigo, $token]);
        $fidelidadeAtiva = $this->fidelidadeAtiva($config);
        $footerFixed = false;
        extract($this->dadosPagamentoPublico($config, $pedido, $slug, $token));

        return view('delivery.public.pedido', compact(
            'config', 'slug', 'pedido', 'token', 'itens', 'historico',
            'passoAtual', 'pedidoShowUrl', 'fidelidadeAtiva', 'footerFixed',
            'pixConfigurada', 'pixQrDataUri', 'pixPayload', 'pixAutomatico', 'pixPollUrl',
            'cartaoCheckoutUrl', 'cartaoOnlinePendente', 'cartaoOnlinePago', 'cartaoPollUrl'
        ));
    }

    public function pagamentoStatus(string $slug, string $codigo, string $token): JsonResponse
    {
        [$config, $pedido] = $this->pedidoSeguro($slug, $codigo, $token);

        if (DeliveryPedidoPresenter::isCartaoOnline($pedido)) {
            return response()->json($this->cardGateway->statusPublico($pedido, $config));
        }

        return response()->json($this->pixGateway->statusPublico($pedido, $config));
    }

    /** @return array<string, mixed> */
    private function dadosPagamentoPublico(object $config, object $pedido, string $slug, string $token): array
    {
        $pix = $this->dadosPixPublico($config, $pedido, $slug, $token);
        $cartaoCheckoutUrl = trim((string) ($pedido->pagamento_checkout_url ?? '')) ?: null;
        $cartaoOnlinePendente = DeliveryPedidoPresenter::cartaoOnlinePendente($pedido);
        $cartaoOnlinePago = DeliveryPedidoPresenter::isCartaoOnlinePago($pedido);
        $cartaoPollUrl = ($cartaoOnlinePendente && $cartaoCheckoutUrl !== null)
            ? route('delivery.public.payment.status', [$slug, $pedido->codigo_publico, $token])
            : null;

        return array_merge($pix, compact(
            'cartaoCheckoutUrl', 'cartaoOnlinePendente', 'cartaoOnlinePago', 'cartaoPollUrl'
        ));
    }

    /** @return array{pixConfigurada:bool,pixQrDataUri:?string,pixPayload:?string,pixAutomatico:bool,pixPollUrl:?string} */
    private function dadosPixPublico(object $config, object $pedido, string $slug, string $token): array
    {
        $pixPayload = trim((string) ($pedido->pagamento_pix_payload ?? '')) ?: null;
        $pixAutomatico = trim((string) ($pedido->pagamento_externo_id ?? '')) !== '';
        $pixConfigurada = DeliveryLojaCheckoutHelper::pixConfiguradaParaCheckout($config) || $pixPayload !== null;
        $pixQrDataUri = $pixPayload !== null
            ? DeliveryLojaCheckoutHelper::pixQrCodeDataUri((object) ['pix_copia_cola' => $pixPayload])
            : DeliveryLojaCheckoutHelper::pixQrCodeDataUri($config);
        $pixPollUrl = ($pixAutomatico && DeliveryPedidoPresenter::isPix($pedido) && ! DeliveryPedidoPresenter::isPixPago($pedido))
            ? route('delivery.public.payment.status', [$slug, $pedido->codigo_publico, $token])
            : null;

        return compact('pixConfigurada', 'pixQrDataUri', 'pixPayload', 'pixAutomatico', 'pixPollUrl');
    }

    private function config(string $slug): object
    {
        $config = DB::table('dlv_loja_config')->where('slug', $slug)->where('ativo', 1)->first();
        abort_unless($config, 404);
        $config->logo_url = $this->imagem($config->logo_path ?? null, (int) $config->unidade_id, 'lojas');
        $config->banner_url = $this->imagem($config->banner_path ?? null, (int) $config->unidade_id, 'lojas');
        $config->filial_logo_url = $this->imagem($config->filial_logo_path ?? null, (int) $config->unidade_id, 'lojas');

        return $config;
    }

    private function fidelidadeAtiva(object $config): bool
    {
        return $this->programaFidelidade($config) !== null;
    }

    private function programaFidelidade(object $config): ?object
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('fid_programas')) {
            return null;
        }

        $unidadeFid = $this->fidelidadeConsulta->unidadeFidelidade($config);

        return DB::table('fid_programas')
            ->where('unidade_id', $unidadeFid)
            ->where('ativo', 1)
            ->first();
    }

    private function nomeUnidadeFidelidade(object $config): string
    {
        $unidadeFid = $this->fidelidadeConsulta->unidadeFidelidade($config);
        if (\Illuminate\Support\Facades\Schema::hasTable('unidades')) {
            $nome = DB::table('unidades')->where('id', $unidadeFid)->value('nome');
            if (is_string($nome) && trim($nome) !== '') {
                return trim($nome);
            }
        }

        return (string) ($config->nome_loja ?: 'Loja');
    }

    /** @return \Illuminate\Support\Collection<int, array{url:string,alt:?string}> */
    private function bannersPublicos(object $config): \Illuminate\Support\Collection
    {
        $alt = $config->nome_loja ?: 'Banner';
        if (\Illuminate\Support\Facades\Schema::hasTable('dlv_loja_banners')) {
            $rows = DB::table('dlv_loja_banners')
                ->where('loja_config_id', $config->id)
                ->orderBy('ordem')
                ->orderBy('id')
                ->get();
            $slides = collect();
            foreach ($rows as $row) {
                $url = $this->imagem($row->caminho ?? null, (int) $config->unidade_id, 'lojas');
                if ($url) {
                    $slides->push(['url' => $url, 'alt' => $alt]);
                }
            }
            if ($slides->isNotEmpty()) {
                return $slides;
            }
        }

        return collect($config->banner_url ? [['url' => $config->banner_url, 'alt' => $alt]] : []);
    }

    private function validarRetirada(object $config, string $fulfillment): void
    {
        if (in_array($fulfillment, ['retirada', 'pickup'], true) && ! (bool) $config->permite_retirada) {
            throw ValidationException::withMessages(['fulfillment' => 'Retirada indisponível nesta loja.']);
        }
    }

    private function pagamentos(object $config): array
    {
        return DeliveryLojaCheckoutHelper::formasPagamentoLojaPublica($config);
    }

    /** @return array{modo:string,cep:string} */
    private function entregaPrefs(string $slug): array
    {
        $stored = session()->get($this->entregaPrefsKey($slug), []);
        $modo = strtolower(trim((string) ($stored['modo'] ?? 'entrega')));
        if (! in_array($modo, ['entrega', 'retirada', 'pickup'], true)) {
            $modo = 'entrega';
        }

        return [
            'modo' => $modo,
            'cep' => preg_replace('/\D+/', '', (string) ($stored['cep'] ?? '')),
        ];
    }

    private function entregaPrefsKey(string $slug): string
    {
        return 'delivery_entrega_prefs.'.$slug;
    }

    /** @return array{taxa:float,rotulo:string,entrega_bloqueada:bool,total:float} */
    private function resumoFreteCheckout(object $config, string $fulfillment, ?string $cep, float $subtotal): array
    {
        if ($fulfillment !== 'entrega') {
            return [
                'taxa' => 0.0,
                'rotulo' => 'Retirada no balcão',
                'entrega_bloqueada' => false,
                'total' => round($subtotal, 2),
            ];
        }

        $resumo = $this->frete->calcularResumo($config, $cep, $subtotal);
        $bloqueada = (bool) ($resumo['entrega_bloqueada'] ?? false);
        $taxa = (float) ($resumo['taxa'] ?? 0);

        return [
            'taxa' => $taxa,
            'rotulo' => (string) ($resumo['rotulo'] ?? ''),
            'entrega_bloqueada' => $bloqueada,
            'total' => $bloqueada ? round($subtotal, 2) : round($subtotal + $taxa, 2),
        ];
    }

    private function pedidoSeguro(string $slug, string $codigo, string $token): array
    {
        $config = $this->config($slug);
        $pedido = DB::table('dlv_pedidos')->where('unidade_id', $config->unidade_id)
            ->where('codigo_publico', $codigo)->where('canal', 'loja')->first();
        abort_unless($pedido && is_string($pedido->cliente_token ?? null)
            && strlen($token) === 64 && hash_equals($pedido->cliente_token, $token), 404);

        return [$config, $pedido];
    }

    private function produtoPublico(object $produto, int $unidadeId): object
    {
        $produto->foto_url = $this->imagem($produto->foto_path ?? null, $unidadeId, 'produtos');
        $produto->personalizavel = (bool) $produto->permite_adicionais
            || DB::table('dlv_produto_ingredientes')->where('produto_id', $produto->id)->exists();

        return $produto;
    }

    private function imagem(?string $path, int $unidadeId, string $grupo): ?string
    {
        $rel = ltrim(str_replace('\\', '/', (string) $path), '/');
        $prefix = "uploads/delivery/{$grupo}/{$unidadeId}/";

        return $rel !== '' && ! str_contains($rel, '..') && str_starts_with($rel, $prefix) ? '/'.$rel : null;
    }
}
