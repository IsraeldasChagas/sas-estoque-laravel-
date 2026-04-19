<?php

namespace App\Support;

/**
 * Impede regressão do incidente RH: migrations em up() que apagam colunas ou a tabela `funcionarios`.
 * Usado pelo teste PHPUnit e pelo comando `php artisan rh:verify-migration-safety`.
 */
final class MigrationRhSafetyChecker
{
    private const MIGRATIONS_SUBDIR = 'database' . DIRECTORY_SEPARATOR . 'migrations';

    /**
     * @return list<string> mensagens de violação (vazio = OK)
     */
    public static function scan(?string $backendRoot = null): array
    {
        $root = $backendRoot ?? base_path();
        $dir = $root . DIRECTORY_SEPARATOR . self::MIGRATIONS_SUBDIR;
        if (! is_dir($dir)) {
            return ["Diretório de migrations não encontrado: {$dir}"];
        }

        $violations = [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $path) {
            foreach (self::violationsInFile($path) as $msg) {
                $violations[] = $msg;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public static function violationsInFile(string $path): array
    {
        $src = @file_get_contents($path);
        if ($src === false || $src === '') {
            return [];
        }

        $up = self::extractUpMethodBody($src);
        if ($up === '') {
            return [];
        }

        $rel = basename($path);
        $out = [];

        // Apagar a tabela inteira em produção
        if (preg_match('/Schema::\s*drop(?:IfExists)?\s*\(\s*[\'"]funcionarios[\'"]\s*\)/s', $up)) {
            $out[] = "{$rel}: em up() não pode usar Schema::drop / dropIfExists em `funcionarios`.";
        }

        // dropColumn na tabela funcionarios (perda de dados RH)
        if (preg_match('/Schema::\s*table\s*\(\s*[\'"]funcionarios[\'"]/s', $up)
            && preg_match('/->\s*dropColumn\s*\(/s', $up)) {
            $out[] = "{$rel}: em up() não pode usar dropColumn em `funcionarios` (apaga dados de clientes). Use colunas novas só com hasColumn + add; nunca drop+add em tabela com dados.";
        }

        // SQL bruto comum em scripts legados
        if (preg_match('/\bDROP\s+COLUMN\b/is', $up) && preg_match('/\bfuncionarios\b/is', $up)) {
            $out[] = "{$rel}: em up() evite DROP COLUMN em `funcionarios` via SQL cru.";
        }

        return $out;
    }

    public static function extractUpMethodBody(string $src): string
    {
        if (! preg_match('/\bfunction\s+up\s*\([^)]*\)\s*(?::\s*\??\s*\w+\s*)?\{/s', $src, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }
        $openBracePos = $m[0][1] + strlen($m[0][0]) - 1;
        $depth = 0;
        $len = strlen($src);
        $body = '';
        for ($i = $openBracePos; $i < $len; $i++) {
            $ch = $src[$i];
            if ($ch === '{') {
                $depth++;
                if ($depth > 1) {
                    $body .= $ch;
                }
                continue;
            }
            if ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
                $body .= $ch;
                continue;
            }
            if ($depth >= 1) {
                $body .= $ch;
            }
        }

        return $body;
    }
}
