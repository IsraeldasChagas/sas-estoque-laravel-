<?php
/**
 * Diagnóstico + correção SAS IA (cache de config).
 * 1) Ver: https://api.gruposaborparaense.com.br/sas-ia-check.php?key=sas2026deploy
 * 2) Corrigir: https://api.gruposaborparaense.com.br/sas-ia-check.php?key=sas2026deploy&fix=1
 * Apague este arquivo depois de usar.
 */
$key = $_GET['key'] ?? '';
if ($key !== 'sas2026deploy') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

/** Lê OPENAI_API_KEY direto do arquivo .env (ignora cache do Laravel). */
function sasIaLerChaveDoArquivoEnv(string $envPath): array
{
    if (! is_file($envPath)) {
        return ['no_arquivo' => true, 'valor' => '', 'linha' => null, 'problema' => 'Arquivo .env não encontrado'];
    }

    $conteudo = file_get_contents($envPath);
    if ($conteudo === false) {
        return ['no_arquivo' => false, 'valor' => '', 'linha' => null, 'problema' => 'Não foi possível ler o .env'];
    }

    $linhaNum = 0;
    $valor = '';
    $problema = null;

    foreach (preg_split('/\r\n|\r|\n/', $conteudo) as $linha) {
        $linhaNum++;
        $trim = trim($linha);
        if ($trim === '' || str_starts_with($trim, '#')) {
            continue;
        }
        if (! str_starts_with($trim, 'OPENAI_API_KEY=')) {
            continue;
        }

        $valor = trim(substr($trim, strlen('OPENAI_API_KEY=')), " \t\"'");
        if ($valor === '') {
            $problema = 'Linha OPENAI_API_KEY existe mas está vazia (linha '.$linhaNum.')';
        } elseif (str_contains($valor, ' ')) {
            $problema = 'Chave parece quebrada em mais de uma linha (linha '.$linhaNum.')';
        }

        return ['no_arquivo' => false, 'valor' => $valor, 'linha' => $linhaNum, 'problema' => $problema];
    }

    return ['no_arquivo' => false, 'valor' => '', 'linha' => null, 'problema' => 'OPENAI_API_KEY não encontrada no .env'];
}

$envPath = dirname(__DIR__).'/.env';
$configCache = dirname(__DIR__).'/bootstrap/cache/config.php';
$configOpenAi = dirname(__DIR__).'/config/openai.php';
$fix = isset($_GET['fix']) && $_GET['fix'] === '1';

$arquivo = sasIaLerChaveDoArquivoEnv($envPath);
$fixAplicado = null;

if ($fix) {
    if (is_file($configCache)) {
        $fixAplicado = @unlink($configCache) ? 'config_cache_removido' : 'falha_remover_cache';
    } else {
        $fixAplicado = 'cache_ja_estava_limpo';
    }
}

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$viaConfig = trim((string) config('openai.api_key', ''));
$viaEnv = trim((string) env('OPENAI_API_KEY', ''));
$chaveEfetiva = $viaConfig !== '' ? $viaConfig : $viaEnv;

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'env_arquivo' => $envPath,
    'config_openai_php_existe' => is_file($configOpenAi),
    'config_cache_ativo' => is_file($configCache),
    'no_arquivo_env' => [
        'encontrada' => ($arquivo['valor'] ?? '') !== '',
        'tamanho' => strlen($arquivo['valor'] ?? ''),
        'inicio' => ($arquivo['valor'] ?? '') !== '' ? substr($arquivo['valor'], 0, 12).'...' : null,
        'linha' => $arquivo['linha'] ?? null,
        'problema' => $arquivo['problema'] ?? null,
    ],
    'laravel_config' => [
        'via_config' => $viaConfig !== '',
        'via_env' => $viaEnv !== '',
        'openai_configurada' => $chaveEfetiva !== '',
        'inicio' => $chaveEfetiva !== '' ? substr($chaveEfetiva, 0, 12).'...' : null,
    ],
    'fix_aplicado' => $fixAplicado,
    'proximo_passo' => match (true) {
        ($arquivo['valor'] ?? '') === '' => 'Cole OPENAI_API_KEY=sk-... em UMA linha no .env e acesse de novo com &fix=1',
        ! is_file($configOpenAi) => 'Envie o arquivo config/openai.php para o servidor, depois acesse com &fix=1',
        $chaveEfetiva === '' && $fix === false => 'Acesse esta URL com &fix=1 para limpar o cache: ...sas-ia-check.php?key=sas2026deploy&fix=1',
        $chaveEfetiva === '' => 'Rode no Terminal: cd ~/public_html/sas-estoque/backend && php artisan config:clear',
        default => 'Pronto! Teste o SAS IA no sistema. Apague este arquivo sas-ia-check.php.',
    },
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
