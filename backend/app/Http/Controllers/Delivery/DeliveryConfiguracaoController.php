<?php

namespace App\Http\Controllers\Delivery;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryConfiguracaoController extends DeliveryBaseController
{
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

        $update = [
            'slug' => $data['slug'] ?? $config->slug,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : (bool) $config->ativo,
            'aberta' => array_key_exists('aberta', $data) ? (bool) $data['aberta'] : (bool) $config->aberta,
            'confirmar_pedidos' => array_key_exists('confirmar_pedidos', $data) ? (bool) $data['confirmar_pedidos'] : (bool) $config->confirmar_pedidos,
            'permite_retirada' => array_key_exists('permite_retirada', $data) ? (bool) $data['permite_retirada'] : (bool) $config->permite_retirada,
            'frete_modo' => $data['frete_modo'] ?? $config->frete_modo,
            'frete_taxa_fixa' => array_key_exists('frete_taxa_fixa', $data) ? round((float) $data['frete_taxa_fixa'], 2) : $config->frete_taxa_fixa,
            'frete_gratis_acima' => array_key_exists('frete_gratis_acima', $data) ? ($data['frete_gratis_acima'] !== null ? round((float) $data['frete_gratis_acima'], 2) : null) : $config->frete_gratis_acima,
            'frete_acrescimo_chuva_percent' => array_key_exists('frete_acrescimo_chuva_percent', $data) ? round((float) $data['frete_acrescimo_chuva_percent'], 2) : $config->frete_acrescimo_chuva_percent,
            'frete_chuva_ativa' => array_key_exists('frete_chuva_ativa', $data) ? (bool) $data['frete_chuva_ativa'] : (bool) $config->frete_chuva_ativa,
            'pix_chave' => array_key_exists('pix_chave', $data) ? $data['pix_chave'] : $config->pix_chave,
            'pix_tipo' => array_key_exists('pix_tipo', $data) ? $data['pix_tipo'] : $config->pix_tipo,
            'pix_beneficiario' => array_key_exists('pix_beneficiario', $data) ? $data['pix_beneficiario'] : $config->pix_beneficiario,
            'formas_pagamento' => array_key_exists('formas_pagamento', $data) ? $data['formas_pagamento'] : $config->formas_pagamento,
            'nome_loja' => array_key_exists('nome_loja', $data) ? $data['nome_loja'] : $config->nome_loja,
            'logo_path' => array_key_exists('logo_path', $data) ? $data['logo_path'] : $config->logo_path,
            'banner_path' => array_key_exists('banner_path', $data) ? $data['banner_path'] : $config->banner_path,
            'cor_primaria' => array_key_exists('cor_primaria', $data) ? $data['cor_primaria'] : $config->cor_primaria,
            'descricao' => array_key_exists('descricao', $data) ? $data['descricao'] : $config->descricao,
            'whatsapp' => array_key_exists('whatsapp', $data) ? $data['whatsapp'] : $config->whatsapp,
            'telefone' => array_key_exists('telefone', $data) ? $data['telefone'] : $config->telefone,
            'endereco_texto' => array_key_exists('endereco_texto', $data) ? $data['endereco_texto'] : $config->endereco_texto,
            'updated_at' => now(),
        ];

        DB::table('dlv_loja_config')->where('id', $config->id)->update($update);

