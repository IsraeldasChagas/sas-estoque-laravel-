<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['movimentacoes', 'lotes'] as $table) {
    $cols = DB::select("SHOW COLUMNS FROM {$table} WHERE Field IN ('unidade', 'motivo')");
    echo "=== {$table} ===\n";
    foreach ($cols as $c) {
        echo "{$c->Field}: {$c->Type}\n";
    }
}
