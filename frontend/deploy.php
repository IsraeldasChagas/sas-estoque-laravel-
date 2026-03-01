<?php
// Script de deploy - faz git pull no servidor
// Acesse: https://gruposaborparaense.com.br/sas-estoque/frontend/deploy.php?key=sas2026

$key = $_GET['key'] ?? '';
if ($key !== 'sas2026') {
    http_response_code(403);
    die('Acesso negado');
}

$output = [];
$projDir = dirname(__DIR__); // sas-estoque

// Git pull
exec("cd $projDir && git pull origin main 2>&1", $output);

// Limpar cache Laravel
exec("cd $projDir/backend && php artisan config:clear 2>&1", $output);
exec("cd $projDir/backend && php artisan cache:clear 2>&1", $output);
exec("cd $projDir/backend && php artisan route:clear 2>&1", $output);

echo "<pre>";
echo implode("\n", $output);
echo "</pre>";
echo "<p>Deploy concluido!</p>";
