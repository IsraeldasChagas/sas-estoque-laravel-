<?php

namespace App\Support;

/**
 * Monta payload EMV (BR Code) estático do PIX com valor.
 * Usado no PDV para gerar QR / copia-e-cola a partir da chave cadastrada.
 */
final class PdvPixEmvSupport
{
    public const TIPOS_PESSOA = ['pf', 'pj'];

    public const TIPOS_CHAVE = ['cpf', 'cnpj', 'email', 'telefone', 'aleatoria'];

    /**
     * @param  array{
     *   chave:string,
     *   beneficiario:string,
     *   cidade?:string,
     *   tipo_chave?:string,
     *   txid?:string|null
     * }  $dados
     */
    public static function montarPayload(array $dados, float $valor): string
    {
        $chave = self::normalizarChave(
            (string) ($dados['chave'] ?? ''),
            (string) ($dados['tipo_chave'] ?? 'aleatoria')
        );
        if ($chave === '') {
            throw new \InvalidArgumentException('Informe a chave PIX.');
        }

        $nome = self::sanitizarTexto((string) ($dados['beneficiario'] ?? 'RECEBEDOR'), 25);
        $cidade = self::sanitizarTexto((string) ($dados['cidade'] ?? 'BELEM'), 15);
        if ($nome === '') {
            $nome = 'RECEBEDOR';
        }
        if ($cidade === '') {
            $cidade = 'BELEM';
        }

        $valor = max(0, round($valor, 2));
        $txid = preg_replace('/[^A-Za-z0-9]/', '', (string) ($dados['txid'] ?? '')) ?: '***';
        $txid = substr($txid, 0, 25);

        $merchantAccount = self::tlv('00', 'br.gov.bcb.pix').self::tlv('01', $chave);
        $additional = self::tlv('05', $txid);

        $payload = self::tlv('00', '01')
            .self::tlv('26', $merchantAccount)
            .self::tlv('52', '0000')
            .self::tlv('53', '986');

        if ($valor > 0) {
            $payload .= self::tlv('54', number_format($valor, 2, '.', ''));
        }

        $payload .= self::tlv('58', 'BR')
            .self::tlv('59', $nome)
            .self::tlv('60', $cidade)
            .self::tlv('62', $additional)
            .'6304';

        return $payload.self::crc16($payload);
    }

    public static function normalizarChave(string $chave, string $tipo): string
    {
        $chave = trim($chave);
        $tipo = mb_strtolower(trim($tipo));

        return match ($tipo) {
            'cpf', 'cnpj', 'telefone' => preg_replace('/\D+/', '', $chave) ?: '',
            'email' => mb_strtolower($chave),
            default => $chave,
        };
    }

    public static function sanitizarTexto(string $texto, int $max): string
    {
        $texto = mb_strtoupper(trim($texto));
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        $texto = preg_replace('/[^A-Z0-9 ]+/', '', $texto) ?: '';
        $texto = preg_replace('/\s+/', ' ', $texto) ?: '';

        return mb_substr(trim($texto), 0, $max);
    }

    public static function tlv(string $id, string $value): string
    {
        $len = strlen($value);
        if ($len > 99) {
            throw new \InvalidArgumentException("Campo PIX {$id} excede o tamanho permitido.");
        }

        return $id.str_pad((string) $len, 2, '0', STR_PAD_LEFT).$value;
    }

    public static function crc16(string $payload): string
    {
        $polinomio = 0x1021;
        $resultado = 0xFFFF;
        $bytes = unpack('C*', $payload) ?: [];
        foreach ($bytes as $byte) {
            $resultado ^= ($byte << 8);
            for ($i = 0; $i < 8; $i++) {
                if (($resultado & 0x8000) !== 0) {
                    $resultado = (($resultado << 1) ^ $polinomio) & 0xFFFF;
                } else {
                    $resultado = ($resultado << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($resultado), 4, '0', STR_PAD_LEFT));
    }
}
