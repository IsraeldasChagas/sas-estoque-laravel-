<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Ajuste de separacao usuario x funcionario ===\n";

$afetados = DB::table('funcionarios')
    ->where('cpf', 'like', '999.999.%')
    ->orWhere('cpf', 'like', '998.998.%')
    ->update([
        'usuario_id' => null,
        'possui_acesso' => 0,
        'updated_at' => now(),
    ]);

echo "Registros ajustados: {$afetados}\n";

