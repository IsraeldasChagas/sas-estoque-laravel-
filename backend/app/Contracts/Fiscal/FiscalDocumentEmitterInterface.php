<?php

namespace App\Contracts\Fiscal;

/**
 * Contrato para o motor de emissão (NF-e / NFC-e). Implementação na fase seguinte.
 */
interface FiscalDocumentEmitterInterface
{
    /**
     * @param  array<string, mixed>  $payload  venda/documento normalizado
     * @return array{success: bool, chave?: string, numero?: string, serie?: string, xml?: string, danfe_url?: string, error?: string}
     */
    public function emitirNfce(int $empresaId, array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, chave?: string, numero?: string, serie?: string, xml?: string, danfe_url?: string, error?: string}
     */
    public function emitirNfe(int $empresaId, array $payload): array;
}
