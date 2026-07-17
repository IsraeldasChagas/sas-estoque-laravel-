<?php

namespace App\Services\Delivery;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryFreteService
{
    public function calcular(int $unidadeId, array $payload): array
    {
        $config = DB::table('dlv_loja_config')->where('unidade_id', $unidadeId)->first();
        abort_unless($config, 422, 'Configuração da loja não encontrada.');

        $fulfillment = strtolower(trim((string) ($payload['fulfillment'] ?? 'entrega')));
        $subtotal = round((float) ($payload['subtotal'] ?? 0), 2);
        $chuva = array_key_exists('chuva', $payload)
            ? (bool) $payload['chuva']
            : (bool) ($config->frete_chuva_ativa ?? false);

        if ($fulfillment === 'retirada' || $fulfillment === 'pickup') {
            return [
                'modo' => (string) $config->frete_modo,
                'fulfillment' => 'retirada',
                'frete_base' => 0.0,
                'frete_valor' => 0.0,
                'frete_gratis' => false,
                'chuva' => false,
                'bloqueado' => false,
                'mensagem' => 'Retirada sem frete.',
            ];
        }

        $modo = strtolower(trim((string) $config->frete_modo));
        $freteBase = 0.0;
        $mensagem = null;
        $bloqueado = false;

        if ($modo === 'fixed') {
            $freteBase = round((float) $config->frete_taxa_fixa, 2);
            $mensagem = 'Frete taxa fixa.';
        } elseif ($modo === 'cep_band') {
            $cep = preg_replace('/\D+/', '', (string) ($payload['cep'] ?? '')) ?? '';
            if (strlen($cep) < 8) {
                throw ValidationException::withMessages(['cep' => 'CEP inválido para cálculo de frete.']);
            }
            $cep = substr($cep, 0, 8);
            $faixa = DB::table('dlv_frete_faixas_cep')
                ->where('unidade_id', $unidadeId)
                ->where('ativo', 1)
                ->where('cep_inicio', '<=', $cep)
                ->where('cep_fim', '>=', $cep)
                ->orderBy('ordem')
                ->orderBy('id')
                ->first();

            if (! $faixa) {
                $bloqueado = true;
                $mensagem = 'CEP fora das faixas de entrega.';
            } else {
                $freteBase = round((float) $faixa->taxa, 2);
                $mensagem = $faixa->label ?: 'Frete por faixa de CEP.';
            }
        } else {
            throw ValidationException::withMessages(['frete_modo' => 'Modo de frete não suportado.']);
        }

        $gratisAcima = $config->frete_gratis_acima !== null ? (float) $config->frete_gratis_acima : null;
        $freteGratis = $gratisAcima !== null && $subtotal >= $gratisAcima;
        $freteValor = ($bloqueado || $freteGratis) ? 0.0 : $freteBase;

        $chuvaPercent = (float) ($config->frete_acrescimo_chuva_percent ?? 0);
        if (! $bloqueado && ! $freteGratis && $chuva && $chuvaPercent > 0 && $freteValor > 0) {
            $freteValor = round($freteValor * (1 + ($chuvaPercent / 100)), 2);
            $mensagem = trim(($mensagem ?? '').' Acréscimo chuva aplicado.');
        }

        return [
            'modo' => $modo,
            'fulfillment' => 'entrega',
            'frete_base' => $freteBase,
            'frete_valor' => round($freteValor, 2),
            'frete_gratis' => $freteGratis,
            'chuva' => $chuva && ! $freteGratis && ! $bloqueado,
            'bloqueado' => $bloqueado,
            'mensagem' => $mensagem,
        ];
    }
}
