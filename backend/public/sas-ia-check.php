<?php
/**
 * Diagnóstico SAS IA — confere se OPENAI_API_KEY está carregada.
 * Acesse: https://api.gruposaborparaense.com.br/sas-ia-check.php?key=sas2026deploy
 * Apague este arquivo depois de usar.
 */
$key = $_GET['key'] ?? '';
if ($key !== 'sas2026deploy') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$envPath = base_path('.env');
$rawKey = trim((string) env('OPENAI_API_KEY', ''));
$model = trim((string) env('OPENAI_MODEL', '')) ?: 'gpt-4o-mini';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'env_arquivo' => $envPath,
    'env_existe' => is_file($envPath),
    'openai_configurada' => $rawKey !== '',
    'openai_inicio' => $rawKey !== '' ? substr($rawKey, 0, 12).'...' : null,
    'openai_tamanho' => strlen($rawKey),
    'modelo' => $model,
    'dica' => $rawKey === ''
        ? 'Adicione OPENAI_API_KEY=sk-... em UMA LINHA SÓ neste arquivo .env acima.'
        : 'Chave OK. Rode: php artisan config:clear',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
