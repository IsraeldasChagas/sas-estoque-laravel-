<?php

namespace App\Support\SasIa;

/**
 * Limpa risadas e detecta respostas intermediárias ("aguarde", "vou consultar").
 */
class SasIaResponseSanitizer
{
    public static function limparRisadas(string $text): string
    {
        $s = $text;

        $s = preg_replace('/[\x{1F602}\x{1F923}\x{1F606}\x{1F605}\x{1F92A}]/u', '', $s) ?? $s;

        $padroesFim = [
            '/\s*[,.]?\s*(?:kk{2,}|k{3,}|rs{1,4}|rsrs|h[ae]{2,}|hehe|haha|huehue|huahuahua|lol|kkj+|kkkk+|kkkkk+|\(risos\)|\(haha\)|\(rs\))+[.!?…\s]*$/iu',
            '/\s+(?:kk|rs)\b[.!?…]*\s*$/iu',
        ];

        $anterior = null;
        while ($s !== $anterior) {
            $anterior = $s;
            foreach ($padroesFim as $padrao) {
                $s = preg_replace($padrao, '', $s) ?? $s;
            }
            $s = trim($s);
        }

        return trim(preg_replace('/\s{2,}/u', ' ', $s) ?? $s);
    }

    public static function ehIntermediaria(string $text): bool
    {
        $t = mb_strtolower(trim($text));
        if ($t === '') {
            return true;
        }

        if (mb_strlen($t) > 280) {
            return false;
        }

        $marcadores = [
            'aguarde',
            'aguarda',
            'espera aí',
            'espera ai',
            'um momento',
            'só um instante',
            'so um instante',
            'já volto',
            'ja volto',
            'deixa eu ver',
            'deixa eu consultar',
            'deixa eu checar',
            'vou verificar',
            'vou consultar',
            'vou checar',
            'estou consultando',
            'estou verificando',
            'um segundinho',
            'só um seg',
            'so um seg',
            'dar uma olhada',
            'deixa comigo',
        ];

        foreach ($marcadores as $m) {
            if (str_contains($t, $m)) {
                return true;
            }
        }

        return (bool) preg_match(
            '/^(?:ah[, ]+|olha[, ]+|então[, ]+|entao[, ]+)?(?:deixa|vou|estou|só|so|um)\b.{0,120}$/iu',
            $t
        );
    }
}
