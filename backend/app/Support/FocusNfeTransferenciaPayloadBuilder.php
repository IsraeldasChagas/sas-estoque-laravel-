<?php

namespace App\Support;

/** Monta JSON NF-e 55 (Focus) para transferência de estoque entre CNPJs. */
final class FocusNfeTransferenciaPayloadBuilder
{
    /**
     * @param  object  $origem  linha empresas (emitente)
     * @param  object  $destino  linha empresas (destinatário)
     * @param  object  $produto
     * @param  object  $config  FiscalEmissaoConfig
     */
    public static function build(
        object $origem,
        object $destino,
        object $produto,
        object $config,
        float $quantidade,
        float $valorUnitario,
        int $movimentacaoId
    ): array {
        $cnpjOrig = FiscalCadastroSupport::normalizarCnpj($origem->cnpj ?? null);
        $cnpjDest = FiscalCadastroSupport::normalizarCnpj($destino->cnpj ?? null);
        if (! $cnpjOrig) {
            throw new \InvalidArgumentException('CNPJ da empresa de origem inválido.');
        }
        if (! $cnpjDest) {
            throw new \InvalidArgumentException('CNPJ da empresa de destino inválido.');
        }
        if ($cnpjOrig === $cnpjDest) {
            throw new \InvalidArgumentException('NF-e de transferência exige CNPJs diferentes.');
        }

        $ncm = FiscalCadastroSupport::normalizarNcm($produto->ncm ?? null);
        if (! $ncm) {
            throw new \InvalidArgumentException('Produto "'.($produto->nome ?? $produto->id).'" sem NCM.');
        }

        $ufOrig = strtoupper(trim((string) ($origem->uf ?? '')));
        $ufDest = strtoupper(trim((string) ($destino->uf ?? '')));
        if (strlen($ufOrig) !== 2 || strlen($ufDest) !== 2) {
            throw new \InvalidArgumentException('Cadastre a UF da empresa de origem e da de destino.');
        }

        $mesmoEstado = $ufOrig === $ufDest;
        $cfop = self::cfopTransferencia($produto->cfop_saida_padrao ?? null, $mesmoEstado);

        $logradouro = trim((string) ($destino->logradouro ?? ''));
        $numero = trim((string) ($destino->numero ?? ''));
        $bairro = trim((string) ($destino->bairro ?? ''));
        $cep = preg_replace('/\D+/', '', (string) ($destino->cep ?? '')) ?? '';
        $municipio = trim((string) ($destino->municipio ?? ''));
        if ($logradouro === '' || $numero === '' || $bairro === '' || strlen($cep) !== 8 || $municipio === '') {
            throw new \InvalidArgumentException(
                'Para emitir NF-e, cadastre o endereço completo da empresa destino (logradouro, número, bairro, CEP e município) em Empresas (CNPJ).'
            );
        }

        $ieDest = trim((string) ($destino->inscricao_estadual ?? ''));
        $qtd = round($quantidade, 4);
        $preco = round($valorUnitario, 4);
        if ($preco <= 0) {
            $preco = 0.01;
        }
        $bruto = round($qtd * $preco, 2);

        $tz = new \DateTimeZone('America/Belem');
        $emissao = (new \DateTimeImmutable('now', $tz))->format('Y-m-d\TH:i:sP');

        $homolog = strtolower((string) ($config->environment ?? '')) === 'homologation';
        $nomeDest = $homolog
            ? 'NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL'
            : mb_substr((string) ($destino->razao_social ?? 'Destinatario'), 0, 60);

        $csosn = FiscalCadastroSupport::normalizarCsosn($produto->csosn ?? null) ?? '102';
        $origemMerc = preg_replace('/\D/', '', (string) ($produto->origem_mercadoria ?? '0'));
        if ($origemMerc === '') {
            $origemMerc = '0';
        }

        $payload = [
            'cnpj_emitente' => $cnpjOrig,
            'natureza_operacao' => 'TRANSFERENCIA ENTRE EMPRESAS DO GRUPO',
            'data_emissao' => $emissao,
            'tipo_documento' => '1',
            'finalidade_emissao' => '1',
            'consumidor_final' => '0',
            'presenca_comprador' => '9',
            'modalidade_frete' => '9',
            'local_destino' => $mesmoEstado ? '1' : '2',
            'nome_destinatario' => $nomeDest,
            'cnpj_destinatario' => $cnpjDest,
            'inscricao_estadual_destinatario' => $ieDest !== '' ? $ieDest : 'ISENTO',
            'indicador_inscricao_estadual_destinatario' => $ieDest !== '' ? '1' : '2',
            'logradouro_destinatario' => mb_substr($logradouro, 0, 60),
            'numero_destinatario' => mb_substr($numero, 0, 60),
            'bairro_destinatario' => mb_substr($bairro, 0, 60),
            'municipio_destinatario' => mb_substr($municipio, 0, 60),
            'uf_destinatario' => $ufDest,
            'cep_destinatario' => $cep,
            'items' => [
                [
                    'numero_item' => '1',
                    'codigo_produto' => (string) $produto->id,
                    'descricao' => mb_substr((string) ($produto->nome ?? 'Item'), 0, 120),
                    'cfop' => $cfop,
                    'unidade_comercial' => self::unidadeComercial($produto->unidade_base ?? 'UN'),
                    'quantidade_comercial' => $qtd,
                    'valor_unitario_comercial' => $preco,
                    'valor_bruto' => $bruto,
                    'codigo_ncm' => $ncm,
                    'icms_origem' => $origemMerc,
                    'icms_situacao_tributaria' => $csosn,
                    'pis_situacao_tributaria' => '07',
                    'cofins_situacao_tributaria' => '07',
                ],
            ],
            'formas_pagamento' => [
                [
                    'forma_pagamento' => '90',
                    'valor_pagamento' => $bruto,
                ],
            ],
        ];

        $codMun = preg_replace('/\D+/', '', (string) ($destino->codigo_municipio ?? ''));
        if (is_string($codMun) && strlen($codMun) === 7) {
            $payload['codigo_municipio_destinatario'] = $codMun;
        }

        if ($config->serie_nfe) {
            $payload['serie'] = (string) (int) $config->serie_nfe;
        }
        if ($config->numero_proximo_nfe) {
            $payload['numero'] = (string) (int) $config->numero_proximo_nfe;
        }

        $payload['informacoes_adicionais_contribuinte'] = 'Transferencia de estoque SAS movimentacao #'.$movimentacaoId;

        return $payload;
    }

    public static function cfopTransferencia(?string $cfopProduto, bool $mesmoEstado): string
    {
        $cfop = FiscalCadastroSupport::normalizarCfop($cfopProduto);
        if ($cfop && strlen($cfop) === 4) {
            $dig = $cfop[0];
            if ($mesmoEstado && $dig === '6') {
                return '5'.substr($cfop, 1);
            }
            if (! $mesmoEstado && $dig === '5') {
                return '6'.substr($cfop, 1);
            }

            return $cfop;
        }

        return $mesmoEstado ? '5102' : '6102';
    }

    private static function unidadeComercial(?string $u): string
    {
        $u = strtoupper(trim((string) $u));
        if ($u === '' || $u === 'UND' || $u === 'UN') {
            return 'UN';
        }
        if (strlen($u) > 6) {
            return 'UN';
        }

        return $u;
    }
}
