<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== RESETAR SENHA DE USUÁRIO ===\n\n";

// Configuração
$email = 'israel@gruposaborparaense.com.br';
$novaSenha = '123456'; // Nova senha (altere aqui)

echo "Email: $email\n";
echo "Nova senha: $novaSenha\n\n";

$usuario = DB::table('usuarios')
    ->where('email', $email)
    ->first();

if (!$usuario) {
    echo "ERRO: Usuário não encontrado\n";
    exit(1);
}

echo "Usuário encontrado: {$usuario->nome}\n\n";

// Gera novo hash da senha
$novoHash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);

// Atualiza no banco
DB::table('usuarios')
    ->where('id', $usuario->id)
    ->update(['senha_hash' => $novoHash]);

echo "Senha resetada com sucesso!\n";
echo "Novo hash (primeiros 30 chars): " . substr($novoHash, 0, 30) . "...\n\n";
echo "Agora você pode fazer login com:\n";
echo "Email: $email\n";
echo "Senha: $novaSenha\n";



