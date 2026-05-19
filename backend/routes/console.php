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

Artisan::command('rh:dedupe-funcionarios {--apply : Remove duplicatas (sem esta flag, apenas simula)}', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('funcionarios')) {
        $this->error('Tabela funcionarios não existe.');

        return 1;
    }

    $grupos = \App\Support\Rh\RhFuncionarioUnicidade::gruposDuplicadosPorEmail();
    if ($grupos === []) {
        $this->info('Nenhuma duplicata por e-mail encontrada.');

        return 0;
    }

    $apply = (bool) $this->option('apply');
    $removidos = 0;
    $pulados = 0;

    foreach ($grupos as $g) {
        $this->line("E-mail: {$g['email']} → manter #{$g['manter_id']}, remover: " . implode(', ', $g['remover_ids']));

        if (! $apply) {
            continue;
        }

        foreach ($g['remover_ids'] as $id) {
            if (\App\Support\Rh\RhFuncionarioUnicidade::temReferencias($id)) {
                $this->warn("  Pulado #{$id}: possui proventos/vale/recibos vinculados.");
                $pulados++;

                continue;
            }
            $f = \Illuminate\Support\Facades\DB::table('funcionarios')->where('id', $id)->first();
            if ($f && ! empty($f->foto) && is_string($f->foto) && file_exists(public_path($f->foto))) {
                @unlink(public_path($f->foto));
            }
            \Illuminate\Support\Facades\DB::table('funcionarios')->where('id', $id)->delete();
            $this->info("  Removido #{$id}");
            $removidos++;
        }
    }

    if (! $apply) {
        $this->newLine();
        $this->comment('Simulação. Para aplicar: php artisan rh:dedupe-funcionarios --apply');

        return 0;
    }

    $this->info("Concluído. Removidos: {$removidos}. Pulados (com vínculos): {$pulados}.");

    return 0;
})->purpose('Remove funcionários duplicados pelo mesmo e-mail (mantém o cadastro mais completo)');
