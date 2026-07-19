<?php

namespace App\Http\Controllers\Delivery;

use App\Services\Delivery\DeliveryAccessService;
use App\Services\Delivery\DeliveryLojaFreteHelper;
use App\Support\Delivery\DeliveryGatewayConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryConfiguracaoController extends DeliveryBaseController
{
    private const IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        DeliveryAccessService $access,
        private readonly DeliveryLojaFreteHelper $freteHelper,
    ) {
        parent::__construct($access);
    }

    public function show(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryConfiguracoes');
        $unidadeId = $this->access->exigirUnidade($request, $usuario);
        $config = $this->obterOuCriar($unidadeId);

        return response()->json($this->formatar($config));
    }

    public function update(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryConfiguracoes');
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $request->all());
        $config = $this->obterOuCriar($unidadeId);
        $data = $this->validar($request);

        if (! empty($data['slug'])) {
            $slug = Str::slug((string) $data['slug']);
            $exists = DB::table('dlv_loja_config')
                ->where('slug', $slug)
                ->where('id', '!=', $config->id)
                ->exists();
            abort_unless(! $exists, 422, 'Slug já em uso.');
            $data['slug'] = $slug;
        }

        [$imagens, $novosArquivos, $arquivosAntigos] = $this->prepararImagens($data, $config, $unidadeId);
        $update = [
            'slug' => $data['slug'] ?? $config->slug,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : (bool) $config->ativo,
            'aberta' => array_key_exists('aberta', $data) ? (bool) $data['aberta'] : (bool) $config->aberta,
            'confirmar_pedidos' => array_key_exists('confirmar_pedidos', $data) ? (bool) $data['confirmar_pedidos'] : (bool) $config->confirmar_pedidos,
            'exigir_pix_confirmado' => array_key_exists('exigir_pix_confirmado', $data) ? (bool) $data['exigir_pix_confirmado'] : (bool) ($config->exigir_pix_confirmado ?? false),
            'permite_retirada' => array_key_exists('permite_retirada', $data) ? (bool) $data['permite_retirada'] : (bool) $config->permite_retirada,
            'frete_modo' => $data['frete_modo'] ?? $config->frete_modo,
            'frete_taxa_fixa' => array_key_exists('frete_taxa_fixa', $data) ? round((float) $data['frete_taxa_fixa'], 2) : $config->frete_taxa_fixa,
            'frete_gratis_acima' => array_key_exists('frete_gratis_acima', $data) ? ($data['frete_gratis_acima'] !== null ? round((float) $data['frete_gratis_acima'], 2) : null) : $config->frete_gratis_acima,
            'frete_acrescimo_chuva_percent' => array_key_exists('frete_acrescimo_chuva_percent', $data) ? round((float) $data['frete_acrescimo_chuva_percent'], 2) : $config->frete_acrescimo_chuva_percent,
            'frete_chuva_ativa' => array_key_exists('frete_chuva_ativa', $data) ? (bool) $data['frete_chuva_ativa'] : (bool) $config->frete_chuva_ativa,
            'frete_google_rs_por_km' => array_key_exists('frete_google_rs_por_km', $data) ? ($data['frete_google_rs_por_km'] !== null ? round((float) $data['frete_google_rs_por_km'], 2) : null) : ($config->frete_google_rs_por_km ?? null),
            'frete_google_taxa_minima' => array_key_exists('frete_google_taxa_minima', $data) ? ($data['frete_google_taxa_minima'] !== null ? round((float) $data['frete_google_taxa_minima'], 2) : null) : ($config->frete_google_taxa_minima ?? null),
            'frete_google_km_max' => array_key_exists('frete_google_km_max', $data) ? ($data['frete_google_km_max'] !== null ? round((float) $data['frete_google_km_max'], 2) : null) : ($config->frete_google_km_max ?? null),
            'frete_origem_endereco' => array_key_exists('frete_origem_endereco', $data) ? $data['frete_origem_endereco'] : ($config->frete_origem_endereco ?? null),
            'frete_entrega_lat_origem' => array_key_exists('frete_entrega_lat_origem', $data) ? ($data['frete_entrega_lat_origem'] !== null ? round((float) $data['frete_entrega_lat_origem'], 7) : null) : ($config->frete_entrega_lat_origem ?? null),
            'frete_entrega_lng_origem' => array_key_exists('frete_entrega_lng_origem', $data) ? ($data['frete_entrega_lng_origem'] !== null ? round((float) $data['frete_entrega_lng_origem'], 7) : null) : ($config->frete_entrega_lng_origem ?? null),
            'frete_km_incluso' => array_key_exists('frete_km_incluso', $data) ? ($data['frete_km_incluso'] !== null ? round((float) $data['frete_km_incluso'], 2) : null) : ($config->frete_km_incluso ?? null),
            'frete_valor_km_extra' => array_key_exists('frete_valor_km_extra', $data) ? ($data['frete_valor_km_extra'] !== null ? round((float) $data['frete_valor_km_extra'], 2) : null) : ($config->frete_valor_km_extra ?? null),
            'pix_chave' => array_key_exists('pix_chave', $data) ? $data['pix_chave'] : $config->pix_chave,
            'pix_tipo' => array_key_exists('pix_tipo', $data) ? $data['pix_tipo'] : $config->pix_tipo,
            'pix_beneficiario' => array_key_exists('pix_beneficiario', $data) ? $data['pix_beneficiario'] : $config->pix_beneficiario,
            'pix_instrucoes' => array_key_exists('pix_instrucoes', $data) ? $data['pix_instrucoes'] : ($config->pix_instrucoes ?? null),
            'pix_copia_cola' => array_key_exists('pix_copia_cola', $data) ? $data['pix_copia_cola'] : ($config->pix_copia_cola ?? null),
            'pix_banco' => array_key_exists('pix_banco', $data) ? $data['pix_banco'] : ($config->pix_banco ?? null),
            'pix_modo' => array_key_exists('pix_modo', $data) ? $data['pix_modo'] : ($config->pix_modo ?? DeliveryGatewayConfig::PIX_MODO_MANUAL),
            'pagamento_gateway' => array_key_exists('pagamento_gateway', $data)
                ? (($data['pagamento_gateway'] ?? '') !== '' ? $data['pagamento_gateway'] : null)
                : ($config->pagamento_gateway ?? null),
            'pagamento_gateway_public_key' => array_key_exists('pagamento_gateway_public_key', $data)
                ? $data['pagamento_gateway_public_key']
                : ($config->pagamento_gateway_public_key ?? null),
            'pagamento_gateway_sandbox' => array_key_exists('pagamento_gateway_sandbox', $data)
                ? (bool) $data['pagamento_gateway_sandbox']
                : (bool) ($config->pagamento_gateway_sandbox ?? true),
            'pagamento_online_ativo' => array_key_exists('pagamento_online_ativo', $data)
                ? (bool) $data['pagamento_online_ativo']
                : (bool) ($config->pagamento_online_ativo ?? false),
            'pix_expiracao_minutos' => array_key_exists('pix_expiracao_minutos', $data)
                ? max(5, min(1440, (int) $data['pix_expiracao_minutos']))
                : (int) ($config->pix_expiracao_minutos ?? 30),
            'formas_pagamento' => array_key_exists('formas_pagamento', $data) ? $data['formas_pagamento'] : $config->formas_pagamento,
            'nome_loja' => array_key_exists('nome_loja', $data) ? $data['nome_loja'] : $config->nome_loja,
            'logo_path' => $imagens['logo_path'],
            'banner_path' => $imagens['banner_path'],
            'cor_primaria' => array_key_exists('cor_primaria', $data) ? $data['cor_primaria'] : $config->cor_primaria,
            'descricao' => array_key_exists('descricao', $data) ? $data['descricao'] : $config->descricao,
            'whatsapp' => array_key_exists('whatsapp', $data) ? $data['whatsapp'] : $config->whatsapp,
            'telefone' => array_key_exists('telefone', $data) ? $data['telefone'] : $config->telefone,
            'endereco_texto' => array_key_exists('endereco_texto', $data) ? $data['endereco_texto'] : $config->endereco_texto,
            'instagram_url' => array_key_exists('instagram_url', $data) ? $this->normalizeUrl($data['instagram_url'] ?? null) : ($config->instagram_url ?? null),
            'facebook_url' => array_key_exists('facebook_url', $data) ? $this->normalizeUrl($data['facebook_url'] ?? null) : ($config->facebook_url ?? null),
            'filial_nome' => array_key_exists('filial_nome', $data) ? $data['filial_nome'] : ($config->filial_nome ?? null),
            'filial_link_url' => array_key_exists('filial_link_url', $data) ? $this->normalizeUrl($data['filial_link_url'] ?? null) : ($config->filial_link_url ?? null),
            'entrega_texto' => array_key_exists('entrega_texto', $data) ? $data['entrega_texto'] : ($config->entrega_texto ?? null),
            'updated_at' => now(),
        ];

        if (array_key_exists('pagamento_gateway_token', $data) && trim((string) $data['pagamento_gateway_token']) !== '') {
            $update['pagamento_gateway_token'] = trim((string) $data['pagamento_gateway_token']);
        }
        if (array_key_exists('pagamento_gateway_webhook_secret', $data) && trim((string) $data['pagamento_gateway_webhook_secret']) !== '') {
            $update['pagamento_gateway_webhook_secret'] = trim((string) $data['pagamento_gateway_webhook_secret']);
        }

        try {
            DB::table('dlv_loja_config')->where('id', $config->id)->update($this->somenteColunasExistentes($update));
        } catch (\Throwable $e) {
            $this->removerArquivos($novosArquivos, $unidadeId);
            throw $e;
        }
        $this->removerArquivos($arquivosAntigos, $unidadeId);

        return response()->json($this->formatar(
            DB::table('dlv_loja_config')->where('id', $config->id)->first()
        ));
    }

    public function geocodeFreteOrigem(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryConfiguracoes');
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $request->all());
        $config = $this->obterOuCriar($unidadeId);

        $data = $request->validate([
            'endereco_texto' => 'nullable|string|max:500',
            'frete_origem_endereco' => 'nullable|string|max:500',
            'frete_entrega_lat_origem' => 'nullable|numeric|between:-90,90',
            'frete_entrega_lng_origem' => 'nullable|numeric|between:-180,180',
            'unidade_id' => 'nullable|integer',
        ]);

        $geo = $this->freteHelper->geocodeOrigem(
            $data['endereco_texto'] ?? $config->endereco_texto,
            $data['frete_origem_endereco'] ?? ($config->frete_origem_endereco ?? null),
            isset($data['frete_entrega_lat_origem']) ? (float) $data['frete_entrega_lat_origem'] : null,
            isset($data['frete_entrega_lng_origem']) ? (float) $data['frete_entrega_lng_origem'] : null,
        );

        if ($geo === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Não foi possível localizar o endereço no mapa. Informe latitude/longitude ou um endereço completo.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'lat' => $geo['lat'],
            'lon' => $geo['lon'],
            'display_name' => $geo['display_name'] ?? null,
        ]);
    }

    public function vitrineShow(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryVitrine');
        $unidadeId = $this->access->exigirUnidade($request, $usuario);
        $config = $this->obterOuCriar($unidadeId);

        return response()->json($this->formatarVitrine($config, $unidadeId));
    }

    public function vitrineUpdate(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryVitrine');
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $request->all());
        $config = $this->obterOuCriar($unidadeId);

        $validator = Validator::make($request->all(), [
            'nome_loja' => 'nullable|string|max:160',
            'logo_base64' => 'nullable|string',
            'banner_base64' => 'nullable|string',
            'banners_base64' => 'nullable|array|max:10',
            'banners_base64.*' => 'nullable|string',
            'banners_remove' => 'nullable|array|max:10',
            'banners_remove.*' => 'integer',
            'logo_clear' => 'nullable|boolean',
            'banner_clear' => 'nullable|boolean',
            'cor_primaria' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'descricao' => 'nullable|string',
            'whatsapp' => 'nullable|string|max:30',
            'telefone' => 'nullable|string|max:30',
            'endereco_texto' => 'nullable|string',
            'instagram_url' => 'nullable|string|max:255',
            'facebook_url' => 'nullable|string|max:255',
            'filial_nome' => 'nullable|string|max:160',
            'filial_link_url' => 'nullable|string|max:255',
            'entrega_texto' => 'nullable|string|max:180',
            'aberta' => 'nullable|boolean',
            'ativo' => 'nullable|boolean',
            'slug' => 'nullable|string|max:120',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $data = $validator->validated();

        if (! empty($data['slug'])) {
            $slug = Str::slug((string) $data['slug']);
            $exists = DB::table('dlv_loja_config')->where('slug', $slug)->where('id', '!=', $config->id)->exists();
            abort_unless(! $exists, 422, 'Slug já em uso.');
            $data['slug'] = $slug;
        }

        // Logo via fluxo antigo; banners múltiplos em sincronizarBanners (0–10).
        $dataLogo = $data;
        unset($dataLogo['banner_base64'], $dataLogo['banner_clear']);
        [$imagens, $novosArquivos, $arquivosAntigos] = $this->prepararImagens($dataLogo, $config, $unidadeId);
        $update = [
            'slug' => $data['slug'] ?? $config->slug,
            'nome_loja' => array_key_exists('nome_loja', $data) ? $data['nome_loja'] : $config->nome_loja,
            'logo_path' => $imagens['logo_path'],
            'banner_path' => $config->banner_path,
            'cor_primaria' => array_key_exists('cor_primaria', $data) ? $data['cor_primaria'] : $config->cor_primaria,
            'descricao' => array_key_exists('descricao', $data) ? $data['descricao'] : $config->descricao,
            'whatsapp' => array_key_exists('whatsapp', $data) ? $data['whatsapp'] : $config->whatsapp,
            'telefone' => array_key_exists('telefone', $data) ? $data['telefone'] : $config->telefone,
            'endereco_texto' => array_key_exists('endereco_texto', $data) ? $data['endereco_texto'] : $config->endereco_texto,
            'instagram_url' => array_key_exists('instagram_url', $data) ? $this->normalizeUrl($data['instagram_url'] ?? null) : ($config->instagram_url ?? null),
            'facebook_url' => array_key_exists('facebook_url', $data) ? $this->normalizeUrl($data['facebook_url'] ?? null) : ($config->facebook_url ?? null),
            'filial_nome' => array_key_exists('filial_nome', $data) ? $data['filial_nome'] : ($config->filial_nome ?? null),
            'filial_link_url' => array_key_exists('filial_link_url', $data) ? $this->normalizeUrl($data['filial_link_url'] ?? null) : ($config->filial_link_url ?? null),
            'entrega_texto' => array_key_exists('entrega_texto', $data) ? $data['entrega_texto'] : ($config->entrega_texto ?? null),
            'aberta' => array_key_exists('aberta', $data) ? (bool) $data['aberta'] : (bool) $config->aberta,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : (bool) $config->ativo,
            'updated_at' => now(),
        ];
        try {
            DB::table('dlv_loja_config')->where('id', $config->id)->update($this->somenteColunasExistentes($update));
            $this->sincronizarBanners(
                DB::table('dlv_loja_config')->where('id', $config->id)->first(),
                $unidadeId,
                $data
            );
        } catch (\Throwable $e) {
            $this->removerArquivos($novosArquivos, $unidadeId);
            throw $e;
        }
        $this->removerArquivos($arquivosAntigos, $unidadeId);

        return response()->json($this->formatarVitrine(
            DB::table('dlv_loja_config')->where('id', $config->id)->first(),
            $unidadeId
        ));
    }

    private function obterOuCriar(int $unidadeId): object
    {
        $config = DB::table('dlv_loja_config')->where('unidade_id', $unidadeId)->first();
        if ($config) {
            return $config;
        }

        $agora = now();
        $slug = 'unidade-'.$unidadeId;
        $base = $slug;
        $n = 1;
        while (DB::table('dlv_loja_config')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n;
            $n++;
        }

        $id = DB::table('dlv_loja_config')->insertGetId([
            'unidade_id' => $unidadeId,
            'slug' => $slug,
            'ativo' => true,
            'aberta' => false,
            'confirmar_pedidos' => true,
            'permite_retirada' => true,
            'frete_modo' => 'fixed',
            'frete_taxa_fixa' => 0,
            'frete_gratis_acima' => null,
            'frete_acrescimo_chuva_percent' => 0,
            'frete_chuva_ativa' => false,
            'nome_loja' => 'Loja '.$unidadeId,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        return DB::table('dlv_loja_config')->where('id', $id)->first();
    }

    private function formatar(object $config): array
    {
        return [
            'id' => (int) $config->id,
            'unidade_id' => (int) $config->unidade_id,
            'slug' => (string) $config->slug,
            'ativo' => (bool) $config->ativo,
            'aberta' => (bool) $config->aberta,
            'confirmar_pedidos' => (bool) $config->confirmar_pedidos,
            'exigir_pix_confirmado' => (bool) ($config->exigir_pix_confirmado ?? false),
            'permite_retirada' => (bool) $config->permite_retirada,
            'frete_modo' => (string) $config->frete_modo,
            'frete_taxa_fixa' => (float) $config->frete_taxa_fixa,
            'frete_gratis_acima' => $config->frete_gratis_acima !== null ? (float) $config->frete_gratis_acima : null,
            'frete_acrescimo_chuva_percent' => (float) $config->frete_acrescimo_chuva_percent,
            'frete_chuva_ativa' => (bool) $config->frete_chuva_ativa,
            'frete_google_rs_por_km' => isset($config->frete_google_rs_por_km) && $config->frete_google_rs_por_km !== null ? (float) $config->frete_google_rs_por_km : null,
            'frete_google_taxa_minima' => isset($config->frete_google_taxa_minima) && $config->frete_google_taxa_minima !== null ? (float) $config->frete_google_taxa_minima : null,
            'frete_google_km_max' => isset($config->frete_google_km_max) && $config->frete_google_km_max !== null ? (float) $config->frete_google_km_max : null,
            'frete_origem_endereco' => $config->frete_origem_endereco ?? null,
            'frete_entrega_lat_origem' => isset($config->frete_entrega_lat_origem) && $config->frete_entrega_lat_origem !== null ? (float) $config->frete_entrega_lat_origem : null,
            'frete_entrega_lng_origem' => isset($config->frete_entrega_lng_origem) && $config->frete_entrega_lng_origem !== null ? (float) $config->frete_entrega_lng_origem : null,
            'frete_km_incluso' => isset($config->frete_km_incluso) && $config->frete_km_incluso !== null ? (float) $config->frete_km_incluso : null,
            'frete_valor_km_extra' => isset($config->frete_valor_km_extra) && $config->frete_valor_km_extra !== null ? (float) $config->frete_valor_km_extra : null,
            'google_maps_configured' => filled(config('services.google_maps.api_key')),
            'osm_user_agent_configured' => filled(config('services.osm_routing.http_user_agent')),
            'pix_chave' => $config->pix_chave,
            'pix_tipo' => $config->pix_tipo,
            'pix_beneficiario' => $config->pix_beneficiario,
            'pix_instrucoes' => $config->pix_instrucoes ?? null,
            'pix_copia_cola' => $config->pix_copia_cola ?? null,
            'pix_banco' => $config->pix_banco ?? null,
            'formas_pagamento' => $config->formas_pagamento,
            'nome_loja' => $config->nome_loja,
            'logo_path' => $config->logo_path,
            'logo_url' => $this->imagemUrl($config->logo_path),
            'banner_path' => $config->banner_path,
            'banner_url' => $this->imagemUrl($config->banner_path),
            'cor_primaria' => $config->cor_primaria,
            'descricao' => $config->descricao,
            'whatsapp' => $config->whatsapp,
            'telefone' => $config->telefone,
            'endereco_texto' => $config->endereco_texto,
            'instagram_url' => $config->instagram_url ?? null,
            'facebook_url' => $config->facebook_url ?? null,
            'filial_nome' => $config->filial_nome ?? null,
            'filial_link_url' => $config->filial_link_url ?? null,
            'entrega_texto' => $config->entrega_texto ?? null,
            'frete_google_checklist' => $this->freteHelper->googleChecklist($config),
            'frete_osrm_checklist' => $this->freteHelper->osrmChecklist($config),
            'frete_preview_mapa_origem' => $this->freteHelper->previewMapaOrigem($config),
            'gateway' => DeliveryGatewayConfig::resumoAdmin($config),
            'pix_modo' => DeliveryGatewayConfig::pixModo($config),
            'pagamento_gateway' => DeliveryGatewayConfig::provedor($config),
            'pagamento_gateway_public_key' => $config->pagamento_gateway_public_key ?? null,
            'pagamento_gateway_sandbox' => (bool) ($config->pagamento_gateway_sandbox ?? true),
            'pagamento_online_ativo' => (bool) ($config->pagamento_online_ativo ?? false),
            'pix_expiracao_minutos' => DeliveryGatewayConfig::pixExpiracaoMinutos($config),
        ];
    }

    private function validar(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'nullable|string|max:120',
            'ativo' => 'nullable|boolean',
            'aberta' => 'nullable|boolean',
            'confirmar_pedidos' => 'nullable|boolean',
            'exigir_pix_confirmado' => 'nullable|boolean',
            'permite_retirada' => 'nullable|boolean',
            'frete_modo' => 'nullable|in:fixed,cep_band,padrao_unico,faixas_cep,google_distancia,osrm_distancia',
            'frete_taxa_fixa' => 'nullable|numeric|min:0',
            'frete_gratis_acima' => 'nullable|numeric|min:0',
            'frete_acrescimo_chuva_percent' => 'nullable|numeric|min:0',
            'frete_chuva_ativa' => 'nullable|boolean',
            'frete_google_rs_por_km' => 'nullable|numeric|min:0',
            'frete_google_taxa_minima' => 'nullable|numeric|min:0',
            'frete_google_km_max' => 'nullable|numeric|min:0',
            'frete_origem_endereco' => 'nullable|string|max:500',
            'frete_entrega_lat_origem' => 'nullable|numeric|between:-90,90',
            'frete_entrega_lng_origem' => 'nullable|numeric|between:-180,180',
            'frete_km_incluso' => 'nullable|numeric|min:0',
            'frete_valor_km_extra' => 'nullable|numeric|min:0',
            'pix_chave' => 'nullable|string|max:180',
            'pix_tipo' => 'nullable|string|max:40',
            'pix_beneficiario' => 'nullable|string|max:160',
            'pix_instrucoes' => 'nullable|string|max:4000',
            'pix_copia_cola' => 'nullable|string|max:8192',
            'pix_banco' => 'nullable|string|max:120',
            'pix_modo' => 'nullable|in:manual,automatico,hibrido',
            'pagamento_gateway' => 'nullable|string|max:40',
            'pagamento_gateway_token' => 'nullable|string|max:500',
            'pagamento_gateway_public_key' => 'nullable|string|max:255',
            'pagamento_gateway_webhook_secret' => 'nullable|string|max:255',
            'pagamento_gateway_sandbox' => 'nullable|boolean',
            'pagamento_online_ativo' => 'nullable|boolean',
            'pix_expiracao_minutos' => 'nullable|integer|min:5|max:1440',
            'formas_pagamento' => 'nullable|string|max:255',
            'nome_loja' => 'nullable|string|max:160',
            'logo_base64' => 'nullable|string',
            'banner_base64' => 'nullable|string',
            'logo_clear' => 'nullable|boolean',
            'banner_clear' => 'nullable|boolean',
            'cor_primaria' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'descricao' => 'nullable|string',
            'whatsapp' => 'nullable|string|max:30',
            'telefone' => 'nullable|string|max:30',
            'endereco_texto' => 'nullable|string',
            'instagram_url' => 'nullable|string|max:255',
            'facebook_url' => 'nullable|string|max:255',
            'filial_nome' => 'nullable|string|max:160',
            'filial_link_url' => 'nullable|string|max:255',
            'entrega_texto' => 'nullable|string|max:180',
            'unidade_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function formatarVitrine(object $config, int $unidadeId): array
    {
        $previewPath = '/loja/'.$config->slug;
        $banners = $this->listarBanners($config, $unidadeId);
        $primeiro = $banners[0] ?? null;

        return [
            'unidade_id' => $unidadeId,
            'slug' => $config->slug,
            'ativo' => (bool) $config->ativo,
            'aberta' => (bool) $config->aberta,
            'nome_loja' => $config->nome_loja,
            'logo_path' => $config->logo_path,
            'logo_url' => $this->imagemUrl($config->logo_path),
            'banner_path' => $primeiro['path'] ?? null,
            'banner_url' => $primeiro['url'] ?? null,
            'banners' => $banners,
            'banners_max' => 10,
            'cor_primaria' => $config->cor_primaria,
            'descricao' => $config->descricao,
            'whatsapp' => $config->whatsapp,
            'telefone' => $config->telefone,
            'endereco_texto' => $config->endereco_texto,
            'instagram_url' => $config->instagram_url ?? null,
            'facebook_url' => $config->facebook_url ?? null,
            'filial_nome' => $config->filial_nome ?? null,
            'filial_link_url' => $config->filial_link_url ?? null,
            'entrega_texto' => $config->entrega_texto ?? null,
            'preview_path' => $previewPath,
            // Preferir o host da requisição (api.*) — APP_URL às vezes aponta pro domínio do frontend.
            'preview_url' => rtrim(request()->getSchemeAndHttpHost(), '/').$previewPath,
            'public_route_available' => collect(Route::getRoutes())->contains(
                fn ($route) => ltrim($route->uri(), '/') === 'loja/{slug}'
            ),
        ];
    }

    /**
     * @return list<array{id:int|null,path:string,url:string,ordem:int}>
     */
    private function listarBanners(object $config, int $unidadeId): array
    {
        if (Schema::hasTable('dlv_loja_banners')) {
            $rows = DB::table('dlv_loja_banners')
                ->where('loja_config_id', $config->id)
                ->orderBy('ordem')
                ->orderBy('id')
                ->get();
            $items = [];
            foreach ($rows as $row) {
                $url = $this->imagemUrl($row->caminho);
                if (! $url) {
                    continue;
                }
                $items[] = [
                    'id' => (int) $row->id,
                    'path' => (string) $row->caminho,
                    'url' => $url,
                    'ordem' => (int) $row->ordem,
                ];
            }
            if ($items !== []) {
                return $items;
            }
        }

        $legacyUrl = $this->imagemUrl($config->banner_path ?? null);
        if ($legacyUrl) {
            return [[
                'id' => null,
                'path' => (string) $config->banner_path,
                'url' => $legacyUrl,
                'ordem' => 0,
            ]];
        }

        return [];
    }

    private function sincronizarBanners(object $config, int $unidadeId, array $data): void
    {
        if (! Schema::hasTable('dlv_loja_banners')) {
            // Fallback legado: um único banner_path.
            if (! empty($data['banner_clear'])) {
                if ($config->banner_path) {
                    $this->removerArquivos([(string) $config->banner_path], $unidadeId);
                }
                DB::table('dlv_loja_config')->where('id', $config->id)->update([
                    'banner_path' => null,
                    'updated_at' => now(),
                ]);
            } elseif (! empty($data['banner_base64'])) {
                $novo = $this->salvarImagemBase64((string) $data['banner_base64'], $unidadeId, 'banner', 6 * 1024 * 1024);
                if ($config->banner_path && $config->banner_path !== $novo) {
                    $this->removerArquivos([(string) $config->banner_path], $unidadeId);
                }
                DB::table('dlv_loja_config')->where('id', $config->id)->update([
                    'banner_path' => $novo,
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        $novosArquivos = [];
        try {
            if (! empty($data['banner_clear'])) {
                $atuais = DB::table('dlv_loja_banners')->where('loja_config_id', $config->id)->get();
                foreach ($atuais as $row) {
                    $this->removerArquivos([(string) $row->caminho], $unidadeId);
                }
                DB::table('dlv_loja_banners')->where('loja_config_id', $config->id)->delete();
            } else {
                $remover = array_values(array_unique(array_map('intval', $data['banners_remove'] ?? [])));
                if ($remover !== []) {
                    $rows = DB::table('dlv_loja_banners')
                        ->where('loja_config_id', $config->id)
                        ->whereIn('id', $remover)
                        ->get();
                    foreach ($rows as $row) {
                        $this->removerArquivos([(string) $row->caminho], $unidadeId);
                    }
                    DB::table('dlv_loja_banners')
                        ->where('loja_config_id', $config->id)
                        ->whereIn('id', $remover)
                        ->delete();
                }
            }

            $adicoes = [];
            foreach ($data['banners_base64'] ?? [] as $base64) {
                if (is_string($base64) && $base64 !== '') {
                    $adicoes[] = $base64;
                }
            }
            if (! empty($data['banner_base64']) && is_string($data['banner_base64'])) {
                $adicoes[] = $data['banner_base64'];
            }

            $atuaisCount = DB::table('dlv_loja_banners')->where('loja_config_id', $config->id)->count();
            if ($atuaisCount + count($adicoes) > 10) {
                throw ValidationException::withMessages([
                    'banners_base64' => 'Máximo de 10 banners por loja.',
                ]);
            }

            $ordem = (int) (DB::table('dlv_loja_banners')->where('loja_config_id', $config->id)->max('ordem') ?? -1);
            foreach ($adicoes as $base64) {
                $caminho = $this->salvarImagemBase64($base64, $unidadeId, 'banner', 6 * 1024 * 1024);
                $novosArquivos[] = $caminho;
                $ordem++;
                DB::table('dlv_loja_banners')->insert([
                    'unidade_id' => $unidadeId,
                    'loja_config_id' => $config->id,
                    'caminho' => $caminho,
                    'ordem' => $ordem,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $primeiro = DB::table('dlv_loja_banners')
                ->where('loja_config_id', $config->id)
                ->orderBy('ordem')
                ->orderBy('id')
                ->value('caminho');
            DB::table('dlv_loja_config')->where('id', $config->id)->update([
                'banner_path' => $primeiro,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->removerArquivos($novosArquivos, $unidadeId);
            throw $e;
        }
    }

    /**
     * @return array{0:array{logo_path:?string,banner_path:?string},1:list<string>,2:list<string>}
     */
    private function prepararImagens(array $data, object $config, int $unidadeId): array
    {
        $paths = [
            'logo_path' => $config->logo_path,
            'banner_path' => $config->banner_path,
        ];
        $novos = [];
        $antigos = [];

        try {
            foreach (['logo', 'banner'] as $tipo) {
                $pathKey = $tipo.'_path';
                $base64Key = $tipo.'_base64';
                $clearKey = $tipo.'_clear';
                $atual = $config->{$pathKey};

                if (! empty($data[$base64Key])) {
                    $novo = $this->salvarImagemBase64(
                        (string) $data[$base64Key],
                        $unidadeId,
                        $tipo,
                        $tipo === 'logo' ? 3 * 1024 * 1024 : 6 * 1024 * 1024
                    );
                    $paths[$pathKey] = $novo;
                    $novos[] = $novo;
                    if ($atual && $atual !== $novo) {
                        $antigos[] = $atual;
                    }
                } elseif (! empty($data[$clearKey])) {
                    $paths[$pathKey] = null;
                    if ($atual) {
                        $antigos[] = $atual;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->removerArquivos($novos, $unidadeId);
            throw $e;
        }

        return [$paths, $novos, $antigos];
    }

    private function salvarImagemBase64(string $dataUrl, int $unidadeId, string $tipo, int $maxBytes): string
    {
        if (! preg_match('#^data:(image/(?:jpeg|png|webp|gif));base64,([a-zA-Z0-9+/=\r\n]+)$#', trim($dataUrl), $match)) {
            throw ValidationException::withMessages([$tipo.'_base64' => 'Imagem inválida. Use JPG, PNG, WebP ou GIF.']);
        }

        $binario = base64_decode(preg_replace('/\s+/', '', $match[2]), true);
        if ($binario === false || $binario === '' || strlen($binario) > $maxBytes) {
            throw ValidationException::withMessages([
                $tipo.'_base64' => $tipo === 'logo' ? 'O logo deve ter no máximo 3 MB.' : 'O banner deve ter no máximo 6 MB.',
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->buffer($binario);
        if (! isset(self::IMAGE_MIMES[$mime]) || @getimagesizefromstring($binario) === false) {
            throw ValidationException::withMessages([$tipo.'_base64' => 'O conteúdo enviado não é uma imagem permitida.']);
        }

        $diretorioRelativo = 'uploads/delivery/lojas/'.$unidadeId;
        $diretorio = public_path($diretorioRelativo);
        if (! is_dir($diretorio) && ! mkdir($diretorio, 0755, true) && ! is_dir($diretorio)) {
            throw ValidationException::withMessages([$tipo.'_base64' => 'Não foi possível preparar o diretório de imagens.']);
        }

        $nome = $tipo.'-'.Str::lower(Str::random(24)).'.'.self::IMAGE_MIMES[$mime];
        $relativo = $diretorioRelativo.'/'.$nome;
        if (file_put_contents(public_path($relativo), $binario, LOCK_EX) === false) {
            throw ValidationException::withMessages([$tipo.'_base64' => 'Não foi possível gravar a imagem.']);
        }

        return $relativo;
    }

    private function imagemUrl(?string $path): ?string
    {
        $relativo = $path ? ltrim(str_replace('\\', '/', $path), '/') : '';

        return $relativo !== '' && ! str_contains($relativo, '..') && str_starts_with($relativo, 'uploads/delivery/lojas/')
            ? '/'.$relativo
            : null;
    }

    private function normalizeUrl(?string $url): ?string
    {
        $value = trim((string) $url);
        if ($value === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://'.$value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }

    /** @param array<string, mixed> $update */
    private function somenteColunasExistentes(array $update): array
    {
        return array_filter(
            $update,
            fn ($key) => Schema::hasColumn('dlv_loja_config', $key),
            ARRAY_FILTER_USE_KEY
        );
    }

    /** @param list<string> $paths */
    private function removerArquivos(array $paths, int $unidadeId): void
    {
        $prefixo = 'uploads/delivery/lojas/'.$unidadeId.'/';
        foreach (array_unique($paths) as $path) {
            $relativo = ltrim(str_replace('\\', '/', (string) $path), '/');
            if ($relativo === '' || str_contains($relativo, '..') || ! str_starts_with($relativo, $prefixo)) {
                continue;
            }
            $arquivo = public_path($relativo);
            if (is_file($arquivo)) {
                @unlink($arquivo);
            }
        }
    }
}
