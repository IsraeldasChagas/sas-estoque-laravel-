<?php
/**
 * Script para resetar a tabela funcionarios e rodar as migrations.
 * Execute no diretório backend: php reset-funcionarios-migration.php
 * Ou: php artisan migrate:refresh --path=database/migrations/2026_03_18_000001_create_funcionarios_table.php
 *     php artisan migrate --path=database/migrations/2026_03_18_000002_add_foto_to_funcionarios_table.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Reset da tabela funcionarios ===\n";

try {
    DB::statement('DROP TABLE IF EXISTS funcionarios');
    echo "Tabela funcionarios removida.\n";

    DB::table('migrations')->whereIn('migration', [
        '2026_03_18_000001_create_funcionarios_table',
        '2026_03_18_000002_add_foto_to_funcionarios_table',
    ])->delete();
    echo "Registros de migrations removidos.\n";

    echo "Executando migrations...\n";
    Artisan::call('migrate', ['--force' => true]);
    echo Artisan::output();

    if (Schema::hasTable('funcionarios')) {
        echo "Tabela funcionarios criada com sucesso!\n";
    } else {
        echo "ERRO: Tabela funcionarios nao foi criada.\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
