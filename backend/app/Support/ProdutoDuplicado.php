<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProdutoDuplicado
{
    public static function nomeComparavel(string $nome): string
    {
        $nome = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $nome) ?? $nome;
        if (class_exists(\Normalizer::class)) {
            $normalizado = \Normalizer::normalize($nome, \Normalizer::FORM_D);
            if (is_string($normalizado)) {
                $nome = preg_replace('/\p{Mn}/u', '', $normalizado) ?? $normalizado;
            }
        }
        $nome = trim((string) preg_replace('/\s+/u', ' ', $nome));
        $nome = mb_strtolower($nome, 'UTF-8');

        return strtr($nome, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
    }

    public static function mensagem(?string $nome, ?int $excetoId = null): ?string
    {
        if (! Schema::hasTable('produtos') || ! Schema::hasColumn('produtos', 'nome')) {
            return null;
        }

        $alvo = self::nomeComparavel((string) $nome);
        if ($alvo === '') {
            return null;
        }

        $consulta = DB::table('produtos')->select('id', 'nome');
        if ($excetoId) {
            $consulta->where('id', '!=', $excetoId);
        }
        foreach ($consulta->cursor() as $produto) {
            if (self::nomeComparavel((string) $produto->nome) === $alvo) {
                return 'Já existe um produto com este nome.';
            }
        }

        return null;
    }
}
