<?php

namespace App\Support\SasIa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nome e foto personalizados do assistente SAS IA (sistema_configuracoes).
 */
class SasIaBranding
{
    public const DEFAULT_NOME = 'SAS IA';

    private const CHAVE_NOME = 'sas_ia_nome';

    private const CHAVE_FOTO = 'sas_ia_foto';

    /** @return array{nome: string, foto: string} */
    public static function ler(): array
    {
        $nome = self::DEFAULT_NOME;
        $foto = '';

        if (Schema::hasTable('sistema_configuracoes')) {
            $rows = DB::table('sistema_configuracoes')
                ->whereIn('chave', [self::CHAVE_NOME, self::CHAVE_FOTO])
                ->pluck('valor', 'chave');

            $n = trim((string) ($rows[self::CHAVE_NOME] ?? ''));
            if ($n !== '') {
                $nome = mb_substr($n, 0, 80);
            }
            $foto = trim((string) ($rows[self::CHAVE_FOTO] ?? ''));
        }

        return ['nome' => $nome, 'foto' => $foto];
    }

    public static function salvarNome(string $nome): void
    {
        $nome = trim($nome);
        if ($nome === '') {
            $nome = self::DEFAULT_NOME;
        }
        self::salvarChave(self::CHAVE_NOME, mb_substr($nome, 0, 80));
    }

    public static function salvarFoto(string $path): void
    {
        self::salvarChave(self::CHAVE_FOTO, trim($path));
    }

    public static function removerFoto(): void
    {
        $atual = self::ler()['foto'];
        if ($atual !== '') {
            $full = public_path($atual);
            if (is_file($full)) {
                @unlink($full);
            }
        }
        self::salvarChave(self::CHAVE_FOTO, '');
    }

    private static function salvarChave(string $chave, string $valor): void
    {
        if (! Schema::hasTable('sistema_configuracoes')) {
            return;
        }

        $exists = DB::table('sistema_configuracoes')->where('chave', $chave)->exists();
        if ($exists) {
            DB::table('sistema_configuracoes')->where('chave', $chave)->update([
                'valor' => $valor,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('sistema_configuracoes')->insert([
                'chave' => $chave,
                'valor' => $valor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
