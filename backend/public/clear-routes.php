<?php
/**
 * Limpeza de cache de rotas — upload manual via FTP se artisan não estiver disponível.
 * Acesse uma vez: https://api.gruposaborparaense.com.br/clear-routes.php?key=sas2026deploy
 * Apague este arquivo depois de usar.
 */
$key = $_GET['key'] ?? '';
if ($key !== 'sas2026deploy') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

$cacheFile = __DIR__ . '/bootstrap/cache/routes-v7.php';
$removed = false;
if (is_file($cacheFile)) {
    $removed = @unlink($cacheFile);
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'cache_removido' => $removed,
    'arquivo_existia' => is_file($cacheFile) ? true : false,
    'mensagem' => $removed || !is_file($cacheFile)
        ? 'Cache de rotas limpo. Teste registrar saída novamente.'
        : 'Não foi possível remover o cache. Apague manualmente bootstrap/cache/routes-v7.php',
]);
