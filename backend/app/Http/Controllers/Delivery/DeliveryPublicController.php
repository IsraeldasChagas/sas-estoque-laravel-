<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Services\Delivery\DeliveryFreteService;
use App\Services\Delivery\DeliveryPedidoService;
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
        $banners = collect($config->banner_url ? [['url' => $config->banner_url, 'alt' => $config->nome_loja]] : []);
        $passoAtual = 'loja';
        $fidelidadeAtiva = $this->fidelidadeAtiva($unidadeId);
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
        $fidelidadeAtiva = $this->fidelidadeAtiva((int) $config->unidade_id);
        $footerFixed = false;

        return view('delivery.public.produto', compact(
            'config', 'slug', 'produto', 'ingredientes', 'adicionais',
            'passoAtual', 'fidelidadeAtiva', 'footerFixed'
        ));
    }

    public function checkout(string $slug): View
    {
        $config = $this->config($slug);
        $pagamentos = $this->pagamentos($config);
        $passoAtual = 'checkout';
        $fidelidadeAtiva = $this->fidelidadeAtiva((int) $config->unidade_id);
        $footerFixed = false;

        return view('delivery.public.checkout', compact(
            'config', 'slug', 'pagamentos', 'passoAtual', 'fidelidadeAtiva', 'footerFixed'
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
            'itens' => 'required|array|min:1|max:80',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.quantidade' => 'required|integer|min:1|max:99',
            'itens.*.opcoes' => 'nullable|array',
        ]);
        $this->validarRetirada($config, $data['fulfillment']);
        $montagem = $this->pedidos->montarItens((int) $config->unidade_id, $data['itens'], true);
        $resultado = $this->frete->calcular((int) $config->unidade_id, [
            'fulfillment' => $data['fulfillment'], 'subtotal' => $montagem['subtotal'], 'cep' => $data['cep'] ?? null,
        ]);

        return response()->json(array_merge($resultado, ['subtotal' => $montagem['subtotal']]));
    }

    public function finalizar(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        $config = $this->config($slug);
        $payload = $request->all();
        if (is_string($payload['itens'] ?? null)) {
            $payload['itens'] = json_decode($payload['itens'], true);
        }
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
            'endereco_uf' => 'nullable|string|size:2',
            'endereco_complemento' => 'nullable|string|max:500',
            'pagamento_forma' => 'required|string|max:40',
            'observacoes' => 'nullable|string|max:1000',
            'itens' => 'required|array|min:1|max:80',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.quantidade' => 'required|integer|min:1|max:99',
            'itens.*.opcoes' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $data = $validator->validated();
        $this->validarRetirada($config, $data['fulfillment']);
        if ($data['fulfillment'] === 'entrega') {
            foreach (['endereco_cep', 'endereco_rua', 'endereco_numero', 'endereco_bairro', 'endereco_cidade', 'endereco_uf'] as $campo) {
                if (blank($data[$campo] ?? null)) {
                    throw ValidationException::withMessages([$campo => 'Preencha o endereço completo para entrega.']);
                }
            }
        }
        if (! in_array($data['pagamento_forma'], array_keys($this->pagamentos($config)), true)) {
            throw ValidationException::withMessages(['pagamento_forma' => 'Forma de pagamento indisponível.']);
        }
        $data['canal'] = 'loja';
        $data['endereco_texto'] = $data['fulfillment'] === 'entrega'
            ? trim(implode(', ', array_filter([$data['endereco_rua'], $data['endereco_numero'], $data['endereco_bairro'], $data['endereco_cidade'].'/'.$data['endereco_uf']])))
            : null;
        $id = $this->pedidos->criar((int) $config->unidade_id, $data, null);
        $pedido = DB::table('dlv_pedidos')->where('id', $id)->first();
        $url = route('delivery.public.success', [$slug, $pedido->codigo_publico, $pedido->cliente_token]);

        return $request->expectsJson()
            ? response()->json(['codigo' => $pedido->codigo_publico, 'redirect_url' => $url], 201)
            : redirect()->to($url);
    }

    public function sucesso(string $slug, string $codigo, string $token): View
    {
        [$config, $pedido] = $this->pedidoSeguro($slug, $codigo, $token);
        $passoAtual = 'pedido';
        $pedidoShowUrl = route('delivery.public.order', [$slug, $codigo, $token]);
        $fidelidadeAtiva = $this->fidelidadeAtiva((int) $config->unidade_id);
        $footerFixed = false;

        return view('delivery.public.sucesso', compact(
            'config', 'slug', 'pedido', 'token', 'passoAtual', 'pedidoShowUrl', 'fidelidadeAtiva', 'footerFixed'
        ));
    }

    public function pedido(string $slug, string $codigo, string $token): View
    {
        [$config, $pedido] = $this->pedidoSeguro($slug, $codigo, $token);
        $itens = DB::table('dlv_pedido_itens')->where('pedido_id', $pedido->id)->orderBy('ordem')->get();
        $historico = DB::table('dlv_pedido_historico')->where('pedido_id', $pedido->id)->orderBy('id')->get();
        $passoAtual = 'pedido';
        $pedidoShowUrl = route('delivery.public.order', [$slug, $codigo, $token]);
        $fidelidadeAtiva = $this->fidelidadeAtiva((int) $config->unidade_id);
        $footerFixed = false;

        return view('delivery.public.pedido', compact(
            'config', 'slug', 'pedido', 'token', 'itens', 'historico',
            'passoAtual', 'pedidoShowUrl', 'fidelidadeAtiva', 'footerFixed'
        ));
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

    private function fidelidadeAtiva(int $unidadeId): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('fid_programas')) {
            return false;
        }

        return DB::table('fid_programas')->where('unidade_id', $unidadeId)->where('ativo', 1)->exists();
    }

    private function validarRetirada(object $config, string $fulfillment): void
    {
        if (in_array($fulfillment, ['retirada', 'pickup'], true) && ! (bool) $config->permite_retirada) {
            throw ValidationException::withMessages(['fulfillment' => 'Retirada indisponível nesta loja.']);
        }
    }

    private function pagamentos(object $config): array
    {
        $raw = trim((string) ($config->formas_pagamento ?? ''));
        $itens = $raw !== '' ? preg_split('/[,;|]+/', $raw) : ['dinheiro', 'cartao', 'pix'];
        $labels = ['dinheiro' => 'Dinheiro', 'cartao' => 'Cartão na entrega', 'credito' => 'Cartão de crédito', 'debito' => 'Cartão de débito', 'pix' => 'PIX'];
        $result = [];
        foreach ($itens ?: [] as $item) {
            $key = strtolower(trim($item));
            $key = str_replace(['ã', 'é', 'í'], ['a', 'e', 'i'], $key);
            if ($key === 'pix' && blank($config->pix_chave ?? null)) {
                continue;
            }
            if ($key !== '') {
                $result[$key] = $labels[$key] ?? ucfirst($key);
            }
        }

        return $result ?: ['dinheiro' => 'Dinheiro'];
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
