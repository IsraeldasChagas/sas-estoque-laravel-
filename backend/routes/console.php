<?php

use App\Support\MigrationRhSafetyChecker;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rh:verify-migration-safety', function () {
    $violations = MigrationRhSafetyChecker::scan();
    if ($violations !== []) {
        foreach ($violations as $line) {
            $this->error($line);
        }
        $this->newLine();
        $this->line('Corrija as migrations acima antes de rodar migrate em produção.');

        return 1;
    }
    $this->info('Migrations OK: nenhuma up() apaga tabela/colunas de `funcionarios`.');

    return 0;
})->purpose('Bloqueia migrations que apagam dados de RH (funcionarios) no método up()');