        return response()->json($this->formatar(DB::table('dlv_loja_config')->where('id', $config->id)->first()));
    }

    public function vitrineShow(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryVitrine');
        $unidadeId = $this->access->exigirUnidade($request, $usuario);
        $config = $this->obterOuCriar($unidadeId);

        return response()->json([
            'unidade_id' => $unidadeId,
            'slug' => $config->slug,
            'ativo' => (bool) $config->ativo,
            'aberta' => (bool) $config->aberta,
            'nome_loja' => $config->nome_loja,
            'logo_path' => $config->logo_path,
            'banner_path' => $config->banner_path,
            'cor_primaria' => $config->cor_primaria,
            'descricao' => $config->descricao,
            'whatsapp' => $config->whatsapp,
            'telefone' => $config->telefone,
            'endereco_texto' => $config->endereco_texto,
            'preview_path' => '/loja/'.$config->slug,
        ]);
    }

    public function vitrineUpdate(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryVitrine');
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $request->all());
        $config = $this->obterOuCriar($unidadeId);

        $validator = Validator::make($request->all(), [
            'nome_loja' => 'nullable|string|max:160',
            'logo_path' => 'nullable|string|max:255',
            'banner_path' => 'nullable|string|max:255',
            'cor_primaria' => 'nullable|string|max:20',
            'descricao' => 'nullable|string',
            'whatsapp' => 'nullable|string|max:30',
            'telefone' => 'nullable|string|max:30',
            'endereco_texto' => 'nullable|string',
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

        DB::table('dlv_loja_config')->where('id', $config->id)->update([
            'slug' => $data['slug'] ?? $config->slug,
            'nome_loja' => array_key_exists('nome_loja', $data) ? $data['nome_loja'] : $config->nome_loja,
            'logo_path' => array_key_exists('logo_path', $data) ? $data['logo_path'] : $config->logo_path,
            'banner_path' => array_key_exists('banner_path', $data) ? $data['banner_path'] : $config->banner_path,
            'cor_primaria' => array_key_exists('cor_primaria', $data) ? $data['cor_primaria'] : $config->cor_primaria,
            'descricao' => array_key_exists('descricao', $data) ? $data['descricao'] : $config->descricao,
            'whatsapp' => array_key_exists('whatsapp', $data) ? $data['whatsapp'] : $config->whatsapp,
            'telefone' => array_key_exists('telefone', $data) ? $data['telefone'] : $config->telefone,
            'endereco_texto' => array_key_exists('endereco_texto', $data) ? $data['endereco_texto'] : $config->endereco_texto,
            'aberta' => array_key_exists('aberta', $data) ? (bool) $data['aberta'] : (bool) $config->aberta,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : (bool) $config->ativo,
            'updated_at' => now(),
        ]);

        return $this->vitrineShow($request);
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
            'permite_retirada' => (bool) $config->permite_retirada,
            'frete_modo' => (string) $config->frete_modo,
            'frete_taxa_fixa' => (float) $config->frete_taxa_fixa,
            'frete_gratis_acima' => $config->frete_gratis_acima !== null ? (float) $config->frete_gratis_acima : null,
            'frete_acrescimo_chuva_percent' => (float) $config->frete_acrescimo_chuva_percent,
            'frete_chuva_ativa' => (bool) $config->frete_chuva_ativa,
            'pix_chave' => $config->pix_chave,
            'pix_tipo' => $config->pix_tipo,
            'pix_beneficiario' => $config->pix_beneficiario,
            'formas_pagamento' => $config->formas_pagamento,
            'nome_loja' => $config->nome_loja,
            'logo_path' => $config->logo_path,
            'banner_path' => $config->banner_path,
            'cor_primaria' => $config->cor_primaria,
            'descricao' => $config->descricao,
            'whatsapp' => $config->whatsapp,
            'telefone' => $config->telefone,
            'endereco_texto' => $config->endereco_texto,
        ];
    }

    private function validar(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'nullable|string|max:120',
            'ativo' => 'nullable|boolean',
            'aberta' => 'nullable|boolean',
            'confirmar_pedidos' => 'nullable|boolean',
            'permite_retirada' => 'nullable|boolean',
            'frete_modo' => 'nullable|in:fixed,cep_band',
            'frete_taxa_fixa' => 'nullable|numeric|min:0',
            'frete_gratis_acima' => 'nullable|numeric|min:0',
            'frete_acrescimo_chuva_percent' => 'nullable|numeric|min:0',
            'frete_chuva_ativa' => 'nullable|boolean',
            'pix_chave' => 'nullable|string|max:180',
            'pix_tipo' => 'nullable|string|max:40',
            'pix_beneficiario' => 'nullable|string|max:160',
            'formas_pagamento' => 'nullable|string|max:255',
            'nome_loja' => 'nullable|string|max:160',
            'logo_path' => 'nullable|string|max:255',
            'banner_path' => 'nullable|string|max:255',
            'cor_primaria' => 'nullable|string|max:20',
            'descricao' => 'nullable|string',
            'whatsapp' => 'nullable|string|max:30',
            'telefone' => 'nullable|string|max:30',
            'endereco_texto' => 'nullable|string',
            'unidade_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
